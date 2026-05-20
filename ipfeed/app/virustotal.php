<?php
declare(strict_types=1);

if (!defined('IP_FEED_APP')) {
    http_response_code(403);
    exit;
}

function maskSecret(string $secret): string
{
    $secret = trim($secret);

    if ($secret === '') {
        return 'غير مضبوط';
    }

    return str_repeat('•', min(16, max(8, strlen($secret) - 6))) . substr($secret, -6);
}

function isLikelyVirusTotalApiKey(string $apiKey): bool
{
    $apiKey = trim($apiKey);

    return preg_match('/^[A-Za-z0-9_-]{32,128}$/', $apiKey) === 1;
}

function ensurePrivateSettingsDir(string $settingsDir): string
{
    if ($settingsDir === '') {
        return 'مسار مجلد الإعدادات الخاصة غير محدد.';
    }

    if (!is_dir($settingsDir)) {
        if (!@mkdir($settingsDir, 0750, true) && !is_dir($settingsDir)) {
            return 'تعذر إنشاء مجلد الإعدادات الخاصة: ' . $settingsDir;
        }
    }

    if (!is_writable($settingsDir)) {
        return 'مجلد الإعدادات الخاصة غير قابل للكتابة: ' . $settingsDir;
    }

    $htaccess = rtrim($settingsDir, '/\\') . '/.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Require all denied
Deny from all
", LOCK_EX);
    }

    $indexFile = rtrim($settingsDir, '/\\') . '/index.html';
    if (!file_exists($indexFile)) {
        @file_put_contents($indexFile, '', LOCK_EX);
    }

    return '';
}

function virusTotalSettingsStorageError(string $settingsDir, string $settingsFile): string
{
    if (isSqliteStorage($settingsFile)) {
        return databaseStorageError($settingsFile);
    }

    $dirIssue = ensurePrivateSettingsDir($settingsDir);

    if ($dirIssue !== '') {
        return $dirIssue;
    }

    if (file_exists($settingsFile) && !is_writable($settingsFile)) {
        return 'ملف إعدادات VirusTotal غير قابل للكتابة: ' . $settingsFile;
    }

    return '';
}

function readVirusTotalSettings(string $settingsFile): array
{
    if (isSqliteStorage($settingsFile)) {
        return sqliteReadJsonState($settingsFile, 'virustotal', 'settings', []);
    }

    if (!file_exists($settingsFile)) {
        return [];
    }

    $json = file_get_contents($settingsFile);

    if ($json === false || trim($json) === '') {
        return [];
    }

    $data = json_decode($json, true);

    return is_array($data) ? $data : [];
}

function saveVirusTotalSettings(string $settingsFile, array $settings): void
{
    $settings['updated_at'] = $settings['updated_at'] ?? date('Y-m-d H:i:s');

    if (isSqliteStorage($settingsFile)) {
        sqliteWriteJsonState($settingsFile, 'virustotal', 'settings', $settings);
        return;
    }

    file_put_contents(
        $settingsFile,
        json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );

    @chmod($settingsFile, 0640);
}

function resolveVirusTotalConfig(string $settingsFile, string $envApiKey): array
{
    $settings = readVirusTotalSettings($settingsFile);
    $savedKey = trim((string) ($settings['api_key'] ?? ''));
    $envApiKey = trim($envApiKey);

    if ($savedKey !== '') {
        return [
            'api_key' => $savedKey,
            'source' => isSqliteStorage($settingsFile) ? 'sqlite' : 'admin_file',
            'source_label' => isSqliteStorage($settingsFile) ? 'SQLite' : 'لوحة المدير',
            'masked' => maskSecret($savedKey),
            'has_saved_key' => true,
            'has_env_key' => $envApiKey !== '',
            'settings' => $settings,
        ];
    }

    if ($envApiKey !== '') {
        return [
            'api_key' => $envApiKey,
            'source' => 'env',
            'source_label' => 'متغير البيئة VT_API_KEY',
            'masked' => maskSecret($envApiKey),
            'has_saved_key' => false,
            'has_env_key' => true,
            'settings' => $settings,
        ];
    }

    return [
        'api_key' => '',
        'source' => 'none',
        'source_label' => 'غير مضبوط',
        'masked' => 'غير مضبوط',
        'has_saved_key' => false,
        'has_env_key' => false,
        'settings' => $settings,
    ];
}

