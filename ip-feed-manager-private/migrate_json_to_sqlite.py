#!/usr/bin/env python3
import argparse
import json
import sqlite3
from pathlib import Path


LOG_COLUMNS = [
    "action",
    "ip",
    "country",
    "city",
    "isp",
    "reason",
    "user",
    "time",
    "source_ip",
    "vt_status",
    "vt_malicious",
    "vt_suspicious",
    "vt_harmless",
    "vt_undetected",
    "vt_timeout",
    "vt_total",
    "vt_reputation",
    "vt_asn",
    "vt_as_owner",
    "vt_last_analysis_date",
    "vt_link",
    "vt_error",
]


def load_json(path: Path, default):
    if not path.exists() or path.stat().st_size == 0:
        return default

    with path.open("r", encoding="utf-8") as handle:
        return json.load(handle)


def create_schema(db: sqlite3.Connection):
    db.executescript(
        """
        CREATE TABLE IF NOT EXISTS users (
            username TEXT PRIMARY KEY,
            display_name TEXT NOT NULL DEFAULT '',
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'operator',
            active INTEGER NOT NULL DEFAULT 1,
            must_change_password INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT '',
            updated_at TEXT NOT NULL DEFAULT '',
            last_login TEXT NOT NULL DEFAULT ''
        );

        CREATE TABLE IF NOT EXISTS logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            action TEXT NOT NULL DEFAULT 'add',
            ip TEXT NOT NULL DEFAULT '',
            country TEXT NOT NULL DEFAULT 'Unknown',
            city TEXT NOT NULL DEFAULT 'Unknown',
            isp TEXT NOT NULL DEFAULT 'Unknown',
            reason TEXT NOT NULL DEFAULT '',
            user TEXT NOT NULL DEFAULT '',
            time TEXT NOT NULL DEFAULT '',
            source_ip TEXT NOT NULL DEFAULT '',
            vt_status TEXT NOT NULL DEFAULT '-',
            vt_malicious INTEGER NOT NULL DEFAULT 0,
            vt_suspicious INTEGER NOT NULL DEFAULT 0,
            vt_harmless INTEGER NOT NULL DEFAULT 0,
            vt_undetected INTEGER NOT NULL DEFAULT 0,
            vt_timeout INTEGER NOT NULL DEFAULT 0,
            vt_total INTEGER NOT NULL DEFAULT 0,
            vt_reputation INTEGER NOT NULL DEFAULT 0,
            vt_asn INTEGER NOT NULL DEFAULT 0,
            vt_as_owner TEXT NOT NULL DEFAULT '',
            vt_last_analysis_date TEXT NOT NULL DEFAULT '',
            vt_link TEXT NOT NULL DEFAULT '',
            vt_error TEXT NOT NULL DEFAULT ''
        );

        CREATE TABLE IF NOT EXISTS geo_cache (
            ip TEXT PRIMARY KEY,
            country TEXT NOT NULL DEFAULT 'Unknown',
            country_code TEXT NOT NULL DEFAULT '',
            city TEXT NOT NULL DEFAULT 'Unknown',
            isp TEXT NOT NULL DEFAULT 'Unknown',
            updated_at TEXT NOT NULL DEFAULT ''
        );

        CREATE TABLE IF NOT EXISTS vt_results (
            ip TEXT PRIMARY KEY,
            vt_status TEXT NOT NULL DEFAULT 'غير معروف',
            vt_malicious INTEGER NOT NULL DEFAULT 0,
            vt_suspicious INTEGER NOT NULL DEFAULT 0,
            vt_harmless INTEGER NOT NULL DEFAULT 0,
            vt_undetected INTEGER NOT NULL DEFAULT 0,
            vt_timeout INTEGER NOT NULL DEFAULT 0,
            vt_total INTEGER NOT NULL DEFAULT 0,
            vt_reputation INTEGER NOT NULL DEFAULT 0,
            vt_asn INTEGER NOT NULL DEFAULT 0,
            vt_as_owner TEXT NOT NULL DEFAULT '',
            vt_last_analysis_date TEXT NOT NULL DEFAULT '',
            vt_link TEXT NOT NULL DEFAULT '',
            vt_error TEXT NOT NULL DEFAULT '',
            checked_at TEXT NOT NULL DEFAULT '',
            updated_at TEXT NOT NULL DEFAULT ''
        );

        CREATE TABLE IF NOT EXISTS vt_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'queued',
            reason TEXT NOT NULL DEFAULT '',
            user TEXT NOT NULL DEFAULT '',
            source_ip TEXT NOT NULL DEFAULT '',
            requested_action TEXT NOT NULL DEFAULT 'vt_check',
            attempts INTEGER NOT NULL DEFAULT 0,
            last_error TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT '',
            started_at TEXT NOT NULL DEFAULT '',
            completed_at TEXT NOT NULL DEFAULT '',
            next_attempt_at TEXT NOT NULL DEFAULT ''
        );

        CREATE TABLE IF NOT EXISTS ip_metadata (
            ip TEXT PRIMARY KEY,
            category TEXT NOT NULL DEFAULT 'manual',
            expires_at TEXT NOT NULL DEFAULT '',
            note TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT '',
            updated_at TEXT NOT NULL DEFAULT '',
            updated_by TEXT NOT NULL DEFAULT ''
        );

        CREATE TABLE IF NOT EXISTS login_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL DEFAULT '',
            success INTEGER NOT NULL DEFAULT 0,
            source_ip TEXT NOT NULL DEFAULT '',
            user_agent TEXT NOT NULL DEFAULT '',
            reason TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT ''
        );

        CREATE INDEX IF NOT EXISTS idx_logs_ip ON logs(ip);
        CREATE INDEX IF NOT EXISTS idx_logs_action ON logs(action);
        CREATE INDEX IF NOT EXISTS idx_logs_user ON logs(user);
        CREATE INDEX IF NOT EXISTS idx_logs_time ON logs(time);
        CREATE INDEX IF NOT EXISTS idx_logs_country ON logs(country);
        CREATE INDEX IF NOT EXISTS idx_logs_vt_status ON logs(vt_status);
        CREATE INDEX IF NOT EXISTS idx_logs_vt_asn ON logs(vt_asn);
        CREATE INDEX IF NOT EXISTS idx_geo_cache_country ON geo_cache(country);
        CREATE INDEX IF NOT EXISTS idx_geo_cache_updated_at ON geo_cache(updated_at);
        CREATE INDEX IF NOT EXISTS idx_vt_results_status ON vt_results(vt_status);
        CREATE INDEX IF NOT EXISTS idx_vt_results_checked_at ON vt_results(checked_at);
        CREATE INDEX IF NOT EXISTS idx_vt_results_asn ON vt_results(vt_asn);
        CREATE INDEX IF NOT EXISTS idx_vt_queue_status ON vt_queue(status);
        CREATE INDEX IF NOT EXISTS idx_vt_queue_ip_status ON vt_queue(ip, status);
        CREATE INDEX IF NOT EXISTS idx_vt_queue_created_at ON vt_queue(created_at);
        CREATE INDEX IF NOT EXISTS idx_ip_metadata_category ON ip_metadata(category);
        CREATE INDEX IF NOT EXISTS idx_ip_metadata_expires_at ON ip_metadata(expires_at);
        CREATE INDEX IF NOT EXISTS idx_ip_metadata_updated_by ON ip_metadata(updated_by);
        CREATE INDEX IF NOT EXISTS idx_login_events_username ON login_events(username);
        CREATE INDEX IF NOT EXISTS idx_login_events_success ON login_events(success);
        CREATE INDEX IF NOT EXISTS idx_login_events_created_at ON login_events(created_at);
        """
    )


