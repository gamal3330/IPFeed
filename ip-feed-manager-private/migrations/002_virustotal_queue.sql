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

CREATE INDEX IF NOT EXISTS idx_vt_results_status ON vt_results(vt_status);
CREATE INDEX IF NOT EXISTS idx_vt_results_checked_at ON vt_results(checked_at);
CREATE INDEX IF NOT EXISTS idx_vt_results_asn ON vt_results(vt_asn);
CREATE INDEX IF NOT EXISTS idx_vt_queue_status ON vt_queue(status);
CREATE INDEX IF NOT EXISTS idx_vt_queue_ip_status ON vt_queue(ip, status);
CREATE INDEX IF NOT EXISTS idx_vt_queue_created_at ON vt_queue(created_at);
