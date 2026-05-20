#!/usr/bin/env python3
import argparse
import sqlite3
from pathlib import Path


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
        db.execute(
            """
            CREATE TABLE IF NOT EXISTS schema_migrations (
                migration TEXT PRIMARY KEY,
                applied_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
            """
        )
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
            db.execute("INSERT INTO schema_migrations (migration) VALUES (?)", (name,))
            applied_now.append(name)

        integrity = db.execute("PRAGMA integrity_check").fetchone()[0]
        db.commit()

    database.chmod(0o640)
    print(f"database={database}")
    print(f"migrations_found={len(migrations)}")
    print(f"migrations_applied={len(applied_now)}")

    for name in applied_now:
        print(f"applied={name}")

    print(f"integrity_check={integrity}")


if __name__ == "__main__":
    main()
