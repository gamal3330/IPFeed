#!/usr/bin/env python3
from __future__ import annotations

import argparse
import hashlib
import json
import os
import shutil
import sqlite3
import sys
from datetime import datetime, timedelta, timezone
from pathlib import Path
from typing import Any, Dict, List
from urllib.parse import quote


def utc_now() -> datetime:
    return datetime.now(timezone.utc)


def timestamp() -> str:
    return utc_now().strftime("%Y%m%d_%H%M%S")


def iso_now() -> str:
    return utc_now().strftime("%Y-%m-%dT%H:%M:%SZ")


def ensure_private_dir(path: Path) -> None:
    path.mkdir(mode=0o750, parents=True, exist_ok=True)
    try:
        path.chmod(0o750)
    except OSError:
        pass


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def write_json_log(log_file: Path, level: str, event: str, context: Dict[str, Any]) -> None:
    ensure_private_dir(log_file.parent)
    record = {
        "time": iso_now(),
        "level": level,
        "event": event,
        "context": context,
    }

    with log_file.open("a", encoding="utf-8") as handle:
        handle.write(json.dumps(record, ensure_ascii=False, separators=(",", ":")) + "\n")

    try:
        log_file.chmod(0o640)
    except OSError:
        pass


def sqlite_uri(path: Path) -> str:
    return "file:" + quote(str(path), safe="/:") + "?mode=ro"


def backup_sqlite(source: Path, destination: Path) -> None:
    if not source.is_file():
        raise FileNotFoundError(f"SQLite database not found: {source}")

    temp_destination = destination.with_suffix(destination.suffix + ".tmp")
    if temp_destination.exists():
        temp_destination.unlink()

    source_connection = sqlite3.connect(sqlite_uri(source), uri=True)
    try:
        destination_connection = sqlite3.connect(str(temp_destination))
        try:
            source_connection.backup(destination_connection)
            integrity = destination_connection.execute("PRAGMA integrity_check").fetchone()
            if not integrity or integrity[0] != "ok":
                raise RuntimeError("SQLite backup failed integrity_check")
        finally:
            destination_connection.close()
    finally:
        source_connection.close()

    temp_destination.replace(destination)
    destination.chmod(0o640)


def copy_feed(source: Path, destination: Path) -> None:
    if not source.is_file():
        raise FileNotFoundError(f"Feed file not found: {source}")

    temp_destination = destination.with_suffix(destination.suffix + ".tmp")
    shutil.copy2(source, temp_destination)
    temp_destination.replace(destination)
    destination.chmod(0o640)


def restore_file(source: Path, destination: Path, mode: int = 0o640) -> None:
    if not source.is_file():
        raise FileNotFoundError(f"Backup file not found: {source}")

    temp_destination = destination.with_suffix(destination.suffix + ".restore_tmp")
    shutil.copy2(source, temp_destination)
    temp_destination.replace(destination)
    destination.chmod(mode)


def remove_sqlite_sidecars(database: Path) -> None:
    for suffix in ("-wal", "-shm"):
        sidecar = Path(str(database) + suffix)
        if sidecar.exists():
            sidecar.unlink()


def assert_inside(path: Path, directory: Path) -> Path:
    resolved_path = path.resolve()
    resolved_directory = directory.resolve()

    if resolved_path != resolved_directory and resolved_directory not in resolved_path.parents:
        raise ValueError(f"Backup path is outside backup directory: {resolved_path}")

    return resolved_path


def file_manifest(path: Path) -> Dict[str, Any]:
    return {
        "path": str(path),
        "size_bytes": path.stat().st_size,
        "sha256": sha256_file(path),
    }


def read_schema_version(database: Path) -> Dict[str, Any]:
    try:
        with sqlite3.connect(sqlite_uri(database), uri=True) as db:
            row = db.execute("SELECT version, migration, applied_at FROM schema_version WHERE id = 1").fetchone()

        if row:
            return {
                "version": int(row[0] or 0),
                "migration": str(row[1] or ""),
                "applied_at": str(row[2] or ""),
            }
    except sqlite3.Error:
        pass

    return {
        "version": 0,
        "migration": "",
        "applied_at": "",
    }