function auditVirusTotalSettings(string $logFile, string $action, string $actorUsername, string $reason): void
{
    addLog($logFile, [
        'action' => $action,
        'ip' => '-',
        'country' => '-',
        'city' => '-',
        'isp' => '-',
        'reason' => $reason,
        'user' => $actorUsername,
        'time' => date('Y-m-d H:i:s'),
        'source_ip' => getRequestClientIp(),
        'vt_status' => '-',
        'vt_malicious' => 0,
        'vt_suspicious' => 0,
        'vt_harmless' => 0,
        'vt_undetected' => 0,
        'vt_timeout' => 0,
        'vt_total' => 0,
        'vt_reputation' => 0,
        'vt_asn' => 0,
        'vt_as_owner' => '',
        'vt_last_analysis_date' => '',
        'vt_link' => '',
        'vt_error' => '',
    ]);
}

function acquireVirusTotalQuotaSlot(): array
{
    global $vtRateLimitFile, $vtDailyQuota, $vtMinIntervalSeconds, $vtMaxServerWaitSeconds;

    $now = time();
    $todayUtc = gmdate('Y-m-d', $now);
    $tomorrowUtc = strtotime($todayUtc . ' 00:00:00 UTC +1 day');

    if (isSqliteStorage($vtRateLimitFile)) {
        try {
            $result = sqliteUpdateJsonState($vtRateLimitFile, 'virustotal', 'rate_limit', function (array &$state, bool $persistent) use ($now, $todayUtc, $tomorrowUtc, $vtDailyQuota, $vtMinIntervalSeconds, $vtMaxServerWaitSeconds): array {
                if (!is_array($state) || (($state['day_utc'] ?? '') !== $todayUtc)) {
                    $state = [
                        'day_utc' => $todayUtc,
                        'daily_count' => 0,
                        'next_allowed_at' => 0,
                        'last_request_at' => 0,
                    ];
                }

                $dailyCount = max(0, (int) ($state['daily_count'] ?? 0));

                if ($dailyCount >= $vtDailyQuota) {
                    $waitSeconds = is_int($tomorrowUtc) ? max(0, $tomorrowUtc - $now) : 86400;

                    return [
                        'allowed' => false,
                        'wait_seconds' => $waitSeconds,
                        'message' => 'تم بلوغ حد VirusTotal اليومي لهذا المفتاح (' . $vtDailyQuota . ' طلب/يوم). أعد المحاولة بعد ' . secondsToHumanArabic($waitSeconds) . ' أو استخدم اشتراكاً مناسباً.',
                    ];
                }

                $nextAllowedAt = max(0, (int) ($state['next_allowed_at'] ?? 0));
                $scheduledAt = max($now, $nextAllowedAt);
                $waitSeconds = max(0, $scheduledAt - $now);

                if ($waitSeconds > $vtMaxServerWaitSeconds) {
                    return [
                        'allowed' => false,
                        'wait_seconds' => $waitSeconds,
                        'message' => 'تم تأجيل الفحص لتجنب خطأ 429. أعد المحاولة بعد ' . secondsToHumanArabic($waitSeconds) . '.',
                    ];
                }

                $state['day_utc'] = $todayUtc;
                $state['daily_count'] = $dailyCount + 1;
                $state['last_request_at'] = $now;
                $state['next_allowed_at'] = $scheduledAt + max(1, (int) $vtMinIntervalSeconds);
                $state['updated_at'] = gmdate('Y-m-d H:i:s') . ' UTC';

                return [
                    'allowed' => true,
                    'wait_seconds' => $waitSeconds,
                    'message' => $persistent ? '' : 'تعذر حفظ حالة حد VirusTotal.',
                    'daily_remaining' => max(0, $vtDailyQuota - ($dailyCount + 1)),
                ];
            });

            if (($result['allowed'] ?? false) && (int) ($result['wait_seconds'] ?? 0) > 0) {
                sleep((int) $result['wait_seconds']);
            }

            return $result;
        } catch (Throwable $exception) {
            return [
                'allowed' => false,
                'wait_seconds' => 0,
                'message' => 'تعذر تحديث حد VirusTotal في SQLite: ' . $exception->getMessage(),
            ];
        }
    }

    if (!is_dir(dirname($vtRateLimitFile))) {
        return [
            'allowed' => false,
            'wait_seconds' => 0,
            'message' => 'مجلد ملف حد VirusTotal غير موجود: ' . dirname($vtRateLimitFile),
        ];
    }

    $fp = @fopen($vtRateLimitFile, 'c+');

    if (!$fp) {
        return [
            'allowed' => false,
            'wait_seconds' => 0,
            'message' => 'تعذر فتح ملف حد VirusTotal: ' . $vtRateLimitFile,
        ];
    }

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return [
            'allowed' => false,
            'wait_seconds' => 0,
            'message' => 'تعذر قفل ملف حد VirusTotal مؤقتاً.',
        ];
    }

    rewind($fp);
    $raw = stream_get_contents($fp);
    $state = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : [];

    if (!is_array($state) || (($state['day_utc'] ?? '') !== $todayUtc)) {
        $state = [
            'day_utc' => $todayUtc,
            'daily_count' => 0,
            'next_allowed_at' => 0,
            'last_request_at' => 0,
        ];
    }

    $dailyCount = max(0, (int) ($state['daily_count'] ?? 0));

    if ($dailyCount >= $vtDailyQuota) {
        $waitSeconds = is_int($tomorrowUtc) ? max(0, $tomorrowUtc - $now) : 86400;
        flock($fp, LOCK_UN);
        fclose($fp);

        return [
            'allowed' => false,
            'wait_seconds' => $waitSeconds,
            'message' => 'تم بلوغ حد VirusTotal اليومي لهذا المفتاح (' . $vtDailyQuota . ' طلب/يوم). أعد المحاولة بعد ' . secondsToHumanArabic($waitSeconds) . ' أو استخدم اشتراكاً مناسباً.',
        ];
    }

    $nextAllowedAt = max(0, (int) ($state['next_allowed_at'] ?? 0));
    $scheduledAt = max($now, $nextAllowedAt);
    $waitSeconds = max(0, $scheduledAt - $now);

    if ($waitSeconds > $vtMaxServerWaitSeconds) {
        flock($fp, LOCK_UN);
        fclose($fp);

        return [
            'allowed' => false,
            'wait_seconds' => $waitSeconds,
            'message' => 'تم تأجيل الفحص لتجنب خطأ 429. أعد المحاولة بعد ' . secondsToHumanArabic($waitSeconds) . '.',
        ];
    }

    $state['day_utc'] = $todayUtc;
    $state['daily_count'] = $dailyCount + 1;
    $state['last_request_at'] = $now;
    $state['next_allowed_at'] = $scheduledAt + max(1, (int) $vtMinIntervalSeconds);
    $state['updated_at'] = gmdate('Y-m-d H:i:s') . ' UTC';

    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    if ($waitSeconds > 0) {
        sleep($waitSeconds);
    }

    return [
        'allowed' => true,
        'wait_seconds' => $waitSeconds,
        'message' => '',
        'daily_remaining' => max(0, $vtDailyQuota - ($dailyCount + 1)),
    ];
}

