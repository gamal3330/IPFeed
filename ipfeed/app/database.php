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
    $GLOBALS['IP_FEED_SQLITE_CONNECTIONS'] ??= [];
    $connections = &$GLOBALS['IP_FEED_SQLITE_CONNECTIONS'];

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

function sqliteCloseConnection(string $databaseFile): void
{
    if (!isset($GLOBALS['IP_FEED_SQLITE_CONNECTIONS'][$databaseFile])) {
        return;
    }

    $GLOBALS['IP_FEED_SQLITE_CONNECTIONS'][$databaseFile] = null;
    unset($GLOBALS['IP_FEED_SQLITE_CONNECTIONS'][$databaseFile]);
}

function ensureSqliteDatabase(string $databaseFile): void
{
    sqliteConnection($databaseFile);
}

function ensureSqliteSchema(PDO $db): void
{
    $db->exec("
        CREATE TABLE IF NOT EXISTS schema_version (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            version INTEGER NOT NULL,
            migration TEXT NOT NULL DEFAULT '',
            applied_at TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS app_state (
            namespace TEXT NOT NULL,
            key TEXT NOT NULL,
            value TEXT NOT NULL DEFAULT '{}',
            updated_at TEXT NOT NULL DEFAULT (datetime('now')),
            PRIMARY KEY (namespace, key)
        )
    ");

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
    $db->exec("CREATE INDEX IF NOT EXISTS idx_app_state_updated_at ON app_state(updated_at)");

    $db->exec("
        INSERT INTO schema_version (id, version, migration, applied_at)
        VALUES (1, 3, '003_ip_metadata.sql', datetime('now'))
        ON CONFLICT(id) DO UPDATE SET
            version = CASE WHEN schema_version.version < 3 THEN 3 ELSE schema_version.version END,
            migration = CASE WHEN schema_version.version < 3 THEN '003_ip_metadata.sql' ELSE schema_version.migration END,
            applied_at = CASE WHEN schema_version.version < 3 THEN datetime('now') ELSE schema_version.applied_at END
    ");
}

function sqliteCountRows(string $databaseFile, string $table): int
{
    $allowed = ['users', 'logs', 'geo_cache', 'vt_results', 'vt_queue', 'ip_metadata', 'login_events', 'app_state', 'schema_version'];

    if (!in_array($table, $allowed, true)) {
        return 0;
    }

    $db = sqliteConnection($databaseFile);
    $stmt = $db->query('SELECT COUNT(*) FROM ' . $table);

    return (int) $stmt->fetchColumn();
}

function sqliteSchemaVersion(string $databaseFile): array
{
    try {
        $db = sqliteConnection($databaseFile);
        $row = $db->query('SELECT version, migration, applied_at FROM schema_version WHERE id = 1')->fetch();

        if (is_array($row)) {
            return [
                'version' => (int) ($row['version'] ?? 0),
                'migration' => (string) ($row['migration'] ?? ''),
                'applied_at' => (string) ($row['applied_at'] ?? ''),
            ];
        }
    } catch (Throwable) {
    }

    return [
        'version' => 0,
        'migration' => '',
        'applied_at' => '',
    ];
}

function sqliteReadJsonState(string $databaseFile, string $namespace, string $key, array $default = []): array
{
    $db = sqliteConnection($databaseFile);
    $stmt = $db->prepare('SELECT value FROM app_state WHERE namespace = :namespace AND key = :key');
    $stmt->execute([
        ':namespace' => $namespace,
        ':key' => $key,
    ]);

    $raw = $stmt->fetchColumn();
    if (!is_string($raw) || trim($raw) === '') {
        return $default;
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : $default;
}

function sqliteWriteJsonState(string $databaseFile, string $namespace, string $key, array $value): void
{
    $db = sqliteConnection($databaseFile);
    $stmt = $db->prepare('
        INSERT INTO app_state (namespace, key, value, updated_at)
        VALUES (:namespace, :key, :value, :updated_at)
        ON CONFLICT(namespace, key) DO UPDATE SET
            value = excluded.value,
            updated_at = excluded.updated_at
    ');
    $stmt->execute([
        ':namespace' => $namespace,
        ':key' => $key,
        ':value' => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ':updated_at' => date('Y-m-d H:i:s'),
    ]);
}

function sqliteUpdateJsonState(string $databaseFile, string $namespace, string $key, callable $callback): mixed
{
    $db = sqliteConnection($databaseFile);
    $db->exec('BEGIN IMMEDIATE');

    try {
        $state = sqliteReadJsonState($databaseFile, $namespace, $key, []);
        $result = $callback($state, true);
        sqliteWriteJsonState($databaseFile, $namespace, $key, $state);
        $db->commit();

        return $result;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}

function sha256File(string $path): string
{
    return is_file($path) ? (string) hash_file('sha256', $path) : '';
}

function ensureBackupDirectory(string $backupDir): void
{
    if (!is_dir($backupDir) && !@mkdir($backupDir, 0750, true) && !is_dir($backupDir)) {
        throw new RuntimeException('تعذر إنشاء مجلد النسخ الاحتياطي: ' . $backupDir);
    }

    @chmod($backupDir, 0750);
}

function sqliteBackupToFile(string $databaseFile, string $backupFile): void
{
    $db = sqliteConnection($databaseFile);
    $db->exec('PRAGMA wal_checkpoint(TRUNCATE)');

    try {
        $db->exec('VACUUM INTO ' . $db->quote($backupFile));
    } catch (Throwable) {
        if (!@copy($databaseFile, $backupFile)) {
            throw new RuntimeException('تعذر نسخ قاعدة SQLite إلى النسخة الاحتياطية.');
        }
    }

    @chmod($backupFile, 0640);
}

function removeSqliteSidecars(string $databaseFile): void
{
    foreach ([$databaseFile . '-wal', $databaseFile . '-shm'] as $sidecar) {
        if (is_file($sidecar)) {
            @unlink($sidecar);
        }
    }
}

function restoreBackupFile(string $source, string $destination, int $mode): void
{
    $temporary = $destination . '.restore_tmp_' . bin2hex(random_bytes(3));

    if (!@copy($source, $temporary)) {
        throw new RuntimeException('تعذر تجهيز ملف الاستعادة: ' . basename($destination));
    }

    @chmod($temporary, $mode);

    if (!@rename($temporary, $destination)) {
        @unlink($temporary);
        throw new RuntimeException('تعذر استبدال ملف الاستعادة: ' . basename($destination));
    }
}

function purgeOldOperationalBackups(string $backupDir, int $retentionDays): array
{
    if ($retentionDays <= 0 || !is_dir($backupDir)) {
        return [];
    }

    $cutoff = time() - ($retentionDays * 86400);
    $removed = [];

    foreach (['ip_feed_*.sqlite', 'ips_*.txt', 'backup_*.json'] as $pattern) {
        foreach (glob(rtrim($backupDir, '/\\') . '/' . $pattern) ?: [] as $path) {
            $mtime = filemtime($path) ?: time();

            if ($mtime >= $cutoff) {
                continue;
            }

            if (@unlink($path)) {
                $removed[] = basename($path);
            }
        }
    }

    return $removed;
}

function createOperationalBackup(string $databaseFile, string $ipsFile, string $backupDir, int $retentionDays = 14, string $type = 'manual'): array
{
    ensureBackupDirectory($backupDir);

    if (!is_file($databaseFile)) {
        throw new RuntimeException('قاعدة SQLite غير موجودة: ' . $databaseFile);
    }

    if (!is_file($ipsFile)) {
        throw new RuntimeException('ملف ips.txt غير موجود: ' . $ipsFile);
    }

    $runId = date('Ymd_His') . '_' . bin2hex(random_bytes(3));
    if ($type !== 'manual') {
        $runId = preg_replace('/[^A-Za-z0-9_-]/', '_', $type) . '_' . $runId;
    }

    $backupDir = rtrim($backupDir, '/\\');
    $databaseBackup = $backupDir . '/ip_feed_' . $runId . '.sqlite';
    $feedBackup = $backupDir . '/ips_' . $runId . '.txt';
    $manifestFile = $backupDir . '/backup_' . $runId . '.json';

    sqliteBackupToFile($databaseFile, $databaseBackup);

    if (!@copy($ipsFile, $feedBackup)) {
        throw new RuntimeException('تعذر نسخ ips.txt إلى النسخة الاحتياطية.');
    }
    @chmod($feedBackup, 0640);

    $manifest = [
        'ok' => true,
        'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'type' => $type,
        'schema_version' => sqliteSchemaVersion($databaseFile),
        'database' => [
            'path' => $databaseBackup,
            'size_bytes' => filesize($databaseBackup) ?: 0,
            'sha256' => sha256File($databaseBackup),
        ],
        'feed' => [
            'path' => $feedBackup,
            'size_bytes' => filesize($feedBackup) ?: 0,
            'sha256' => sha256File($feedBackup),
        ],
        'removed_old_files' => purgeOldOperationalBackups($backupDir, $retentionDays),
    ];

    file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL, LOCK_EX);
    @chmod($manifestFile, 0640);

    return [
        'ok' => true,
        'manifest' => basename($manifestFile),
        'manifest_path' => $manifestFile,
        'database_backup' => basename($databaseBackup),
        'feed_backup' => basename($feedBackup),
        'removed_old_files' => count($manifest['removed_old_files']),
    ];
}

function listOperationalBackups(string $backupDir, int $limit = 10): array
{
    if (!is_dir($backupDir)) {
        return [];
    }

    $files = glob(rtrim($backupDir, '/\\') . '/backup_*.json') ?: [];
    usort($files, static fn (string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
    $rows = [];

    foreach (array_slice($files, 0, max(1, $limit)) as $file) {
        $json = file_get_contents($file);
        $manifest = $json !== false ? json_decode($json, true) : [];

        if (!is_array($manifest)) {
            $manifest = [];
        }

        $rows[] = [
            'manifest' => basename($file),
            'created_at' => (string) ($manifest['created_at'] ?? date('Y-m-d H:i:s', filemtime($file) ?: time())),
            'type' => (string) ($manifest['type'] ?? 'manual'),
            'database_size' => (int) ($manifest['database']['size_bytes'] ?? 0),
            'feed_size' => (int) ($manifest['feed']['size_bytes'] ?? 0),
            'schema_version' => (int) ($manifest['schema_version']['version'] ?? 0),
        ];
    }

    return $rows;
}

function resolveBackupPath(string $backupDir, string $path): string
{
    $backupDirReal = realpath($backupDir);
    $pathReal = realpath($path);

    if ($backupDirReal === false || $pathReal === false || !str_starts_with($pathReal, rtrim($backupDirReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('مسار النسخة الاحتياطية غير صالح.');
    }

    return $pathReal;
}

function restoreOperationalBackup(string $databaseFile, string $ipsFile, string $backupDir, string $manifestName): array
{
    ensureBackupDirectory($backupDir);

    if (!preg_match('/^backup_[A-Za-z0-9_-]+\.json$/', $manifestName)) {
        throw new RuntimeException('اسم ملف النسخة الاحتياطية غير صالح.');
    }

    $manifestFile = resolveBackupPath($backupDir, rtrim($backupDir, '/\\') . '/' . $manifestName);
    $json = file_get_contents($manifestFile);
    $manifest = $json !== false ? json_decode($json, true) : [];

    if (!is_array($manifest) || empty($manifest['database']['path']) || empty($manifest['feed']['path'])) {
        throw new RuntimeException('ملف manifest غير صالح.');
    }

    $databaseBackup = resolveBackupPath($backupDir, (string) $manifest['database']['path']);
    $feedBackup = resolveBackupPath($backupDir, (string) $manifest['feed']['path']);

    if (sha256File($databaseBackup) !== (string) ($manifest['database']['sha256'] ?? '')) {
        throw new RuntimeException('فشل تحقق SHA256 لنسخة SQLite.');
    }

    if (sha256File($feedBackup) !== (string) ($manifest['feed']['sha256'] ?? '')) {
        throw new RuntimeException('فشل تحقق SHA256 لنسخة ips.txt.');
    }

    $restoreDb = new PDO('sqlite:' . $databaseBackup);
    $integrity = (string) $restoreDb->query('PRAGMA integrity_check')->fetchColumn();
    $restoreDb = null;

    if ($integrity !== 'ok') {
        throw new RuntimeException('نسخة SQLite الاحتياطية غير سليمة.');
    }

    $preRestore = createOperationalBackup($databaseFile, $ipsFile, $backupDir, 0, 'pre_restore');
    $activeDb = sqliteConnection($databaseFile);
    $activeDb->exec('PRAGMA wal_checkpoint(TRUNCATE)');
    $activeDb = null;
    sqliteCloseConnection($databaseFile);
    removeSqliteSidecars($databaseFile);

    restoreBackupFile($databaseBackup, $databaseFile, 0640);
    removeSqliteSidecars($databaseFile);
    restoreBackupFile($feedBackup, $ipsFile, 0644);

    $restoredDb = new PDO('sqlite:' . $databaseFile);
    $restoredDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $postIntegrity = (string) $restoredDb->query('PRAGMA integrity_check')->fetchColumn();
    $restoredDb = null;

    if ($postIntegrity !== 'ok') {
        throw new RuntimeException('فشل فحص SQLite بعد الاستعادة.');
    }

    return [
        'ok' => true,
        'manifest' => $manifestName,
        'pre_restore_manifest' => (string) ($preRestore['manifest'] ?? ''),
    ];
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

function migrateOperationalJsonToSqlite(string $databaseFile, array $legacyFiles): void
{
    ensureSqliteDatabase($databaseFile);

    $jsonStateMappings = [
        'vt_settings' => ['virustotal', 'settings'],
        'vt_rate_limit' => ['virustotal', 'rate_limit'],
        'login_rate_limit' => ['auth', 'login_attempts'],
    ];

    foreach ($jsonStateMappings as $legacyKey => [$namespace, $key]) {
        $file = (string) ($legacyFiles[$legacyKey] ?? '');

        if ($file === '' || !file_exists($file) || sqliteReadJsonState($databaseFile, $namespace, $key, []) !== []) {
            continue;
        }

        $json = file_get_contents($file);
        $data = $json !== false && trim($json) !== '' ? json_decode($json, true) : [];

        if (is_array($data)) {
            sqliteWriteJsonState($databaseFile, $namespace, $key, $data);
        }
    }

    $visitorGeoCacheFile = (string) ($legacyFiles['visitor_geo_cache'] ?? '');
    if ($visitorGeoCacheFile !== '' && file_exists($visitorGeoCacheFile)) {
        $json = file_get_contents($visitorGeoCacheFile);
        $data = $json !== false && trim($json) !== '' ? json_decode($json, true) : [];

        if (is_array($data)) {
            $cache = readGeoCache($databaseFile);
            $changed = false;

            foreach ($data as $ip => $entry) {
                if (!is_array($entry) || isset($cache[(string) $ip])) {
                    continue;
                }

                $cache[(string) $ip] = [
                    'country' => (string) ($entry['country'] ?? 'Unknown'),
                    'country_code' => (string) ($entry['country_code'] ?? ''),
                    'city' => (string) ($entry['city'] ?? 'Unknown'),
                    'isp' => (string) ($entry['isp'] ?? 'Unknown'),
                    'updated_at' => (string) ($entry['updated_at'] ?? date('Y-m-d H:i:s')),
                ];
                $changed = true;
            }

            if ($changed) {
                saveGeoCache($databaseFile, $cache);
            }
        }
    }
}