def purge_old_backups(backup_dir: Path, retention_days: int) -> List[str]:
    if retention_days <= 0:
        return []

    cutoff = utc_now() - timedelta(days=retention_days)
    removed: List[str] = []

    for pattern in ("ip_feed_*.sqlite", "ips_*.txt", "backup_*.json"):
        for path in backup_dir.glob(pattern):
            modified_at = datetime.fromtimestamp(path.stat().st_mtime, timezone.utc)
            if modified_at >= cutoff:
                continue

            path.unlink()
            removed.append(path.name)

    return removed


def resolve_manifest_file(backup_dir: Path, manifest: str) -> Path:
    manifest_path = Path(manifest)

    if not manifest_path.is_absolute():
        manifest_path = backup_dir / manifest_path

    manifest_path = assert_inside(manifest_path, backup_dir)

    if not manifest_path.is_file() or not manifest_path.name.startswith("backup_") or manifest_path.suffix != ".json":
        raise FileNotFoundError(f"Backup manifest not found: {manifest_path}")

    return manifest_path


def restore_from_manifest(database: Path, feed: Path, backup_dir: Path, manifest_name: str, log_file: Path) -> dict[str, Any]:
    manifest_file = resolve_manifest_file(backup_dir, manifest_name)
    manifest = json.loads(manifest_file.read_text(encoding="utf-8"))
    database_backup = assert_inside(Path(manifest["database"]["path"]), backup_dir)
    feed_backup = assert_inside(Path(manifest["feed"]["path"]), backup_dir)

    if sha256_file(database_backup) != manifest["database"]["sha256"]:
        raise RuntimeError("SQLite backup checksum mismatch")

    if sha256_file(feed_backup) != manifest["feed"]["sha256"]:
        raise RuntimeError("Feed backup checksum mismatch")

    with sqlite3.connect(sqlite_uri(database_backup), uri=True) as db:
        integrity = db.execute("PRAGMA integrity_check").fetchone()
        if not integrity or integrity[0] != "ok":
            raise RuntimeError("SQLite backup failed integrity_check")

    pre_restore_id = "pre_restore_" + timestamp()
    pre_restore_database = backup_dir / f"ip_feed_{pre_restore_id}.sqlite"
    pre_restore_feed = backup_dir / f"ips_{pre_restore_id}.txt"
    pre_restore_manifest = backup_dir / f"backup_{pre_restore_id}.json"

    backup_sqlite(database, pre_restore_database)
    copy_feed(feed, pre_restore_feed)

    pre_manifest = {
        "ok": True,
        "created_at": iso_now(),
        "type": "pre_restore",
        "schema_version": read_schema_version(pre_restore_database),
        "database": file_manifest(pre_restore_database),
        "feed": file_manifest(pre_restore_feed),
        "restored_from": manifest_file.name,
    }
    pre_restore_manifest.write_text(json.dumps(pre_manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    pre_restore_manifest.chmod(0o640)

    remove_sqlite_sidecars(database)
    restore_file(database_backup, database, 0o640)
    remove_sqlite_sidecars(database)
    restore_file(feed_backup, feed, 0o644)

    with sqlite3.connect(sqlite_uri(database), uri=True) as db:
        integrity = db.execute("PRAGMA integrity_check").fetchone()
        if not integrity or integrity[0] != "ok":
            raise RuntimeError("Restored SQLite database failed integrity_check")

    result = {
        "ok": True,
        "restored_manifest": manifest_file.name,
        "pre_restore_manifest": pre_restore_manifest.name,
        "database": str(database),
        "feed": str(feed),
    }
    write_json_log(log_file, "warning", "restore_run", result)

    return result


def parse_args() -> argparse.Namespace:
    private_dir = Path(__file__).resolve().parent
    project_dir = private_dir.parent
    env_default = lambda name, default: os.getenv(name) or default
    retention_default = env_default("IP_FEED_BACKUP_RETENTION_DAYS", "14")
    try:
        retention_days = int(retention_default)
    except ValueError:
        retention_days = 14

    parser = argparse.ArgumentParser(description="Create IPFeed SQLite and ips.txt backups.")
    parser.add_argument("action", nargs="?", choices=["backup", "restore"], default="backup")
    parser.add_argument("--database", default=env_default("IP_FEED_DATABASE", str(private_dir / "ip_feed.sqlite")))
    parser.add_argument("--feed", default=env_default("IP_FEED_FEED_FILE", str(project_dir / "ipfeed" / "ips.txt")))
    parser.add_argument("--backup-dir", default=env_default("IP_FEED_BACKUP_DIR", str(private_dir / "backups")))
    parser.add_argument("--log-file", default=env_default("IP_FEED_BACKUP_LOG", str(private_dir / "logs" / "backup.log")))
    parser.add_argument("--retention-days", type=int, default=retention_days)
    parser.add_argument("--manifest", help="Manifest name or path for restore, e.g. backup_20260520_092243.json")

    return parser.parse_args()


def main() -> int:
    args = parse_args()
    database = Path(args.database).resolve()
    feed = Path(args.feed).resolve()
    backup_dir = Path(args.backup_dir).resolve()
    log_file = Path(args.log_file).resolve()
    run_id = timestamp()

    ensure_private_dir(backup_dir)

    if args.action == "restore":
        if not args.manifest:
            print(json.dumps({
                "ok": False,
                "error": "--manifest is required for restore",
            }, ensure_ascii=False, indent=2), file=sys.stderr)
            return 2

        try:
            result = restore_from_manifest(database, feed, backup_dir, args.manifest, log_file)
            print(json.dumps(result, ensure_ascii=False, indent=2))
            return 0
        except Exception as exc:
            write_json_log(log_file, "error", "restore_failed", {
                "ok": False,
                "manifest": args.manifest,
                "error": str(exc),
            })
            print(json.dumps({
                "ok": False,
                "error": str(exc),
            }, ensure_ascii=False, indent=2), file=sys.stderr)
            return 1

    database_backup = backup_dir / f"ip_feed_{run_id}.sqlite"
    feed_backup = backup_dir / f"ips_{run_id}.txt"
    manifest_file = backup_dir / f"backup_{run_id}.json"

    try:
        backup_sqlite(database, database_backup)
        copy_feed(feed, feed_backup)

        removed = purge_old_backups(backup_dir, max(0, args.retention_days))
        manifest = {
            "ok": True,
            "created_at": iso_now(),
            "retention_days": max(0, args.retention_days),
            "schema_version": read_schema_version(database_backup),
            "database": file_manifest(database_backup),
            "feed": file_manifest(feed_backup),
            "removed_old_files": removed,
        }

        manifest_file.write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        manifest_file.chmod(0o640)

        write_json_log(log_file, "info", "backup_run", {
            "ok": True,
            "database_backup": database_backup.name,
            "feed_backup": feed_backup.name,
            "manifest": manifest_file.name,
            "removed_old_files": len(removed),
        })

        print(json.dumps({
            "ok": True,
            "backup_dir": str(backup_dir),
            "database_backup": database_backup.name,
            "feed_backup": feed_backup.name,
            "manifest": manifest_file.name,
            "removed_old_files": len(removed),
        }, ensure_ascii=False, indent=2))

        return 0
    except Exception as exc:
        write_json_log(log_file, "error", "backup_failed", {
            "ok": False,
            "error": str(exc),
        })
        print(json.dumps({
            "ok": False,
            "error": str(exc),
        }, ensure_ascii=False, indent=2), file=sys.stderr)

        return 1


if __name__ == "__main__":
    raise SystemExit(main())