def table_count(db: sqlite3.Connection, table: str) -> int:
    return int(db.execute(f"SELECT COUNT(*) FROM {table}").fetchone()[0])


def import_users(db: sqlite3.Connection, path: Path, force: bool) -> int:
    if force:
        db.execute("DELETE FROM users")
    elif table_count(db, "users") > 0:
        return 0

    data = load_json(path, {})
    if not isinstance(data, dict):
        return 0

    count = 0
    for key, record in data.items():
        if isinstance(record, str):
            user = {
                "username": str(key).strip().lower(),
                "display_name": str(key).strip().lower(),
                "password_hash": record,
                "role": "operator",
                "active": 1,
                "must_change_password": 0,
                "created_at": "",
                "updated_at": "",
                "last_login": "",
            }
        elif isinstance(record, dict):
            username = str(record.get("username") or key).strip().lower()
            user = {
                "username": username,
                "display_name": str(record.get("display_name") or record.get("full_name") or username),
                "password_hash": str(record.get("password_hash") or record.get("hash") or record.get("password") or ""),
                "role": str(record.get("role") or "operator"),
                "active": 1 if record.get("active", True) else 0,
                "must_change_password": 1 if record.get("must_change_password", False) else 0,
                "created_at": str(record.get("created_at") or ""),
                "updated_at": str(record.get("updated_at") or ""),
                "last_login": str(record.get("last_login") or ""),
            }
        else:
            continue

        if not user["username"] or not user["password_hash"]:
            continue

        if user["role"] not in {"admin", "operator", "viewer"}:
            user["role"] = "operator"

        db.execute(
            """
            INSERT OR REPLACE INTO users (
                username, display_name, password_hash, role, active, must_change_password,
                created_at, updated_at, last_login
            ) VALUES (
                :username, :display_name, :password_hash, :role, :active, :must_change_password,
                :created_at, :updated_at, :last_login
            )
            """,
            user,
        )
        count += 1

    return count


