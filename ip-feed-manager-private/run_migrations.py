#!/usr/bin/env python3
import argparse
import re
import sqlite3
from pathlib import Path


MIGRATION_ALIASES = {
    "001_initial.sql": "001_initial_schema.sql",
    "002_vt_queue.sql": "002_virustotal_queue.sql",
    "003_ip_metadata.sql": "003_management_metadata.sql",
}


def migration_version(name: str) -> int:
    match = re.match(r"^(\d+)_", name)
    return int(match.group(1)) if match else 0


def ensure_schema_tracking(db: sqlite3.Connection) -> None:
    db.execute(
        """
        CREATE TABLE IF NOT EXISTS schema_migrations (
            migration TEXT PRIMARY KEY,
            version INTEGER NOT NULL DEFAULT 0,
            applied_at TEXT NOT NULL DEFAULT (datetime('now'))
        )
        """
    )
    columns = {row[1] for row in db.execute("PRAGMA table_info(schema_migrations)").fetchall()}
    if "version" not in columns:
        db.execute("ALTER TABLE schema_migrations ADD COLUMN version INTEGER NOT NULL DEFAULT 0")

    db.execute(
        """
        CREATE TABLE IF NOT EXISTS schema_version (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            version INTEGER NOT NULL,
            migration TEXT NOT NULL DEFAULT '',
            applied_at TEXT NOT NULL DEFAULT (datetime('now'))
        )
        """
    )


def canonicalize_renamed_migrations(db: sqlite3.Connection) -> None:
    for canonical, legacy in MIGRATION_ALIASES.items():
        legacy_row = db.execute(
            "SELECT applied_at FROM schema_migrations WHERE migration = ?",
            (legacy,),
        ).fetchone()
        canonical_row = db.execute(
            "SELECT 1 FROM schema_migrations WHERE migration = ?",
            (canonical,),
        ).fetchone()

        if legacy_row is None or canonical_row is not None:
            continue

        db.execute(
            "INSERT INTO schema_migrations (migration, version, applied_at) VALUES (?, ?, ?)",
            (canonical, migration_version(canonical), legacy_row[0]),
        )


def refresh_schema_version(db: sqlite3.Connection, migration_names: list[str]) -> tuple[int, str]:
    rows = db.execute("SELECT migration, version FROM schema_migrations").fetchall()

    for migration, version in rows:
        parsed_version = migration_version(str(migration))
        if int(version or 0) != parsed_version:
            db.execute(
                "UPDATE schema_migrations SET version = ? WHERE migration = ?",
                (parsed_version, migration),
            )

    if migration_names:
        migration = max(migration_names, key=lambda name: (migration_version(name), name))
        version = migration_version(migration)
    else:
        latest = db.execute(
            """
            SELECT migration, version
            FROM schema_migrations
            ORDER BY version DESC, migration DESC
            LIMIT 1
            """
        ).fetchone()

        if latest is None:
            version = 0
            migration = ''
        else:
            migration = str(latest[0])
            version = int(latest[1] or migration_version(migration))

    if version <= 0:
        version = 0
        migration = ''

    db.execute(
        """
        INSERT INTO schema_version (id, version, migration, applied_at)
        VALUES (1, ?, ?, datetime('now'))
        ON CONFLICT(id) DO UPDATE SET
            version = excluded.version,
            migration = excluded.migration,
            applied_at = excluded.applied_at
        """,
        (version, migration),
    )

    return version, migration


def main() -> None:
    parser = argparse.ArgumentParser(description="Run IP Feed Manager SQLite migrations.")
    parser.add_argument("--database", default=str(Path(__file__).resolve().parent / "ip_feed.sqlite"))
    parser.add_argument("--migrations-dir", default=str(Path(__file__).resolve().parent / "migrations"))
    args = parser.parse_args()

    database = Path(args.database).resolve()
    migrations_dir = Path(args.migrations_dir).resolve()
    database.parent.mkdir(parents=True, exist_ok=True)

    if not migrations_dir.is_dir():
        raise SystemExit(f"migrations directory not found: {migrations_dir}")

    migrations = sorted(migrations_dir.glob("*.sql"))

    with sqlite3.connect(database) as db:
        ensure_schema_tracking(db)
        canonicalize_renamed_migrations(db)
        applied = {
            row[0]
            for row in db.execute("SELECT migration FROM schema_migrations").fetchall()
        }

        applied_now = []

        for migration in migrations:
            name = migration.name

            if name in applied:
                continue

            db.executescript(migration.read_text())
            db.execute(
                "INSERT INTO schema_migrations (migration, version) VALUES (?, ?)",
                (name, migration_version(name)),
            )
            applied_now.append(name)

        schema_version, schema_migration = refresh_schema_version(db, [migration.name for migration in migrations])
        integrity = db.execute("PRAGMA integrity_check").fetchone()[0]
        db.commit()

    database.chmod(0o640)
    print(f"database={database}")
    print(f"migrations_found={len(migrations)}")
    print(f"migrations_applied={len(applied_now)}")

    for name in applied_now:
        print(f"applied={name}")

    print(f"schema_version={schema_version}")
    print(f"schema_migration={schema_migration}")
    print(f"integrity_check={integrity}")


if __name__ == "__main__":
    main()
