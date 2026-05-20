CREATE TABLE IF NOT EXISTS schema_version (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    version INTEGER NOT NULL,
    migration TEXT NOT NULL DEFAULT '',
    applied_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS app_state (
    namespace TEXT NOT NULL,
    key TEXT NOT NULL,
    value TEXT NOT NULL DEFAULT '{}',
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    PRIMARY KEY (namespace, key)
);

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

CREATE INDEX IF NOT EXISTS idx_logs_ip ON logs(ip);
CREATE INDEX IF NOT EXISTS idx_logs_action ON logs(action);
CREATE INDEX IF NOT EXISTS idx_logs_user ON logs(user);
CREATE INDEX IF NOT EXISTS idx_logs_time ON logs(time);
CREATE INDEX IF NOT EXISTS idx_logs_country ON logs(country);
CREATE INDEX IF NOT EXISTS idx_logs_vt_status ON logs(vt_status);
CREATE INDEX IF NOT EXISTS idx_logs_vt_asn ON logs(vt_asn);
CREATE INDEX IF NOT EXISTS idx_geo_cache_country ON geo_cache(country);
CREATE INDEX IF NOT EXISTS idx_geo_cache_updated_at ON geo_cache(updated_at);
CREATE INDEX IF NOT EXISTS idx_app_state_updated_at ON app_state(updated_at);

INSERT INTO schema_version (id, version, migration, applied_at)
VALUES (1, 1, '001_initial.sql', datetime('now'))
ON CONFLICT(id) DO UPDATE SET
    version = CASE WHEN excluded.version > schema_version.version THEN excluded.version ELSE schema_version.version END,
    migration = CASE WHEN excluded.version >= schema_version.version THEN excluded.migration ELSE schema_version.migration END,
    applied_at = CASE WHEN excluded.version >= schema_version.version THEN excluded.applied_at ELSE schema_version.applied_at END;