function virusTotalQuotaSnapshot(): array
{
    global $vtRateLimitFile, $vtDailyQuota, $vtMinIntervalSeconds;

    $now = time();
    $todayUtc = gmdate('Y-m-d', $now);
    $state = [];

    if (isSqliteStorage($vtRateLimitFile)) {
        try {
            $state = sqliteReadJsonState($vtRateLimitFile, 'virustotal', 'rate_limit', []);
        } catch (Throwable) {
            $state = [];
        }
    } elseif (file_exists($vtRateLimitFile)) {
        $raw = file_get_contents($vtRateLimitFile);
        $decoded = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : [];
        $state = is_array($decoded) ? $decoded : [];
    }

    if (($state['day_utc'] ?? '') !== $todayUtc) {
        $state = [
            'day_utc' => $todayUtc,
            'daily_count' => 0,
            'next_allowed_at' => 0,
            'last_request_at' => 0,
        ];
    }

    $dailyCount = max(0, (int) ($state['daily_count'] ?? 0));
    $nextAllowedAt = max(0, (int) ($state['next_allowed_at'] ?? 0));
    $waitSeconds = max(0, $nextAllowedAt - $now);

    return [
        'daily_count' => $dailyCount,
        'daily_remaining' => max(0, $vtDailyQuota - $dailyCount),
        'daily_quota' => $vtDailyQuota,
        'wait_seconds' => $waitSeconds,
        'min_interval_seconds' => $vtMinIntervalSeconds,
    ];
}

function defaultVirusTotalInfo(string $ip, string $status = 'غير مفعل', string $error = ''): array
{
    return [
        'status' => $status,
        'malicious' => 0,
        'suspicious' => 0,
        'harmless' => 0,
        'undetected' => 0,
        'timeout' => 0,
        'total' => 0,
        'reputation' => 0,
        'asn' => 0,
        'as_owner' => '',
        'last_analysis_date' => '',
        'link' => 'https://www.virustotal.com/gui/ip-address/' . rawurlencode($ip),
        'error' => $error,
    ];
}

