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


def file_manifest(path: Path) -> Dict[str, Any]:
    return {
        "path": str(path),
        "size_bytes": path.stat().st_size,
        "sha256": sha256_file(path),
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
    parser.add_argument("--database", default=env_default("IP_FEED_DATABASE", str(private_dir / "ip_feed.sqlite")))
    parser.add_argument("--feed", default=env_default("IP_FEED_FEED_FILE", str(project_dir / "ipfeed" / "ips.txt")))
    parser.add_argument("--backup-dir", default=env_default("IP_FEED_BACKUP_DIR", str(private_dir / "backups")))
    parser.add_argument("--log-file", default=env_default("IP_FEED_BACKUP_LOG", str(private_dir / "logs" / "backup.log")))
    parser.add_argument("--retention-days", type=int, default=retention_days)

    return parser.parse_args()


def main() -> int:
    args = parse_args()
    database = Path(args.database).resolve()
    feed = Path(args.feed).resolve()
    backup_dir = Path(args.backup_dir).resolve()
    log_file = Path(args.log_file).resolve()
    run_id = timestamp()

    ensure_private_dir(backup_dir)

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