def import_logs(db: sqlite3.Connection, path: Path, force: bool) -> int:
    if force:
        db.execute("DELETE FROM logs")
    elif table_count(db, "logs") > 0:
        return 0

    data = load_json(path, [])
    if not isinstance(data, list):
        return 0

    defaults = {
        "action": "add",
        "ip": "",
        "country": "Unknown",
        "city": "Unknown",
        "isp": "Unknown",
        "reason": "",
        "user": "",
        "time": "",
        "source_ip": "",
        "vt_status": "-",
        "vt_as_owner": "",
        "vt_last_analysis_date": "",
        "vt_link": "",
        "vt_error": "",
    }
    integer_columns = {
        "vt_malicious",
        "vt_suspicious",
        "vt_harmless",
        "vt_undetected",
        "vt_timeout",
        "vt_total",
        "vt_reputation",
        "vt_asn",
    }
    placeholders = ", ".join(":" + column for column in LOG_COLUMNS)
    sql = f"INSERT INTO logs ({', '.join(LOG_COLUMNS)}) VALUES ({placeholders})"

    count = 0
    for row in data:
        if not isinstance(row, dict):
            continue

        normalized = {}
        for column in LOG_COLUMNS:
            if column in integer_columns:
                normalized[column] = int(row.get(column) or 0)
            else:
                normalized[column] = str(row.get(column, defaults.get(column, "")))

        if not normalized["time"]:
            normalized["time"] = str(row.get("added_at") or "")

        db.execute(sql, normalized)
        count += 1

    return count


def import_geo_cache(db: sqlite3.Connection, path: Path, force: bool) -> int:
    if force:
        db.execute("DELETE FROM geo_cache")
    elif table_count(db, "geo_cache") > 0:
        return 0

    data = load_json(path, {})
    if not isinstance(data, dict):
        return 0

    count = 0
    for ip, entry in data.items():
        if not isinstance(entry, dict):
            continue

        db.execute(
            """
            INSERT OR REPLACE INTO geo_cache (ip, country, country_code, city, isp, updated_at)
            VALUES (:ip, :country, :country_code, :city, :isp, :updated_at)
            """,
            {
                "ip": str(ip),
                "country": str(entry.get("country") or "Unknown"),
                "country_code": str(entry.get("country_code") or ""),
                "city": str(entry.get("city") or "Unknown"),
                "isp": str(entry.get("isp") or "Unknown"),
                "updated_at": str(entry.get("updated_at") or ""),
            },
        )
        count += 1

    return count


def backfill_vt_results(db: sqlite3.Connection, force: bool) -> int:
    if force:
        db.execute("DELETE FROM vt_results")
    elif table_count(db, "vt_results") > 0:
        return 0

    db.execute(
        """
        INSERT OR REPLACE INTO vt_results (
            ip, vt_status, vt_malicious, vt_suspicious, vt_harmless, vt_undetected,
            vt_timeout, vt_total, vt_reputation, vt_asn, vt_as_owner,
            vt_last_analysis_date, vt_link, vt_error, checked_at, updated_at
        )
        SELECT
            l.ip, l.vt_status, l.vt_malicious, l.vt_suspicious, l.vt_harmless, l.vt_undetected,
            l.vt_timeout, l.vt_total, l.vt_reputation, l.vt_asn, l.vt_as_owner,
            l.vt_last_analysis_date, l.vt_link, l.vt_error,
            COALESCE(NULLIF(l.time, ''), datetime('now')),
            datetime('now')
        FROM logs l
        INNER JOIN (
            SELECT ip, MAX(id) AS latest_id
            FROM logs
            WHERE ip != ''
              AND vt_status NOT IN ('', '-', 'غير مفعل', 'لم يتم الفحص', 'في الطابور')
            GROUP BY ip
        ) latest ON latest.latest_id = l.id
        """
    )

    return table_count(db, "vt_results")


def main():
    parser = argparse.ArgumentParser(description="Migrate IP Feed Manager JSON storage to SQLite.")
    parser.add_argument("--storage-dir", default=str(Path(__file__).resolve().parent))
    parser.add_argument("--database", default="")
    parser.add_argument("--force", action="store_true", help="Replace existing SQLite table contents.")
    args = parser.parse_args()

    storage_dir = Path(args.storage_dir).resolve()
    database = Path(args.database).resolve() if args.database else storage_dir / "ip_feed.sqlite"
    database.parent.mkdir(parents=True, exist_ok=True)

    with sqlite3.connect(database) as db:
        create_schema(db)
        users = import_users(db, storage_dir / "users.json", args.force)
        logs = import_logs(db, storage_dir / "ips_log.json", args.force)
        geo = import_geo_cache(db, storage_dir / "ip_geo_cache.json", args.force)
        vt_results = backfill_vt_results(db, args.force)
        ip_metadata = table_count(db, "ip_metadata")
        login_events = table_count(db, "login_events")
        db.commit()

    database.chmod(0o640)
    print(f"database={database}")
    print(f"users_imported={users}")
    print(f"logs_imported={logs}")
    print(f"geo_cache_imported={geo}")
    print(f"vt_results_backfilled={vt_results}")
    print(f"ip_metadata={ip_metadata}")
    print(f"login_events={login_events}")


if __name__ == "__main__":
    main()