function getVirusTotalInfo(string $ip, string $apiKey): array
{
    if ($apiKey === '') {
        return defaultVirusTotalInfo($ip, 'غير مفعل', 'مفتاح VirusTotal غير مضبوط. أضفه من لوحة المدير أو متغير البيئة VT_API_KEY');
    }

    $quota = acquireVirusTotalQuotaSlot();

    if (!($quota['allowed'] ?? false)) {
        return defaultVirusTotalInfo($ip, 'مؤجل', (string) ($quota['message'] ?? 'تم تأجيل الفحص لتجنب تجاوز حدود VirusTotal.'));
    }

    $url = 'https://www.virustotal.com/api/v3/ip_addresses/' . rawurlencode($ip);
    $response = false;
    $statusCode = 0;
    $transportError = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'x-apikey: ' . $apiKey,
            ],
            CURLOPT_USERAGENT => 'IP-Feed-Manager/1.0',
        ]);

        $response = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $transportError = (string) curl_error($ch);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 12,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\n" .
                            "x-apikey: " . $apiKey . "\r\n" .
                            "User-Agent: IP-Feed-Manager/1.0\r\n",
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
            $statusCode = (int) $matches[1];
        }
    }

    if ($response === false || $response === '') {
        return defaultVirusTotalInfo($ip, 'غير معروف', $transportError !== '' ? $transportError : 'تعذر الاتصال بـ VirusTotal');
    }

    $data = json_decode((string) $response, true);

    if (!is_array($data)) {
        return defaultVirusTotalInfo($ip, 'غير معروف', 'استجابة VirusTotal ليست JSON صالحة');
    }

    if ($statusCode < 200 || $statusCode >= 300) {
        $apiMessage = (string) ($data['error']['message'] ?? $data['error']['code'] ?? '');
        $message = 'خطأ VirusTotal HTTP ' . $statusCode;

        if ($apiMessage !== '') {
            $message .= ': ' . $apiMessage;
        }

        return defaultVirusTotalInfo($ip, 'غير معروف', $message);
    }

    $attributes = $data['data']['attributes'] ?? null;

    if (!is_array($attributes)) {
        return defaultVirusTotalInfo($ip, 'غير معروف', 'لم يتم العثور على attributes في استجابة VirusTotal');
    }

    $stats = $attributes['last_analysis_stats'] ?? [];

    if (!is_array($stats)) {
        $stats = [];
    }

    $malicious = (int) ($stats['malicious'] ?? 0);
    $suspicious = (int) ($stats['suspicious'] ?? 0);
    $harmless = (int) ($stats['harmless'] ?? 0);
    $undetected = (int) ($stats['undetected'] ?? 0);
    $timeout = (int) ($stats['timeout'] ?? 0);
    $total = $malicious + $suspicious + $harmless + $undetected + $timeout;

    if ($malicious >= 3) {
        $status = 'خطير';
    } elseif ($malicious > 0 || $suspicious > 0) {
        $status = 'مشبوه';
    } else {
        $status = 'نظيف';
    }

    $lastAnalysisDate = '';
    if (!empty($attributes['last_analysis_date']) && is_numeric($attributes['last_analysis_date'])) {
        $lastAnalysisDate = date('Y-m-d H:i:s', (int) $attributes['last_analysis_date']);
    }

    return [
        'status' => $status,
        'malicious' => $malicious,
        'suspicious' => $suspicious,
        'harmless' => $harmless,
        'undetected' => $undetected,
        'timeout' => $timeout,
        'total' => $total,
        'reputation' => (int) ($attributes['reputation'] ?? 0),
        'asn' => (int) ($attributes['asn'] ?? 0),
        'as_owner' => (string) ($attributes['as_owner'] ?? ''),
        'last_analysis_date' => $lastAnalysisDate,
        'link' => 'https://www.virustotal.com/gui/ip-address/' . rawurlencode($ip),
        'error' => '',
    ];
}

