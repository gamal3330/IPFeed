<?php
declare(strict_types=1);

if (!defined('IP_FEED_APP')) {
    http_response_code(403);
    exit;
}

function isSqliteStorage(string $path): bool
{
    return preg_match('/\.(sqlite|sqlite3|db)$/i', $path) === 1;
}

function databaseStorageError(string $databaseFile): string
{
    if (!extension_loaded('pdo_sqlite')) {
        return 'امتداد PHP pdo_sqlite غير مفعل. فعّله قبل استخدام تخزين SQLite.';
    }

    $dir = dirname($databaseFile);

    if (!is_dir($dir)) {
        return 'مجلد قاعدة البيانات غير موجود: ' . $dir;
    }

    if (!is_writable($dir)) {
        return 'مجلد قاعدة البيانات غير قابل للكتابة: ' . $dir;
    }

    if (file_exists($databaseFile) && !is_writable($databaseFile)) {
        return 'ملف قاعدة البيانات غير قابل للكتابة: ' . $databaseFile;
    }

    return '';
}

function sqliteConnection(string $databaseFile): PDO
{
    static $connections = [];

    if (isset($connections[$databaseFile])) {
        return $connections[$databaseFile];
    }

    $issue = databaseStorageError($databaseFile);
    if ($issue !== '') {
        throw new RuntimeException($issue);
    }

    $pdo = new PDO('sqlite:' . $databaseFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');

    ensureSqliteSchema($pdo);
    @chmod($databaseFile, 0640);

    $connections[$databaseFile] = $pdo;

    return $pdo;
}

function ensureSqliteDatabase(string $databaseFile): void
{
    sqliteConnection($databaseFile);
}

function ensureSqliteSchema(PDO $db): void
{
    $db->exec("
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
        )
    ");

    $db->exec("
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
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS geo_cache (
            ip TEXT PRIMARY KEY,
            country TEXT NOT NULL DEFAULT 'Unknown',
            country_code TEXT NOT NULL DEFAULT '',
            city TEXT NOT NULL DEFAULT 'Unknown',
            isp TEXT NOT NULL DEFAULT 'Unknown',
            updated_at TEXT NOT NULL DEFAULT ''
        )
    ");

    $db->exec("
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
        )
    ");

    $db->exec("
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
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS ip_metadata (
            ip TEXT PRIMARY KEY,
            category TEXT NOT NULL DEFAULT 'manual',
            expires_at TEXT NOT NULL DEFAULT '',
            note TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT '',
            updated_at TEXT NOT NULL DEFAULT '',
            updated_by TEXT NOT NULL DEFAULT ''
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS login_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL DEFAULT '',
            success INTEGER NOT NULL DEFAULT 0,
            source_ip TEXT NOT NULL DEFAULT '',
            user_agent TEXT NOT NULL DEFAULT '',
            reason TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT ''
        )
    ");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_logs_ip ON logs(ip)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_logs_action ON logs(action)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_logs_user ON logs(user)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_logs_time ON logs(time)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_logs_country ON logs(country)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_logs_vt_status ON logs(vt_status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_logs_vt_asn ON logs(vt_asn)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_geo_cache_country ON geo_cache(country)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_geo_cache_updated_at ON geo_cache(updated_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_vt_results_status ON vt_results(vt_status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_vt_results_checked_at ON vt_results(checked_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_vt_results_asn ON vt_results(vt_asn)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_vt_queue_status ON vt_queue(status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_vt_queue_ip_status ON vt_queue(ip, status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_vt_queue_created_at ON vt_queue(created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_ip_metadata_category ON ip_metadata(category)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_ip_metadata_expires_at ON ip_metadata(expires_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_ip_metadata_updated_by ON ip_metadata(updated_by)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_login_events_username ON login_events(username)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_login_events_success ON login_events(success)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_login_events_created_at ON login_events(created_at)");
}

function sqliteCountRows(string $databaseFile, string $table): int
{
    $allowed = ['users', 'logs', 'geo_cache', 'vt_results', 'vt_queue', 'ip_metadata', 'login_events'];

    if (!in_array($table, $allowed, true)) {
        return 0;
    }

    $db = sqliteConnection($databaseFile);
    $stmt = $db->query('SELECT COUNT(*) FROM ' . $table);

    return (int) $stmt->fetchColumn();
}

function migrateLegacyJsonToSqlite(string $databaseFile, array $legacyFiles): void
{
    ensureSqliteDatabase($databaseFile);

    $usersFile = (string) ($legacyFiles['users'] ?? '');
    if ($usersFile !== '' && sqliteCountRows($databaseFile, 'users') === 0 && file_exists($usersFile)) {
        $json = file_get_contents($usersFile);
        $data = $json !== false && trim($json) !== '' ? json_decode($json, true) : [];

        if (is_array($data)) {
            $users = [];

            foreach ($data as $key => $record) {
                $fallback = is_string($key) ? normalizeUsername($key) : '';

                if (is_array($record) && isset($record['username'])) {
                    $fallback = normalizeUsername((string) $record['username']);
                }

                if ($fallback === '') {
                    continue;
                }

                $user = normalizeUserRecord($fallback, $record);

                if ($user !== null) {
                    $users[$user['username']] = $user;
                }
            }

            if (!empty($users)) {
                saveUsers($databaseFile, $users);
            }
        }
    }

    $logFile = (string) ($legacyFiles['log'] ?? '');
    if ($logFile !== '' && sqliteCountRows($databaseFile, 'logs') === 0 && file_exists($logFile)) {
        $json = file_get_contents($logFile);
        $data = $json !== false && trim($json) !== '' ? json_decode($json, true) : [];

        if (is_array($data)) {
            saveLog($databaseFile, $data);
        }
    }

    $geoCacheFile = (string) ($legacyFiles['geo_cache'] ?? '');
    if ($geoCacheFile !== '' && sqliteCountRows($databaseFile, 'geo_cache') === 0 && file_exists($geoCacheFile)) {
        $json = file_get_contents($geoCacheFile);
        $data = $json !== false && trim($json) !== '' ? json_decode($json, true) : [];

        if (is_array($data)) {
            saveGeoCache($databaseFile, $data);
        }
    }
}
