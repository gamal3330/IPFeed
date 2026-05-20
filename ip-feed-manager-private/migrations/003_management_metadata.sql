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

CREATE INDEX IF NOT EXISTS idx_ip_metadata_category ON ip_metadata(category);
CREATE INDEX IF NOT EXISTS idx_ip_metadata_expires_at ON ip_metadata(expires_at);
CREATE INDEX IF NOT EXISTS idx_ip_metadata_updated_by ON ip_metadata(updated_by);
CREATE INDEX IF NOT EXISTS idx_login_events_username ON login_events(username);
CREATE INDEX IF NOT EXISTS idx_login_events_success ON login_events(success);
CREATE INDEX IF NOT EXISTS idx_login_events_created_at ON login_events(created_at);