function virusTotalLogFields(array $vt): array
{
    return [
        'vt_status' => $vt['status'] ?? 'غير معروف',
        'vt_malicious' => (int) ($vt['malicious'] ?? 0),
        'vt_suspicious' => (int) ($vt['suspicious'] ?? 0),
        'vt_harmless' => (int) ($vt['harmless'] ?? 0),
        'vt_undetected' => (int) ($vt['undetected'] ?? 0),
        'vt_timeout' => (int) ($vt['timeout'] ?? 0),
        'vt_total' => (int) ($vt['total'] ?? 0),
        'vt_reputation' => (int) ($vt['reputation'] ?? 0),
        'vt_asn' => (int) ($vt['asn'] ?? 0),
        'vt_as_owner' => (string) ($vt['as_owner'] ?? ''),
        'vt_last_analysis_date' => (string) ($vt['last_analysis_date'] ?? ''),
        'vt_link' => (string) ($vt['link'] ?? ''),
        'vt_error' => (string) ($vt['error'] ?? ''),
    ];
}

function normalizeVirusTotalResultRow(string $ip, array $row): array
{
    return [
        'ip' => $ip,
        'vt_status' => (string) ($row['vt_status'] ?? $row['status'] ?? 'غير معروف'),
        'vt_malicious' => (int) ($row['vt_malicious'] ?? $row['malicious'] ?? 0),
        'vt_suspicious' => (int) ($row['vt_suspicious'] ?? $row['suspicious'] ?? 0),
        'vt_harmless' => (int) ($row['vt_harmless'] ?? $row['harmless'] ?? 0),
        'vt_undetected' => (int) ($row['vt_undetected'] ?? $row['undetected'] ?? 0),
        'vt_timeout' => (int) ($row['vt_timeout'] ?? $row['timeout'] ?? 0),
        'vt_total' => (int) ($row['vt_total'] ?? $row['total'] ?? 0),
        'vt_reputation' => (int) ($row['vt_reputation'] ?? $row['reputation'] ?? 0),
        'vt_asn' => (int) ($row['vt_asn'] ?? $row['asn'] ?? 0),
        'vt_as_owner' => (string) ($row['vt_as_owner'] ?? $row['as_owner'] ?? ''),
        'vt_last_analysis_date' => (string) ($row['vt_last_analysis_date'] ?? $row['last_analysis_date'] ?? ''),
        'vt_link' => (string) ($row['vt_link'] ?? $row['link'] ?? ''),
        'vt_error' => (string) ($row['vt_error'] ?? $row['error'] ?? ''),
        'checked_at' => (string) ($row['checked_at'] ?? date('Y-m-d H:i:s')),
        'updated_at' => (string) ($row['updated_at'] ?? date('Y-m-d H:i:s')),
    ];
}

