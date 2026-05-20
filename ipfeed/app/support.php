<?php
declare(strict_types=1);

if (!defined('IP_FEED_APP')) {
    http_response_code(403);
    exit;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function secondsToHumanArabic(int $seconds): string
{
    $seconds = max(0, $seconds);

    if ($seconds < 60) {
        return $seconds . ' ثانية';
    }

    $minutes = intdiv($seconds, 60);
    $remainingSeconds = $seconds % 60;

    if ($minutes < 60) {
        return $remainingSeconds > 0
            ? $minutes . ' دقيقة و ' . $remainingSeconds . ' ثانية'
            : $minutes . ' دقيقة';
    }

    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;

    return $remainingMinutes > 0
        ? $hours . ' ساعة و ' . $remainingMinutes . ' دقيقة'
        : $hours . ' ساعة';
}

function jsonResponse(array $payload): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function allowedAppPage(string $page): string
{
    $page = strtolower(trim($page));

    return in_array($page, ['dashboard', 'ips', 'logs', 'users', 'settings', 'health'], true) ? $page : 'dashboard';
}

function appPageLabel(string $page): string
{
    return match ($page) {
        'ips' => 'IPs',
        'logs' => 'Logs',
        'users' => 'Users',
        'settings' => 'Settings',
        'health' => 'Health',
        default => 'Dashboard',
    };
}

function iconSvg(string $name): string
{
    $path = match ($name) {
        'dashboard' => '<path d="M4 13h6V4H4v9Z"/><path d="M14 20h6v-9h-6v9Z"/><path d="M4 20h6v-3H4v3Z"/><path d="M14 7h6V4h-6v3Z"/>',
        'ips' => '<path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/><path d="M8 7v10"/><path d="M16 7v10"/>',
        'logs' => '<path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'settings' => '<path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"/><path d="M19.4 15a1.8 1.8 0 0 0 .36 1.98l.04.04a2 2 0 1 1-2.83 2.83l-.04-.04A1.8 1.8 0 0 0 15 19.4a1.8 1.8 0 0 0-1 .6V20a2 2 0 1 1-4 0v-.06a1.8 1.8 0 0 0-1-.6 1.8 1.8 0 0 0-1.98.36l-.04.04a2 2 0 1 1-2.83-2.83l.04-.04A1.8 1.8 0 0 0 4.6 15a1.8 1.8 0 0 0-.6-1H4a2 2 0 1 1 0-4h.06a1.8 1.8 0 0 0 .6-1 1.8 1.8 0 0 0-.36-1.98l-.04-.04a2 2 0 1 1 2.83-2.83l.04.04A1.8 1.8 0 0 0 9 4.6a1.8 1.8 0 0 0 1-.6V4a2 2 0 1 1 4 0v.06a1.8 1.8 0 0 0 1 .6 1.8 1.8 0 0 0 1.98-.36l.04-.04a2 2 0 1 1 2.83 2.83l-.04.04A1.8 1.8 0 0 0 19.4 9c.22.34.42.66.6 1H20a2 2 0 1 1 0 4h-.06a1.8 1.8 0 0 0-.54 1Z"/>',
        'health' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'feed' => '<path d="M4 4h16v16H4z"/><path d="M8 8h8"/><path d="M8 12h8"/><path d="M8 16h5"/>',
        'login' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'filter' => '<path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3Z"/>',
        'clear' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'save' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/>',
        'trash' => '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/>',
        'scan' => '<path d="M4 7V4h3"/><path d="M17 4h3v3"/><path d="M20 17v3h-3"/><path d="M7 20H4v-3"/><path d="M7 12h10"/>',
        'download' => '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/>',
        'add' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'check' => '<path d="m20 6-11 11-5-5"/>',
        'warning' => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        default => '<circle cx="12" cy="12" r="9"/>',
    };

    return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $path . '</svg>';
}

function healthBadgeClass(string $status): string
{
    return match ($status) {
        'ok' => 'badge-vt-success',
        'warning' => 'badge-vt-warning',
        'error' => 'badge-vt-danger',
        default => 'badge-vt-muted',
    };
}

function healthStatusLabel(string $status): string
{
    return match ($status) {
        'ok' => 'سليم',
        'warning' => 'تنبيه',
        'error' => 'خطأ',
        default => 'غير معروف',
    };
}

function filePermissionSummary(string $path): string
{
    if (!file_exists($path)) {
        return 'غير موجود';
    }

    $perms = @fileperms($path);
    $mode = $perms === false ? '----' : substr(sprintf('%o', $perms), -4);
    $flags = [];

    if (is_readable($path)) {
        $flags[] = 'readable';
    }

    if (is_writable($path)) {
        $flags[] = 'writable';
    }

    return $mode . ' · ' . implode(', ', $flags);
}

function isMonitoringHealthCheckRequest(): bool
{
    return isset($_GET['healthcheck']) || isset($_GET['health']);
}

function monitoringHealthRequestToken(): string
{
    $headerToken = trim((string) ($_SERVER['HTTP_X_IPFEED_HEALTH_TOKEN'] ?? ''));

    if ($headerToken !== '') {
        return $headerToken;
    }

    $authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if (str_starts_with($authorization, 'Bearer ')) {
        return trim(substr($authorization, 7));
    }

    return trim((string) ($_GET['token'] ?? ''));
}

function latestBackupSnapshot(string $backupDir, int $maxAgeHours): array
{
    $maxAgeHours = max(1, $maxAgeHours);

    if (!is_dir($backupDir)) {
        return [
            'status' => 'warning',
            'detail' => 'لم يتم إنشاء مجلد النسخ الاحتياطي بعد.',
            'age_seconds' => null,
            'latest_at' => '',
        ];
    }

    $manifestFiles = glob(rtrim($backupDir, '/\\') . '/backup_*.json') ?: [];
    usort($manifestFiles, static fn (string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));

    if (empty($manifestFiles)) {
        return [
            'status' => 'warning',
            'detail' => 'لا توجد نسخة احتياطية مسجلة.',
            'age_seconds' => null,
            'latest_at' => '',
        ];
    }

    $latest = $manifestFiles[0];
    $mtime = filemtime($latest) ?: 0;
    $ageSeconds = $mtime > 0 ? max(0, time() - $mtime) : null;
    $status = $ageSeconds !== null && $ageSeconds <= ($maxAgeHours * 3600) ? 'ok' : 'warning';
    $latestAt = $mtime > 0 ? date('Y-m-d H:i:s', $mtime) : '';
    $detail = $status === 'ok'
        ? 'آخر نسخة احتياطية: ' . $latestAt
        : 'آخر نسخة احتياطية قديمة أو غير معروفة: ' . ($latestAt !== '' ? $latestAt : 'لا يوجد');

    return [
        'status' => $status,
        'detail' => $detail,
        'age_seconds' => $ageSeconds,
        'latest_at' => $latestAt,
    ];
}

function renderMonitoringHealthCheck(array $options): void
{
    $enabled = (bool) ($options['enabled'] ?? true);

    if (!$enabled) {
        http_response_code(404);
        jsonResponse([
            'ok' => false,
            'status' => 'disabled',
            'service' => 'ipfeed',
            'checked_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }

    $configuredToken = trim((string) ($options['token'] ?? ''));

    if ($configuredToken !== '' && !hash_equals($configuredToken, monitoringHealthRequestToken())) {
        http_response_code(403);
        jsonResponse([
            'ok' => false,
            'status' => 'unauthorized',
            'service' => 'ipfeed',
            'checked_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }

    $payload = buildMonitoringHealthPayload($options);

    if (!($payload['ok'] ?? false)) {
        $appLogFile = (string) ($options['app_log_file'] ?? '');
        if ($appLogFile !== '') {
            \IpFeed\Services\AppLogger::warning($appLogFile, 'healthcheck_not_ok', [
                'status' => (string) ($payload['status'] ?? 'unknown'),
            ]);
        }

        http_response_code(503);
    }

    jsonResponse($payload);
}

function buildMonitoringHealthPayload(array $options): array
{
    $databaseFile = (string) ($options['database_file'] ?? '');
    $ipsFile = (string) ($options['ips_file'] ?? '');
    $settingsDir = (string) ($options['settings_dir'] ?? '');
    $appLogFile = (string) ($options['app_log_file'] ?? '');
    $backupDir = (string) ($options['backup_dir'] ?? '');
    $backupMaxAgeHours = max(1, (int) ($options['backup_max_age_hours'] ?? 30));
    $vtApiConfigured = (bool) ($options['vt_api_configured'] ?? false);
    $failOnWarning = (bool) ($options['fail_on_warning'] ?? false);
    $includeDetails = (bool) ($options['include_details'] ?? false);
    $checks = [];
    $metrics = [];

    $addCheck = static function (string $key, string $status, string $message, array $details = []) use (&$checks, $includeDetails): void {
        $check = [
            'status' => $status,
            'message' => $message,
        ];

        if ($includeDetails) {
            $check['details'] = $details;
        }

        $checks[$key] = $check;
    };

    $feedOk = $ipsFile !== '' && is_file($ipsFile) && is_readable($ipsFile) && is_writable($ipsFile);
    $addCheck('feed_file', $feedOk ? 'ok' : 'error', $feedOk ? 'ips.txt جاهز للقراءة والكتابة.' : 'ips.txt غير جاهز للقراءة والكتابة.');

    if ($feedOk) {
        $lines = file($ipsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $metrics['feed_ips'] = is_array($lines) ? count($lines) : 0;
    }

    $settingsOk = $settingsDir !== '' && is_dir($settingsDir) && is_readable($settingsDir) && is_writable($settingsDir);
    $addCheck('private_storage', $settingsOk ? 'ok' : 'error', $settingsOk ? 'مجلد التشغيل الخاص جاهز.' : 'مجلد التشغيل الخاص غير جاهز.');

    $logDir = $appLogFile !== '' ? dirname($appLogFile) : '';
    $logOk = $logDir !== '' && is_dir($logDir) && is_writable($logDir) && (file_exists($appLogFile) ? is_writable($appLogFile) : is_writable($logDir));
    $addCheck('app_logs', $logOk ? 'ok' : 'warning', $logOk ? 'سجل التطبيق قابل للكتابة.' : 'سجل التطبيق غير جاهز للكتابة.');

    $backupSnapshot = latestBackupSnapshot($backupDir, $backupMaxAgeHours);
    $addCheck('backup', (string) $backupSnapshot['status'], (string) $backupSnapshot['detail']);
    if (isset($backupSnapshot['age_seconds'])) {
        $metrics['last_backup_age_seconds'] = $backupSnapshot['age_seconds'];
    }

    if (!extension_loaded('pdo_sqlite')) {
        $addCheck('sqlite', 'error', 'امتداد pdo_sqlite غير مفعل.');
    } elseif ($databaseFile === '' || !is_file($databaseFile) || !is_readable($databaseFile)) {
        $addCheck('sqlite', 'error', 'قاعدة SQLite غير موجودة أو غير قابلة للقراءة.');
    } else {
        try {
            $db = new PDO('sqlite:' . $databaseFile);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $integrity = (string) $db->query('PRAGMA integrity_check')->fetchColumn();

            if ($integrity === 'ok') {
                $addCheck('sqlite', 'ok', 'SQLite integrity_check = ok.');
            } else {
                $addCheck('sqlite', 'error', 'فشل فحص سلامة SQLite.');
            }

            $metrics['logs'] = (int) $db->query('SELECT COUNT(*) FROM logs')->fetchColumn();
            $metrics['users'] = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
            $schemaRow = $db->query('SELECT version, migration FROM schema_version WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
            if (is_array($schemaRow)) {
                $metrics['schema_version'] = (int) ($schemaRow['version'] ?? 0);
                $metrics['schema_migration'] = (string) ($schemaRow['migration'] ?? '');
            }
            $metrics['app_state_rows'] = (int) $db->query('SELECT COUNT(*) FROM app_state')->fetchColumn();

            $queueRows = $db->query('SELECT status, COUNT(*) AS total FROM vt_queue GROUP BY status')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($queueRows ?: [] as $row) {
                $metrics['vt_queue_' . (string) ($row['status'] ?? 'unknown')] = (int) ($row['total'] ?? 0);
            }
        } catch (Throwable $exception) {
            $addCheck('sqlite', 'error', 'تعذر فحص SQLite.');
            $metrics['sqlite_error'] = $includeDetails ? $exception->getMessage() : 'hidden';
        }
    }

    $addCheck('virustotal_key', $vtApiConfigured ? 'ok' : 'warning', $vtApiConfigured ? 'مفتاح VirusTotal مضبوط.' : 'مفتاح VirusTotal غير مضبوط.');

    $statuses = array_map(static fn (array $check): string => (string) ($check['status'] ?? 'unknown'), $checks);
    $hasError = in_array('error', $statuses, true);
    $hasWarning = in_array('warning', $statuses, true);
    $status = $hasError ? 'error' : ($hasWarning ? 'warning' : 'ok');
    $ok = !$hasError && (!$failOnWarning || !$hasWarning);

    return [
        'ok' => $ok,
        'status' => $status,
        'service' => 'ipfeed',
        'checked_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'checks' => $checks,
        'metrics' => $metrics,
    ];
}