function saveVirusTotalResult(string $databaseFile, string $ip, array $vt): void
{
    $db = sqliteConnection($databaseFile);
    $row = normalizeVirusTotalResultRow($ip, virusTotalLogFields($vt) + [
        'checked_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    $stmt = $db->prepare('
        INSERT INTO vt_results (
            ip, vt_status, vt_malicious, vt_suspicious, vt_harmless, vt_undetected,
            vt_timeout, vt_total, vt_reputation, vt_asn, vt_as_owner,
            vt_last_analysis_date, vt_link, vt_error, checked_at, updated_at
        ) VALUES (
            :ip, :vt_status, :vt_malicious, :vt_suspicious, :vt_harmless, :vt_undetected,
            :vt_timeout, :vt_total, :vt_reputation, :vt_asn, :vt_as_owner,
            :vt_last_analysis_date, :vt_link, :vt_error, :checked_at, :updated_at
        )
        ON CONFLICT(ip) DO UPDATE SET
            vt_status = excluded.vt_status,
            vt_malicious = excluded.vt_malicious,
            vt_suspicious = excluded.vt_suspicious,
            vt_harmless = excluded.vt_harmless,
            vt_undetected = excluded.vt_undetected,
            vt_timeout = excluded.vt_timeout,
            vt_total = excluded.vt_total,
            vt_reputation = excluded.vt_reputation,
            vt_asn = excluded.vt_asn,
            vt_as_owner = excluded.vt_as_owner,
            vt_last_analysis_date = excluded.vt_last_analysis_date,
            vt_link = excluded.vt_link,
            vt_error = excluded.vt_error,
            checked_at = excluded.checked_at,
            updated_at = excluded.updated_at
    ');

    $stmt->execute([
        ':ip' => $row['ip'],
        ':vt_status' => $row['vt_status'],
        ':vt_malicious' => $row['vt_malicious'],
        ':vt_suspicious' => $row['vt_suspicious'],
        ':vt_harmless' => $row['vt_harmless'],
        ':vt_undetected' => $row['vt_undetected'],
        ':vt_timeout' => $row['vt_timeout'],
        ':vt_total' => $row['vt_total'],
        ':vt_reputation' => $row['vt_reputation'],
        ':vt_asn' => $row['vt_asn'],
        ':vt_as_owner' => $row['vt_as_owner'],
        ':vt_last_analysis_date' => $row['vt_last_analysis_date'],
        ':vt_link' => $row['vt_link'],
        ':vt_error' => $row['vt_error'],
        ':checked_at' => $row['checked_at'],
        ':updated_at' => $row['updated_at'],
    ]);
}

function getVirusTotalResult(string $databaseFile, string $ip): ?array
{
    $db = sqliteConnection($databaseFile);
    $stmt = $db->prepare('SELECT * FROM vt_results WHERE ip = :ip LIMIT 1');
    $stmt->execute([':ip' => $ip]);
    $row = $stmt->fetch();

    return is_array($row) ? normalizeVirusTotalResultRow($ip, $row) : null;
}

function isVirusTotalResultFresh(?array $row, int $ttlSeconds): bool
{
    if ($row === null || $ttlSeconds <= 0) {
        return false;
    }

    if (trim((string) ($row['vt_error'] ?? '')) !== '') {
        return false;
    }

    $checkedAt = strtotime((string) ($row['checked_at'] ?? ''));

    return $checkedAt !== false && (time() - $checkedAt) < $ttlSeconds;
}

function readVirusTotalResultsByIp(string $databaseFile): array
{
    $db = sqliteConnection($databaseFile);
    $rows = $db->query('SELECT * FROM vt_results')->fetchAll();
    $results = [];

    foreach ($rows as $row) {
        $ip = (string) ($row['ip'] ?? '');

        if ($ip === '') {
            continue;
        }

        $results[$ip] = normalizeVirusTotalResultRow($ip, $row);
    }

    return $results;
}

function backfillVirusTotalResultsFromLogs(string $databaseFile): void
{
    if (sqliteCountRows($databaseFile, 'vt_results') > 0) {
        return;
    }

    $db = sqliteConnection($databaseFile);
    $rows = $db->query("
        SELECT l.*
        FROM logs l
        INNER JOIN (
            SELECT ip, MAX(id) AS latest_id
            FROM logs
            WHERE ip != ''
              AND vt_status NOT IN ('', '-', 'غير مفعل', 'لم يتم الفحص', 'في الطابور')
            GROUP BY ip
        ) latest ON latest.latest_id = l.id
    ")->fetchAll();

    foreach ($rows as $row) {
        $ip = (string) ($row['ip'] ?? '');

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            continue;
        }

        $vt = [
            'status' => (string) ($row['vt_status'] ?? 'غير معروف'),
            'malicious' => (int) ($row['vt_malicious'] ?? 0),
            'suspicious' => (int) ($row['vt_suspicious'] ?? 0),
            'harmless' => (int) ($row['vt_harmless'] ?? 0),
            'undetected' => (int) ($row['vt_undetected'] ?? 0),
            'timeout' => (int) ($row['vt_timeout'] ?? 0),
            'total' => (int) ($row['vt_total'] ?? 0),
            'reputation' => (int) ($row['vt_reputation'] ?? 0),
            'asn' => (int) ($row['vt_asn'] ?? 0),
            'as_owner' => (string) ($row['vt_as_owner'] ?? ''),
            'last_analysis_date' => (string) ($row['vt_last_analysis_date'] ?? ''),
            'link' => (string) ($row['vt_link'] ?? ''),
            'error' => (string) ($row['vt_error'] ?? ''),
        ];

        saveVirusTotalResult($databaseFile, $ip, $vt);
    }
}

function enqueueVirusTotalScan(
    string $databaseFile,
    string $ip,
    string $reason,
    string $username,
    string $sourceIp,
    int $freshTtlSeconds,
    string $requestedAction = 'vt_check'
): array {
    $ip = trim($ip);

    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return ['status' => 'invalid', 'message' => 'IP غير صالح.'];
    }

    $latest = getVirusTotalResult($databaseFile, $ip);
    if (isVirusTotalResultFresh($latest, $freshTtlSeconds)) {
        return [
            'status' => 'skipped_recent',
            'message' => 'تم تخطيه لأن لديه نتيجة حديثة.',
            'checked_at' => (string) ($latest['checked_at'] ?? ''),
        ];
    }

    $db = sqliteConnection($databaseFile);
    $active = $db->prepare("
        SELECT id, status
        FROM vt_queue
        WHERE ip = :ip AND status IN ('queued', 'processing')
        ORDER BY id DESC
        LIMIT 1
    ");
    $active->execute([':ip' => $ip]);
    $existing = $active->fetch();

    if (is_array($existing)) {
        return [
            'status' => 'already_queued',
            'message' => 'العنوان موجود بالفعل في طابور VirusTotal.',
            'queue_id' => (int) ($existing['id'] ?? 0),
        ];
    }

    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare("
        INSERT INTO vt_queue (ip, status, reason, user, source_ip, requested_action, attempts, created_at, next_attempt_at)
        VALUES (:ip, 'queued', :reason, :user, :source_ip, :requested_action, 0, :created_at, :next_attempt_at)
    ");
    $stmt->execute([
        ':ip' => $ip,
        ':reason' => $reason,
        ':user' => $username,
        ':source_ip' => $sourceIp,
        ':requested_action' => $requestedAction,
        ':created_at' => $now,
        ':next_attempt_at' => $now,
    ]);

    return [
        'status' => 'queued',
        'message' => 'تمت إضافة العنوان إلى طابور VirusTotal.',
        'queue_id' => (int) $db->lastInsertId(),
    ];
}

function enqueueVirusTotalScans(
    string $databaseFile,
    array $ips,
    string $reason,
    string $username,
    string $sourceIp,
    int $freshTtlSeconds,
    string $requestedAction = 'vt_bulk_check'
): array {
    $result = [
        'received' => count($ips),
        'queued' => 0,
        'already_queued' => 0,
        'skipped_recent' => 0,
        'invalid' => 0,
    ];

    foreach (array_values(array_unique(array_map('trim', $ips))) as $ip) {
        $queued = enqueueVirusTotalScan($databaseFile, $ip, $reason, $username, $sourceIp, $freshTtlSeconds, $requestedAction);
        $status = (string) ($queued['status'] ?? 'invalid');

        if (isset($result[$status])) {
            $result[$status]++;
        } else {
            $result['invalid']++;
        }
    }

    return $result;
}

function virusTotalQueueStats(string $databaseFile): array
{
    $db = sqliteConnection($databaseFile);
    $stats = [
        'queued' => 0,
        'processing' => 0,
        'completed' => 0,
        'failed' => 0,
        'skipped' => 0,
        'total' => 0,
    ];

    $stmt = $db->query('SELECT status, COUNT(*) AS count FROM vt_queue GROUP BY status');

    foreach ($stmt->fetchAll() as $row) {
        $status = (string) ($row['status'] ?? '');
        $count = (int) ($row['count'] ?? 0);

        if (isset($stats[$status])) {
            $stats[$status] = $count;
        }

        $stats['total'] += $count;
    }

    return $stats;
}

function recentVirusTotalQueueRows(string $databaseFile, int $limit = 10): array
{
    $db = sqliteConnection($databaseFile);
    $stmt = $db->prepare('
        SELECT id, ip, status, reason, user, requested_action, attempts, last_error, created_at, started_at, completed_at
        FROM vt_queue
        ORDER BY id DESC
        LIMIT :limit
    ');
    $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function processNextVirusTotalQueueJob(string $databaseFile, string $logFile, string $apiKey): array
{
    $db = sqliteConnection($databaseFile);
    $now = date('Y-m-d H:i:s');
    $staleStartedBefore = date('Y-m-d H:i:s', time() - 600);

    $db->prepare("
        UPDATE vt_queue
        SET status = 'queued', next_attempt_at = :now, last_error = 'تمت إعادة المهمة بعد توقف المعالجة السابقة.'
        WHERE status = 'processing' AND started_at != '' AND started_at <= :stale_started_before
    ")->execute([
        ':now' => $now,
        ':stale_started_before' => $staleStartedBefore,
    ]);

    $db->beginTransaction();

    try {
        $stmt = $db->prepare("
            SELECT *
            FROM vt_queue
            WHERE status = 'queued' AND (next_attempt_at = '' OR next_attempt_at <= :now)
            ORDER BY id ASC
            LIMIT 1
        ");
        $stmt->execute([':now' => $now]);
        $job = $stmt->fetch();

        if (!is_array($job)) {
            $db->commit();

            return ['ok' => true, 'processed' => false, 'message' => 'لا توجد مهام VirusTotal معلقة.'];
        }

        $update = $db->prepare("
            UPDATE vt_queue
            SET status = 'processing', attempts = attempts + 1, started_at = :started_at, last_error = ''
            WHERE id = :id AND status = 'queued'
        ");
        $update->execute([
            ':started_at' => $now,
            ':id' => (int) $job['id'],
        ]);
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }

    $ip = (string) ($job['ip'] ?? '');
    $vt = getVirusTotalInfo($ip, $apiKey);
    $status = (string) ($vt['status'] ?? 'غير معروف');
    $error = (string) ($vt['error'] ?? '');
    $completedAt = date('Y-m-d H:i:s');
    $queueStatus = $error === '' ? 'completed' : (($status === 'مؤجل') ? 'queued' : 'failed');
    $nextAttemptAt = '';

    if ($queueStatus === 'queued') {
        $nextAttemptAt = date('Y-m-d H:i:s', time() + 60);
    }

    if ($queueStatus !== 'queued') {
        saveVirusTotalResult($databaseFile, $ip, $vt);
        addLog($logFile, array_merge([
            'action' => (string) ($job['requested_action'] ?? 'vt_check'),
            'ip' => $ip,
            'country' => '-',
            'city' => '-',
            'isp' => '-',
            'reason' => (string) ($job['reason'] ?? 'فحص VirusTotal من الطابور'),
            'user' => (string) ($job['user'] ?? ''),
            'time' => $completedAt,
            'source_ip' => (string) ($job['source_ip'] ?? ''),
        ], virusTotalLogFields($vt)));
    }

    $finish = $db->prepare("
        UPDATE vt_queue
        SET status = :status, completed_at = :completed_at, last_error = :last_error, next_attempt_at = :next_attempt_at
        WHERE id = :id
    ");
    $finish->execute([
        ':status' => $queueStatus,
        ':completed_at' => $queueStatus === 'queued' ? '' : $completedAt,
        ':last_error' => $error,
        ':next_attempt_at' => $nextAttemptAt,
        ':id' => (int) $job['id'],
    ]);

    return [
        'ok' => true,
        'processed' => $queueStatus !== 'queued',
        'deferred' => $queueStatus === 'queued',
        'queue_id' => (int) $job['id'],
        'ip' => $ip,
        'status' => $status,
        'queue_status' => $queueStatus,
        'error' => $error,
        'stats' => virusTotalQueueStats($databaseFile),
    ];
}

function vtBadgeClass(string $status): string
{
    if ($status === 'خطير') {
        return 'badge-vt-danger';
    }

    if ($status === 'مشبوه') {
        return 'badge-vt-warning';
    }

    if ($status === 'نظيف') {
        return 'badge-vt-success';
    }

    return 'badge-vt-muted';
}

function vtQueueStatusLabel(string $status): string
{
    return match ($status) {
        'queued' => 'منتظر',
        'processing' => 'جاري',
        'completed' => 'مكتمل',
        'failed' => 'فشل',
        'skipped' => 'متخطى',
        default => 'غير معروف',
    };
}

function vtQueueBadgeClass(string $status): string
{
    return match ($status) {
        'completed' => 'badge-vt-success',
        'failed' => 'badge-vt-danger',
        'processing' => 'badge-check',
        default => 'badge-vt-muted',
    };
}

function vtAsText(array $row): string
{
    $asn = (int) ($row['vt_asn'] ?? $row['asn'] ?? 0);
    $owner = trim((string) ($row['vt_as_owner'] ?? $row['as_owner'] ?? ''));

    if ($asn <= 0 && $owner === '') {
        return '-';
    }

    if ($asn > 0 && $owner !== '') {
        return 'AS' . $asn . ' (' . $owner . ')';
    }

    if ($asn > 0) {
        return 'AS' . $asn;
    }

    return $owner;
}

function countCurrentVirusTotalStatus(array $currentIps, array $latestVtByIp, string $status): int
{
    $count = 0;

    foreach ($currentIps as $ip) {
        if (($latestVtByIp[$ip]['vt_status'] ?? '') === $status) {
            $count++;
        }
    }

    return $count;
}

function latestVirusTotalByIp(array $log): array
{
    $map = [];

    foreach ($log as $row) {
        $ip = (string) ($row['ip'] ?? '');

        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            continue;
        }

        $action = (string) ($row['action'] ?? 'add');

        if ($action === 'delete') {
            unset($map[$ip]);
            continue;
        }

        if (isset($row['vt_status'])) {
            $map[$ip] = $row;
        }
    }

    return $map;
}

function countVirusTotalStatus(array $log, string $status): int
{
    $count = 0;

    foreach ($log as $row) {
        if (($row['vt_status'] ?? '') === $status) {
            $count++;
        }
    }

    return $count;
}
