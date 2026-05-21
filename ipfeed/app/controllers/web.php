<?php
declare(strict_types=1);

if (!defined('IP_FEED_APP')) {
    http_response_code(403);
    exit;
}

/*
 |--------------------------------------------------------------------------
 | IP Feed Manager - Professional RTL Dashboard
 |--------------------------------------------------------------------------
 | ضع هذا الملف باسم index.php داخل مجلد الويب، واترك ips.txt فقط مكشوفاً
 | حتى يعمل رابط FortiGate Feed بالشكل المعتاد. تحفظ SQLite والسجلات
 | والنسخ الاحتياطية في مجلد خاص خارج ipfeed قدر الإمكان.
 */

$webRoot = isset($webRoot) && is_string($webRoot) ? $webRoot : dirname(__DIR__, 2);
$defaultSettingsDir = dirname($webRoot) . '/ip-feed-manager-private';
$appSettingsDir = trim((string) (getenv('IP_FEED_SETTINGS_DIR') ?: ($_SERVER['IP_FEED_SETTINGS_DIR'] ?? $defaultSettingsDir)));
if ($appSettingsDir === '') {
    $appSettingsDir = $defaultSettingsDir;
}

$appConfigFile = trim((string) (getenv('IP_FEED_CONFIG_FILE') ?: ($_SERVER['IP_FEED_CONFIG_FILE'] ?? '')));
if ($appConfigFile === '') {
    $appConfigFile = rtrim($appSettingsDir, '/\\') . '/config.php';
}

$appConfig = [];
if (is_file($appConfigFile)) {
    $loadedConfig = require $appConfigFile;
    if (is_array($loadedConfig)) {
        $appConfig = $loadedConfig;
    }
}

function appConfigValue(array $config, string $path, mixed $default = null): mixed
{
    return \IpFeed\Config\AppConfig::value($config, $path, $default);
}

$appSettingsDir = rtrim((string) appConfigValue($appConfig, 'storage_dir', $appSettingsDir), '/\\');
if ($appSettingsDir === '') {
    $appSettingsDir = $defaultSettingsDir;
}

date_default_timezone_set((string) appConfigValue($appConfig, 'timezone', 'Asia/Aden'));

$databaseFile = (string) appConfigValue($appConfig, 'database', $appSettingsDir . '/ip_feed.sqlite');
$ipsFile = (string) appConfigValue($appConfig, 'files.feed', $webRoot . '/ips.txt');
$usersFile = (string) appConfigValue($appConfig, 'files.users', $databaseFile);
$logFile = (string) appConfigValue($appConfig, 'files.log', $databaseFile);
$geoCacheFile = (string) appConfigValue($appConfig, 'files.geo_cache', $databaseFile);
$visitorGeoCacheFile = (string) appConfigValue($appConfig, 'files.visitor_geo_cache', $databaseFile);
$vtSettingsFile = (string) appConfigValue($appConfig, 'files.vt_settings', $databaseFile);
$vtRateLimitFile = (string) appConfigValue($appConfig, 'files.vt_rate_limit', $databaseFile);
$loginRateLimitFile = (string) appConfigValue($appConfig, 'files.login_rate_limit', $databaseFile);
$legacyUsersFile = (string) appConfigValue($appConfig, 'legacy_json.users', $appSettingsDir . '/users.json');
$legacyLogFile = (string) appConfigValue($appConfig, 'legacy_json.log', $appSettingsDir . '/ips_log.json');
$legacyGeoCacheFile = (string) appConfigValue($appConfig, 'legacy_json.geo_cache', $appSettingsDir . '/ip_geo_cache.json');
$legacyVisitorGeoCacheFile = (string) appConfigValue($appConfig, 'legacy_json.visitor_geo_cache', $appSettingsDir . '/visitor_geo_cache.json');
$legacyVtSettingsFile = (string) appConfigValue($appConfig, 'legacy_json.vt_settings', $appSettingsDir . '/vt_settings.json');
$legacyVtRateLimitFile = (string) appConfigValue($appConfig, 'legacy_json.vt_rate_limit', $appSettingsDir . '/vt_rate_limit.json');
$legacyLoginRateLimitFile = (string) appConfigValue($appConfig, 'legacy_json.login_rate_limit', $appSettingsDir . '/login_attempts.json');

$maxLogRowsOnScreen = max(50, (int) appConfigValue($appConfig, 'ui.max_log_rows_on_screen', 300));
$rowsPerPage = max(5, (int) appConfigValue($appConfig, 'ui.rows_per_page', 10));
$bulkScanLimit = max(1, (int) appConfigValue($appConfig, 'virustotal.bulk_scan_limit', 2));
$vtPublicApiSafeMode = (bool) appConfigValue($appConfig, 'virustotal.public_api_safe_mode', true);
$addProgressThreshold = max(1, (int) appConfigValue($appConfig, 'ui.add_progress_threshold', 20));
$addProgressChunkSize = max(1, (int) appConfigValue($appConfig, 'ui.add_progress_chunk_size', 10));

/*
 | VirusTotal API v3
 | يمكن ضبط المفتاح من لوحة المدير أو كمتغير بيئة VT_API_KEY.
 | المفتاح المحفوظ في لوحة المدير لا يظهر كاملاً بعد الحفظ.
 */
$vtEnvApiKey = trim((string) (getenv('VT_API_KEY') ?: ($_SERVER['VT_API_KEY'] ?? '')));
$vtConfig = [];
$vtApiKey = '';

$vtRequestsPerMinute = max(1, (int) appConfigValue($appConfig, 'virustotal.requests_per_minute', 4));
$vtMinIntervalSeconds = max(1, (int) appConfigValue($appConfig, 'virustotal.min_interval_seconds', 16));
$vtDailyQuota = max(1, (int) appConfigValue($appConfig, 'virustotal.daily_quota', 500));
$vtMaxServerWaitSeconds = max(0, (int) appConfigValue($appConfig, 'virustotal.max_server_wait_seconds', 20));
$vtResultFreshTtlSeconds = max(0, (int) appConfigValue($appConfig, 'virustotal.result_fresh_ttl_seconds', 86400));
configureVirusTotalQuotaStorage($vtRateLimitFile, $vtDailyQuota, $vtMinIntervalSeconds, $vtMaxServerWaitSeconds);

$countryRestrictionEnabled = (bool) appConfigValue($appConfig, 'visitor_country_restriction.enabled', false);
$allowedVisitorCountryCodes = appConfigValue($appConfig, 'visitor_country_restriction.allowed_countries', [
    'JO' => 'الأردن',
    'YE' => 'اليمن',
]);
if (!is_array($allowedVisitorCountryCodes)) {
    $allowedVisitorCountryCodes = [];
}
$allowLocalAndPrivateVisitors = (bool) appConfigValue($appConfig, 'visitor_country_restriction.allow_local_private', true);
$visitorGeoCacheTtlSeconds = max(300, (int) appConfigValue($appConfig, 'visitor_country_restriction.cache_ttl_seconds', 86400));

$forceDefaultAdminPasswordChange = (bool) appConfigValue($appConfig, 'security.force_default_admin_password_change', true);
$loginRateLimitEnabled = (bool) appConfigValue($appConfig, 'security.login_rate_limit.enabled', true);
$loginRateLimitMaxAttempts = max(1, (int) appConfigValue($appConfig, 'security.login_rate_limit.max_attempts', 5));
$loginRateLimitWindowSeconds = max(60, (int) appConfigValue($appConfig, 'security.login_rate_limit.window_seconds', 900));
$loginRateLimitLockSeconds = max(60, (int) appConfigValue($appConfig, 'security.login_rate_limit.lock_seconds', 900));

$operationsLogsDir = rtrim((string) appConfigValue($appConfig, 'operations.logs_dir', $appSettingsDir . '/logs'), '/\\');
$appLogFile = (string) appConfigValue($appConfig, 'operations.app_log', $operationsLogsDir . '/app.log');
$backupDir = rtrim((string) appConfigValue($appConfig, 'backup.dir', $appSettingsDir . '/backups'), '/\\');
$backupRetentionDays = max(0, (int) appConfigValue($appConfig, 'backup.retention_days', 14));
$backupMaxAgeHours = max(1, (int) appConfigValue($appConfig, 'backup.max_age_hours', 30));
$healthCheckEnabled = (bool) appConfigValue($appConfig, 'healthcheck.enabled', true);
$healthCheckToken = trim((string) (getenv('IP_FEED_HEALTH_TOKEN') ?: ($_SERVER['IP_FEED_HEALTH_TOKEN'] ?? appConfigValue($appConfig, 'healthcheck.token', ''))));
$healthCheckFailOnWarning = (bool) appConfigValue($appConfig, 'healthcheck.fail_on_warning', false);

\IpFeed\Services\AppLogger::configurePhpErrorLog($appLogFile);

ensurePrivateSettingsDir($appSettingsDir);
ensureSqliteDatabase($databaseFile);
migrateLegacyJsonToSqlite($databaseFile, [
    'users' => $legacyUsersFile,
    'log' => $legacyLogFile,
    'geo_cache' => $legacyGeoCacheFile,
]);
migrateOperationalJsonToSqlite($databaseFile, [
    'visitor_geo_cache' => $legacyVisitorGeoCacheFile,
    'vt_settings' => $legacyVtSettingsFile,
    'vt_rate_limit' => $legacyVtRateLimitFile,
    'login_rate_limit' => $legacyLoginRateLimitFile,
]);
backfillVirusTotalResultsFromLogs($databaseFile);

$vtConfig = resolveVirusTotalConfig($vtSettingsFile, $vtEnvApiKey);
$vtApiKey = (string) ($vtConfig['api_key'] ?? '');

if (isMonitoringHealthCheckRequest()) {
    renderMonitoringHealthCheck([
        'enabled' => $healthCheckEnabled,
        'token' => $healthCheckToken,
        'fail_on_warning' => $healthCheckFailOnWarning,
        'database_file' => $databaseFile,
        'ips_file' => $ipsFile,
        'settings_dir' => $appSettingsDir,
        'app_log_file' => $appLogFile,
        'backup_dir' => $backupDir,
        'backup_max_age_hours' => $backupMaxAgeHours,
        'vt_api_configured' => $vtApiKey !== '',
    ]);
}

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) === '443');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

$visitorAccess = [
    'allowed' => true,
    'ip' => getRequestClientIp(),
    'country_code' => 'OFF',
    'country' => 'Restriction disabled',
    'source' => 'disabled',
    'allowed_countries' => $allowedVisitorCountryCodes,
    'error' => '',
];

if ($countryRestrictionEnabled) {
    $visitorAccess = resolveVisitorCountryAccess(
        $allowedVisitorCountryCodes,
        $visitorGeoCacheFile,
        $allowLocalAndPrivateVisitors,
        $visitorGeoCacheTtlSeconds
    );

    if (!($visitorAccess['allowed'] ?? false)) {
        renderCountryBlockedPage($visitorAccess);
    }
}

$message = (string) ($_SESSION['flash_message'] ?? '');
unset($_SESSION['flash_message']);
$error = '';
$requestMethod = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
ensureCsrfToken();

$users = readUsers($usersFile);
if (!file_exists($usersFile) && usersStorageError($usersFile) === '') {
    saveUsers($usersFile, $users);
}

if (isLoggedIn()) {
    $sessionUser = normalizeUsername((string) ($_SESSION['user'] ?? ''));

    if ($sessionUser === '' || !isset($users[$sessionUser]) || !(bool) ($users[$sessionUser]['active'] ?? false)) {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
        header('Location: index.php');
        exit;
    }

    $_SESSION['user'] = $sessionUser;
    $_SESSION['role'] = $users[$sessionUser]['role'] ?? 'viewer';
}

$mustChangeDefaultAdminPassword = adminMustChangeDefaultPassword($users, $forceDefaultAdminPasswordChange);

function resolveBulkIpRowsFromRequest(array $source, array $existingIps, string $databaseFile, string $geoCacheFile, string $logFile): array
{
    $logOriginal = readLog($logFile);
    $latestVtByIp = readVirusTotalResultsByIp($databaseFile) + latestVirusTotalByIp($logOriginal);
    $metadataByIp = readIpMetadataByIp($databaseFile);
    $geoCache = readGeoCache($geoCacheFile);
    $contextByIp = latestIpContextByIp($logOriginal);
    $allRows = buildIpAdminRows($existingIps, $latestVtByIp, $metadataByIp, $geoCache, $contextByIp);
    $filters = ipFiltersFromArray($source, 'bulk_');
    $filteredRows = filterIpAdminRows($allRows, $filters);
    $mode = '';

    foreach (['bulk_check_vt', 'bulk_delete_ips', 'bulk_export_ips', 'bulk_metadata_ips'] as $key) {
        if (isset($source[$key])) {
            $mode = (string) $source[$key];
            break;
        }
    }

    $selectAll = (isset($source['select_all_ips']) && (string) $source['select_all_ips'] === '1') || $mode === 'all';

    if ($selectAll) {
        return [$filteredRows, count($filteredRows)];
    }

    $postedIps = $source['selected_ips'] ?? [];

    if (!is_array($postedIps)) {
        $postedIps = [];
    }

    $selected = [];
    foreach ($postedIps as $postedIp) {
        $postedIp = trim((string) $postedIp);

        if (filter_var($postedIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && in_array($postedIp, $existingIps, true)) {
            $selected[$postedIp] = true;
        }
    }

    return [
        array_values(array_filter($allRows, static fn (array $row): bool => isset($selected[(string) ($row['ip'] ?? '')]))),
        count($filteredRows),
    ];
}

if (isset($_GET['logout'])) {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
    header('Location: index.php');
    exit;
}

if ($requestMethod === 'POST' && isset($_POST['ajax_add_ips_chunk'])) {
    if (!isLoggedIn()) {
        jsonResponse(['ok' => false, 'error' => 'يجب تسجيل الدخول أولاً.']);
    }

    if (!canModifyIps($users)) {
        jsonResponse(['ok' => false, 'error' => 'حسابك لا يملك صلاحية إضافة IPs.']);
    }

    if (!verifyCsrfToken()) {
        jsonResponse(['ok' => false, 'error' => 'انتهت صلاحية الجلسة أو الطلب غير صالح.']);
    }

    if ($mustChangeDefaultAdminPassword) {
        jsonResponse(['ok' => false, 'error' => 'يجب تغيير كلمة مرور admin الافتراضية قبل استخدام اللوحة.']);
    }

    $storageIssue = storageError($ipsFile, $logFile);

    if ($storageIssue !== '') {
        jsonResponse(['ok' => false, 'error' => $storageIssue]);
    }

    $reason = trim((string) ($_POST['reason'] ?? ''));
    $category = normalizeIpCategory((string) ($_POST['category'] ?? 'manual'));
    $expiresAt = normalizeDateOnly((string) ($_POST['expires_at'] ?? ''));
    $metadataNote = trim((string) ($_POST['metadata_note'] ?? ''));

    if ((string) ($_POST['expires_at'] ?? '') !== '' && $expiresAt === '') {
        jsonResponse(['ok' => false, 'error' => 'تاريخ انتهاء الحظر غير صحيح. استخدم الصيغة YYYY-MM-DD.']);
    }

    if ($reason === '') {
        $reason = 'بدون سبب';
    }

    $chunkIps = cleanIps((string) ($_POST['ips'] ?? ''));
    $checkVirusTotal = isset($_POST['check_virustotal']) && $vtApiKey !== '';
    $result = addIpBatchToFeed(
        $ipsFile,
        $logFile,
        $geoCacheFile,
        $chunkIps,
        $reason,
        (string) ($_SESSION['user'] ?? ''),
        getRequestClientIp(),
        $checkVirusTotal,
        $vtApiKey,
        $databaseFile,
        $vtResultFreshTtlSeconds,
        $category,
        $expiresAt,
        $metadataNote
    );

    jsonResponse(['ok' => true] + $result);
}

if ($requestMethod === 'POST' && isset($_POST['ajax_vt_process_next'])) {
    if (!isLoggedIn()) {
        jsonResponse(['ok' => false, 'error' => 'يجب تسجيل الدخول أولاً.']);
    }

    if (!canCheckVirusTotal($users)) {
        jsonResponse(['ok' => false, 'error' => 'حسابك لا يملك صلاحية فحص VirusTotal.']);
    }

    if (!verifyCsrfToken()) {
        jsonResponse(['ok' => false, 'error' => 'انتهت صلاحية الجلسة أو الطلب غير صالح.']);
    }

    if ($mustChangeDefaultAdminPassword) {
        jsonResponse(['ok' => false, 'error' => 'يجب تغيير كلمة مرور admin الافتراضية قبل استخدام اللوحة.']);
    }

    if ($vtApiKey === '') {
        jsonResponse(['ok' => false, 'error' => 'لم يتم ضبط مفتاح VirusTotal.']);
    }

    $storageIssue = storageError($ipsFile, $logFile);
    if ($storageIssue !== '') {
        jsonResponse(['ok' => false, 'error' => $storageIssue]);
    }

    jsonResponse(processNextVirusTotalQueueJob($databaseFile, $logFile, $vtApiKey));
}

if ($requestMethod === 'GET' && isset($_GET['ajax_vt_queue_status'])) {
    if (!isLoggedIn()) {
        jsonResponse(['ok' => false, 'error' => 'يجب تسجيل الدخول أولاً.']);
    }

    jsonResponse([
        'ok' => true,
        'stats' => virusTotalQueueStats($databaseFile),
        'recent' => recentVirusTotalQueueRows($databaseFile, 8),
        'quota' => virusTotalQuotaSnapshot(),
    ]);
}

if ($requestMethod === 'POST') {
    if (!verifyCsrfToken()) {
        $error = 'انتهت صلاحية الجلسة أو الطلب غير صالح. حدّث الصفحة وحاول مرة أخرى.';
    } elseif (isset($_POST['login'])) {
        $username = normalizeUsername((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $user = $users[$username] ?? null;
        $sourceIp = getRequestClientIp();
        $rateStatus = loginRateLimitStatus(
            $loginRateLimitFile,
            $username,
            $sourceIp,
            $loginRateLimitEnabled,
            $loginRateLimitMaxAttempts,
            $loginRateLimitWindowSeconds,
            $loginRateLimitLockSeconds
        );

        if (!($rateStatus['allowed'] ?? true)) {
            recordLoginEvent($databaseFile, $username, false, $sourceIp, 'rate_limited');
            $error = (string) ($rateStatus['message'] ?? 'تم إيقاف محاولات الدخول مؤقتاً.');
        } elseif (is_array($user) && (bool) ($user['active'] ?? false) && password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            recordLoginAttempt(
                $loginRateLimitFile,
                $username,
                $sourceIp,
                true,
                $loginRateLimitEnabled,
                $loginRateLimitMaxAttempts,
                $loginRateLimitWindowSeconds,
                $loginRateLimitLockSeconds
            );
            session_regenerate_id(true);
            $_SESSION['user'] = $username;
            $_SESSION['role'] = $user['role'] ?? 'viewer';
            $users[$username]['last_login'] = date('Y-m-d H:i:s');
            $users[$username]['updated_at'] = date('Y-m-d H:i:s');

            if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
                $users[$username]['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }

            if (usersStorageError($usersFile) === '') {
                saveUsers($usersFile, $users);
            }

            recordLoginEvent($databaseFile, $username, true, $sourceIp, 'success');
            ensureCsrfToken();
            header('Location: index.php');
            exit;
        } else {
            $attemptResult = recordLoginAttempt(
                $loginRateLimitFile,
                $username,
                $sourceIp,
                false,
                $loginRateLimitEnabled,
                $loginRateLimitMaxAttempts,
                $loginRateLimitWindowSeconds,
                $loginRateLimitLockSeconds
            );

            $error = 'اسم المستخدم أو كلمة المرور غير صحيحة أو الحساب غير مفعل';
            recordLoginEvent($databaseFile, $username, false, $sourceIp, 'invalid_credentials_or_inactive');

            if (!empty($attemptResult['message'])) {
                $error .= '. ' . (string) $attemptResult['message'];
            }
        }
    } elseif (isset($_POST['force_password_change']) && isLoggedIn()) {
        if (!$mustChangeDefaultAdminPassword) {
            $error = 'لا يوجد طلب تغيير كلمة مرور معلق لهذا الحساب.';
        } else {
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $newPasswordConfirm = (string) ($_POST['new_password_confirm'] ?? '');

            if (strlen($newPassword) < 8) {
                $error = 'كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل.';
            } elseif ($newPassword !== $newPasswordConfirm) {
                $error = 'تأكيد كلمة المرور غير مطابق.';
            } elseif ($newPassword === 'ChangeMe123!') {
                $error = 'لا يمكن استخدام كلمة المرور الافتراضية مرة أخرى.';
            } else {
                $usersIssue = usersStorageError($usersFile);

                if ($usersIssue !== '') {
                    $error = $usersIssue;
                } else {
                    $users['admin']['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                    $users['admin']['must_change_password'] = false;
                    $users['admin']['updated_at'] = date('Y-m-d H:i:s');
                    saveUsers($usersFile, $users);

                    auditUserManagement($logFile, 'user_password_change', 'admin', 'admin');
                    $mustChangeDefaultAdminPassword = false;
                    $message = 'تم تغيير كلمة مرور admin الافتراضية بنجاح.';
                }
            }
        }
    } elseif ($mustChangeDefaultAdminPassword && isLoggedIn()) {
        $error = 'يجب تغيير كلمة مرور admin الافتراضية قبل استخدام اللوحة.';
    } elseif (isset($_POST['user_action']) && isLoggedIn()) {
        if (!canManageUsers($users)) {
            $error = 'حسابك لا يملك صلاحية إدارة المستخدمين.';
        } else {
            $usersIssue = usersStorageError($usersFile);
            $action = (string) ($_POST['user_action'] ?? '');

            if ($usersIssue !== '') {
                $error = $usersIssue;
            } elseif ($action === 'create') {
                $newUsername = normalizeUsername((string) ($_POST['new_username'] ?? ''));
                $displayName = trim((string) ($_POST['new_display_name'] ?? ''));
                $role = (string) ($_POST['new_role'] ?? 'operator');
                $password = (string) ($_POST['new_password'] ?? '');
                $passwordConfirm = (string) ($_POST['new_password_confirm'] ?? '');

                if (!isValidUsername($newUsername)) {
                    $error = 'اسم المستخدم يجب أن يكون 3-32 حرفاً ويحتوي على حروف إنجليزية أو أرقام أو . _ - فقط.';
                } elseif (isset($users[$newUsername])) {
                    $error = 'اسم المستخدم موجود مسبقاً.';
                } elseif (!in_array($role, ['admin', 'operator', 'viewer'], true)) {
                    $error = 'الصلاحية المحددة غير صحيحة.';
                } elseif (strlen($password) < 8) {
                    $error = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.';
                } elseif ($password !== $passwordConfirm) {
                    $error = 'تأكيد كلمة المرور غير مطابق.';
                } else {
                    if ($displayName === '') {
                        $displayName = $newUsername;
                    }

                    $users[$newUsername] = [
                        'username' => $newUsername,
                        'display_name' => $displayName,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'role' => $role,
                        'active' => true,
                        'must_change_password' => false,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                        'last_login' => '',
                    ];

                    saveUsers($usersFile, $users);
                    auditUserManagement($logFile, 'user_create', $newUsername, (string) $_SESSION['user']);
                    $message = 'تم إنشاء المستخدم: ' . $newUsername;
                }
            } elseif ($action === 'update') {
                $targetUsername = normalizeUsername((string) ($_POST['target_user'] ?? ''));

                if (!isset($users[$targetUsername])) {
                    $error = 'المستخدم غير موجود.';
                } else {
                    $displayName = trim((string) ($_POST['display_name'] ?? ''));
                    $role = (string) ($_POST['role'] ?? 'operator');
                    $active = isset($_POST['active']) && (string) $_POST['active'] === '1';
                    $newPassword = (string) ($_POST['password'] ?? '');
                    $currentUsername = normalizeUsername((string) $_SESSION['user']);

                    if ($displayName === '') {
                        $displayName = $targetUsername;
                    }

                    if (!in_array($role, ['admin', 'operator', 'viewer'], true)) {
                        $error = 'الصلاحية المحددة غير صحيحة.';
                    } elseif ($targetUsername === $currentUsername && !$active) {
                        $error = 'لا يمكنك تعطيل حسابك الحالي.';
                    } elseif ($newPassword !== '' && strlen($newPassword) < 8) {
                        $error = 'كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل.';
                    } else {
                        $candidateUsers = $users;
                        $candidateUsers[$targetUsername]['display_name'] = $displayName;
                        $candidateUsers[$targetUsername]['role'] = $role;
                        $candidateUsers[$targetUsername]['active'] = $active;
                        $candidateUsers[$targetUsername]['updated_at'] = date('Y-m-d H:i:s');

                        if ($newPassword !== '') {
                            $candidateUsers[$targetUsername]['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                            $candidateUsers[$targetUsername]['must_change_password'] = false;
                        }

                        if (!hasActiveAdmin($candidateUsers)) {
                            $error = 'يجب أن يبقى مدير واحد مفعّل على الأقل.';
                        } else {
                            $users = $candidateUsers;
                            saveUsers($usersFile, $users);
                            auditUserManagement($logFile, 'user_update', $targetUsername, (string) $_SESSION['user']);
                            $message = 'تم تحديث المستخدم: ' . $targetUsername;
                        }
                    }
                }
            } elseif ($action === 'delete') {
                $targetUsername = normalizeUsername((string) ($_POST['target_user'] ?? ''));
                $currentUsername = normalizeUsername((string) $_SESSION['user']);

                if (!isset($users[$targetUsername])) {
                    $error = 'المستخدم غير موجود.';
                } elseif ($targetUsername === $currentUsername) {
                    $error = 'لا يمكنك حذف حسابك الحالي.';
                } else {
                    $candidateUsers = $users;
                    unset($candidateUsers[$targetUsername]);

                    if (!hasActiveAdmin($candidateUsers)) {
                        $error = 'لا يمكن حذف آخر مدير مفعّل.';
                    } else {
                        $users = $candidateUsers;
                        saveUsers($usersFile, $users);
                        auditUserManagement($logFile, 'user_delete', $targetUsername, (string) $_SESSION['user']);
                        $message = 'تم حذف المستخدم: ' . $targetUsername;
                    }
                }
            }
        }
    } elseif (isset($_POST['vt_settings_action']) && isLoggedIn()) {
        if (!canManageUsers($users)) {
            $error = 'حسابك لا يملك صلاحية تعديل إعدادات VirusTotal.';
        } else {
            $settingsIssue = virusTotalSettingsStorageError($appSettingsDir, $vtSettingsFile);
            $action = (string) ($_POST['vt_settings_action'] ?? '');

            if ($settingsIssue !== '') {
                $error = $settingsIssue;
            } elseif ($action === 'save') {
                $newApiKey = trim((string) ($_POST['vt_api_key'] ?? ''));

                if ($newApiKey === '') {
                    $error = 'اكتب مفتاح VirusTotal API قبل الحفظ.';
                } elseif (!isLikelyVirusTotalApiKey($newApiKey)) {
                    $error = 'صيغة مفتاح VirusTotal غير صحيحة. تأكد من نسخ المفتاح كاملاً بدون مسافات.';
                } else {
                    saveVirusTotalSettings($vtSettingsFile, [
                        'api_key' => $newApiKey,
                        'updated_by' => (string) ($_SESSION['user'] ?? ''),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);

                    $vtConfig = resolveVirusTotalConfig($vtSettingsFile, $vtEnvApiKey);
                    $vtApiKey = (string) ($vtConfig['api_key'] ?? '');
                    auditVirusTotalSettings($logFile, 'vt_key_update', (string) ($_SESSION['user'] ?? ''), 'تم تحديث مفتاح VirusTotal من لوحة المدير');
                    $message = 'تم حفظ مفتاح VirusTotal بنجاح. المفتاح الحالي: ' . maskSecret($vtApiKey);
                }
            } elseif ($action === 'clear') {
                saveVirusTotalSettings($vtSettingsFile, [
                    'api_key' => '',
                    'updated_by' => (string) ($_SESSION['user'] ?? ''),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                $vtConfig = resolveVirusTotalConfig($vtSettingsFile, $vtEnvApiKey);
                $vtApiKey = (string) ($vtConfig['api_key'] ?? '');
                auditVirusTotalSettings($logFile, 'vt_key_clear', (string) ($_SESSION['user'] ?? ''), 'تم حذف مفتاح VirusTotal المحفوظ من لوحة المدير');
                $message = $vtApiKey === ''
                    ? 'تم حذف مفتاح VirusTotal المحفوظ، وأصبح الفحص غير مفعل.'
                    : 'تم حذف المفتاح المحفوظ. سيتم استخدام مفتاح متغير البيئة VT_API_KEY كبديل.';
            } else {
                $error = 'إجراء إعدادات VirusTotal غير معروف.';
            }
        }
    } elseif (isset($_POST['backup_action']) && isLoggedIn()) {
        if (!canManageUsers($users)) {
            $error = 'حسابك لا يملك صلاحية إدارة النسخ الاحتياطي.';
        } else {
            $backupAction = (string) ($_POST['backup_action'] ?? '');

            try {
                if ($backupAction === 'create') {
                    $backupResult = createOperationalBackup($databaseFile, $ipsFile, $backupDir, $backupRetentionDays);
                    auditUserManagement($logFile, 'backup_create', (string) ($backupResult['manifest'] ?? ''), (string) $_SESSION['user']);
                    $message = 'تم إنشاء نسخة احتياطية: ' . (string) ($backupResult['manifest'] ?? '');
                } elseif ($backupAction === 'restore') {
                    $manifestName = trim((string) ($_POST['backup_manifest'] ?? ''));
                    $restoreResult = restoreOperationalBackup($databaseFile, $ipsFile, $backupDir, $manifestName);
                    $_SESSION['flash_message'] = 'تمت الاستعادة من النسخة: ' . (string) ($restoreResult['manifest'] ?? $manifestName) . '. تم إنشاء نسخة قبل الاستعادة: ' . (string) ($restoreResult['pre_restore_manifest'] ?? '-');
                    header('Location: index.php?page=settings');
                    exit;
                } else {
                    $error = 'إجراء النسخ الاحتياطي غير معروف.';
                }
            } catch (Throwable $exception) {
                $error = 'تعذر تنفيذ إجراء النسخ الاحتياطي: ' . $exception->getMessage();
            }
        }
    } elseif (isset($_POST['add_ips']) && isLoggedIn()) {
        $storageIssue = storageError($ipsFile, $logFile);

        if (!canModifyIps($users)) {
            $error = 'حسابك لا يملك صلاحية إضافة IPs.';
        } elseif ($storageIssue !== '') {
            $error = $storageIssue;
        } else {
            $input = (string) ($_POST['ips'] ?? '');
            $reason = trim((string) ($_POST['reason'] ?? ''));
            $category = normalizeIpCategory((string) ($_POST['category'] ?? 'manual'));
            $expiresAt = normalizeDateOnly((string) ($_POST['expires_at'] ?? ''));
            $metadataNote = trim((string) ($_POST['metadata_note'] ?? ''));

            if ($reason === '') {
                $reason = 'بدون سبب';
            }

            if ((string) ($_POST['expires_at'] ?? '') !== '' && $expiresAt === '') {
                $error = 'تاريخ انتهاء الحظر غير صحيح. استخدم الصيغة YYYY-MM-DD.';
            } else {
                $newIps = cleanIps($input);
                $checkVirusTotal = isset($_POST['check_virustotal']) && $vtApiKey !== '';
                $result = addIpBatchToFeed(
                    $ipsFile,
                    $logFile,
                    $geoCacheFile,
                    $newIps,
                    $reason,
                    (string) $_SESSION['user'],
                    getRequestClientIp(),
                    $checkVirusTotal,
                    $vtApiKey,
                    $databaseFile,
                    $vtResultFreshTtlSeconds,
                    $category,
                    $expiresAt,
                    $metadataNote
                );

                if (($result['added'] ?? 0) > 0) {
                    $message = 'تمت إضافة ' . number_format((int) $result['added']) . ' IP بنجاح.';
                } else {
                    $message = 'لم تتم إضافة أي IP جديد. قد تكون القيم مكررة أو غير صحيحة.';
                }
            }
        }
    } elseif (isset($_POST['bulk_check_vt']) && isLoggedIn()) {
        $mode = (string) ($_POST['bulk_check_vt'] ?? 'selected');
        $storageIssue = storageError($ipsFile, $logFile);

        if (!canCheckVirusTotal($users)) {
            $error = 'حسابك لا يملك صلاحية فحص VirusTotal.';
        } elseif ($vtApiKey === '') {
            $error = 'لم يتم ضبط مفتاح VirusTotal. أضفه من لوحة المدير أو من متغير البيئة VT_API_KEY.';
        } elseif ($storageIssue !== '') {
            $error = $storageIssue;
        } else {
            $existingNow = readExistingIps($ipsFile);
            [$targetRows, $filteredCount] = resolveBulkIpRowsFromRequest($_POST, $existingNow, $databaseFile, $geoCacheFile, $logFile);
            $ipsToCheck = ipRowsToIps($targetRows);
            $selectAllIps = (isset($_POST['select_all_ips']) && (string) $_POST['select_all_ips'] === '1') || $mode === 'all';
            $bulkIpQuery = normalizeIpSearchQuery((string) ($_POST['bulk_ip_query'] ?? ''));
            $reasonText = $selectAllIps
                ? ($bulkIpQuery !== '' ? 'فحص VirusTotal جماعي لنتائج البحث: ' . $bulkIpQuery : 'فحص VirusTotal جماعي لكل النتائج المفلترة')
                : 'فحص VirusTotal جماعي للعناوين المحددة';

            if (empty($ipsToCheck)) {
                $error = 'لم يتم اختيار أي IP صالح للفحص.';
            } else {
                $requestedCount = count($ipsToCheck);
                $limited = false;

                if ($requestedCount > $bulkScanLimit) {
                    $ipsToCheck = array_slice($ipsToCheck, 0, $bulkScanLimit);
                    $limited = true;
                }

                $queueResult = enqueueVirusTotalScans(
                    $databaseFile,
                    $ipsToCheck,
                    $reasonText,
                    (string) $_SESSION['user'],
                    getRequestClientIp(),
                    $vtResultFreshTtlSeconds,
                    'vt_bulk_check'
                );

                if ($limited) {
                    $message = 'تمت إضافة أول ' . $bulkScanLimit . ' IP إلى طابور VirusTotal من أصل ' . $requestedCount . ' مستهدف' . ($selectAllIps ? ' ضمن ' . $filteredCount . ' نتيجة مفلترة' : '') . '.';
                } else {
                    $message = 'تمت إضافة طلبات الفحص إلى طابور VirusTotal.';
                }

                $message .= ' جديد: ' . number_format((int) ($queueResult['queued'] ?? 0));
                $message .= '، موجود بالطابور: ' . number_format((int) ($queueResult['already_queued'] ?? 0));
                $message .= '، نتيجة حديثة: ' . number_format((int) ($queueResult['skipped_recent'] ?? 0));
            }
        }

    } elseif (isset($_POST['bulk_export_ips']) && isLoggedIn()) {
        $existingNow = readExistingIps($ipsFile);
        [$targetRows] = resolveBulkIpRowsFromRequest($_POST, $existingNow, $databaseFile, $geoCacheFile, $logFile);

        if (empty($targetRows)) {
            $error = 'لا توجد IPs صالحة للتصدير.';
        } else {
            sendIpExport($targetRows, (string) ($_POST['export_format'] ?? 'txt'));
        }
    } elseif (isset($_POST['bulk_delete_ips']) && isLoggedIn()) {
        $mode = (string) ($_POST['bulk_delete_ips'] ?? 'selected');
        $storageIssue = storageError($ipsFile, $logFile);

        if (!canModifyIps($users)) {
            $error = 'حسابك لا يملك صلاحية حذف IPs.';
        } elseif ($storageIssue !== '') {
            $error = $storageIssue;
        } else {
            $existingNow = readExistingIps($ipsFile);
            [$targetRows, $filteredCount] = resolveBulkIpRowsFromRequest($_POST, $existingNow, $databaseFile, $geoCacheFile, $logFile);
            $targetIps = ipRowsToIps($targetRows);

            if (empty($targetIps)) {
                $error = 'لم يتم اختيار أي IP صالح للحذف.';
            } else {
                $selectAllIps = (isset($_POST['select_all_ips']) && (string) $_POST['select_all_ips'] === '1') || $mode === 'all';
                $reason = $selectAllIps
                    ? 'حذف جماعي لكل النتائج المفلترة وعددها ' . $filteredCount
                    : 'حذف جماعي للعناوين المحددة';
                $result = deleteIpsFromFeed($ipsFile, $logFile, $databaseFile, $targetIps, (string) $_SESSION['user'], getRequestClientIp(), $reason);
                $message = 'تم حذف ' . number_format((int) ($result['deleted'] ?? 0)) . ' IP من القائمة.';
            }
        }
    } elseif (isset($_POST['bulk_metadata_ips']) && isLoggedIn()) {
        $storageIssue = storageError($ipsFile, $logFile);

        if (!canModifyIps($users)) {
            $error = 'حسابك لا يملك صلاحية تعديل تصنيفات IPs.';
        } elseif ($storageIssue !== '') {
            $error = $storageIssue;
        } else {
            $existingNow = readExistingIps($ipsFile);
            [$targetRows] = resolveBulkIpRowsFromRequest($_POST, $existingNow, $databaseFile, $geoCacheFile, $logFile);
            $targetIps = ipRowsToIps($targetRows);
            $category = normalizeIpCategory((string) ($_POST['bulk_set_category'] ?? 'manual'));
            $expiresAt = normalizeDateOnly((string) ($_POST['bulk_set_expires_at'] ?? ''));
            $note = trim((string) ($_POST['bulk_set_note'] ?? ''));

            if (empty($targetIps)) {
                $error = 'لم يتم اختيار أي IP صالح لتحديث التصنيف.';
            } elseif ((string) ($_POST['bulk_set_expires_at'] ?? '') !== '' && $expiresAt === '') {
                $error = 'تاريخ الانتهاء غير صحيح. استخدم الصيغة YYYY-MM-DD.';
            } else {
                $updated = updateIpMetadataForFeed(
                    $databaseFile,
                    $logFile,
                    $targetIps,
                    $category,
                    $expiresAt,
                    $note,
                    (string) $_SESSION['user'],
                    getRequestClientIp()
                );
                $message = 'تم تحديث تصنيف/انتهاء ' . number_format($updated) . ' IP.';
            }
        }

    } elseif (isset($_POST['check_vt_ip']) && isLoggedIn()) {
        $checkIp = trim((string) $_POST['check_vt_ip']);
        $storageIssue = storageError($ipsFile, $logFile);

        if (!canCheckVirusTotal($users)) {
            $error = 'حسابك لا يملك صلاحية فحص VirusTotal.';
        } elseif ($vtApiKey === '') {
            $error = 'لم يتم ضبط مفتاح VirusTotal. أضفه من لوحة المدير أو من متغير البيئة VT_API_KEY.';
        } elseif (!filter_var($checkIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $error = 'IP غير صحيح.';
        } elseif ($storageIssue !== '') {
            $error = $storageIssue;
        } elseif (!in_array($checkIp, readExistingIps($ipsFile), true)) {
            $error = 'لا يمكن فحص IP غير موجود في القائمة.';
        } else {
            $scanResult = runVirusTotalScanNow(
                $databaseFile,
                $logFile,
                $checkIp,
                $vtApiKey,
                'فحص VirusTotal مباشر',
                (string) $_SESSION['user'],
                getRequestClientIp(),
                $vtResultFreshTtlSeconds,
                'vt_check_now'
            );
            $status = (string) ($scanResult['status'] ?? '');

            if ($status === 'completed') {
                $vt = is_array($scanResult['vt'] ?? null) ? $scanResult['vt'] : [];
                $message = 'تم فحص IP مباشرة: ' . $checkIp . '، النتيجة: ' . (string) ($vt['status'] ?? 'غير معروف');
            } elseif ($status === 'skipped_recent') {
                $message = 'لم تتم إعادة الفحص لأن لدى IP نتيجة حديثة: ' . $checkIp;
            } elseif ($status === 'deferred') {
                $queueResult = enqueueVirusTotalScan(
                    $databaseFile,
                    $checkIp,
                    'تأجيل فحص VirusTotal المباشر بسبب حدود API',
                    (string) $_SESSION['user'],
                    getRequestClientIp(),
                    $vtResultFreshTtlSeconds,
                    'vt_check'
                );
                $queueStatus = (string) ($queueResult['status'] ?? '');

                if ($queueStatus === 'queued') {
                    $message = 'تعذر الفحص المباشر الآن بسبب حدود VirusTotal، وتمت إضافة IP للطابور: ' . $checkIp;
                } elseif ($queueStatus === 'already_queued') {
                    $message = 'تعذر الفحص المباشر الآن، وهذا IP موجود بالفعل في طابور VirusTotal: ' . $checkIp;
                } elseif ($queueStatus === 'skipped_recent') {
                    $message = 'لم تتم إعادة الفحص لأن لدى IP نتيجة حديثة: ' . $checkIp;
                } else {
                    $error = (string) ($queueResult['message'] ?? ($scanResult['message'] ?? 'تعذر فحص IP أو إضافته للطابور.'));
                }
            } else {
                $error = (string) ($scanResult['message'] ?? 'تعذر فحص IP عبر VirusTotal.');
            }
        }

    } elseif (isset($_POST['delete_ip']) && isLoggedIn()) {
        $deleteIp = trim((string) $_POST['delete_ip']);
        $storageIssue = storageError($ipsFile, $logFile);

        if (!canModifyIps($users)) {
            $error = 'حسابك لا يملك صلاحية حذف IPs.';
        } elseif (!filter_var($deleteIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $error = 'IP غير صحيح.';
        } elseif ($storageIssue !== '') {
            $error = $storageIssue;
        } else {
            $existingIps = readExistingIps($ipsFile);
            $newList = array_values(array_filter($existingIps, static function ($ip) use ($deleteIp): bool {
                return trim($ip) !== $deleteIp;
            }));

            if (count($newList) === count($existingIps)) {
                $message = 'IP غير موجود في القائمة.';
            } else {
                saveIps($ipsFile, $newList);
                deleteIpMetadataBatch($databaseFile, [$deleteIp]);

                addLog($logFile, [
                    'action' => 'delete',
                    'ip' => $deleteIp,
                    'country' => '-',
                    'city' => '-',
                    'isp' => '-',
                    'reason' => 'تم حذف IP من القائمة',
                    'user' => $_SESSION['user'],
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

                $message = 'تم حذف IP: ' . $deleteIp;
            }
        }
    }
}

$existingIps = readExistingIps($ipsFile);
$logOriginal = readLog($logFile);
$log = array_reverse($logOriginal);
$addCount = countActions($logOriginal, 'add');
$deleteCount = countActions($logOriginal, 'delete');
[$topCountries, $countryStatsMeta] = topCountriesForCurrentIpList($existingIps, $logOriginal, $geoCacheFile);
$lastUpdate = $log[0]['time'] ?? 'لا يوجد';
$latestVtByIp = readVirusTotalResultsByIp($databaseFile) + latestVirusTotalByIp($logOriginal);
$vtDangerCount = countCurrentVirusTotalStatus($existingIps, $latestVtByIp, 'خطير');
$vtSuspiciousCount = countCurrentVirusTotalStatus($existingIps, $latestVtByIp, 'مشبوه');
$vtQuotaSnapshot = virusTotalQuotaSnapshot();
$vtQueueStats = virusTotalQueueStats($databaseFile);
$recentVtQueue = recentVirusTotalQueueRows($databaseFile, 8);
$ipMetadataByIp = readIpMetadataByIp($databaseFile);
$geoCacheForRows = readGeoCache($geoCacheFile);
$latestIpContextByIp = latestIpContextByIp($logOriginal);
$allIpRows = buildIpAdminRows($existingIps, $latestVtByIp, $ipMetadataByIp, $geoCacheForRows, $latestIpContextByIp);
$ipFilters = ipFiltersFromArray($_GET);
$filteredIpRows = filterIpAdminRows($allIpRows, $ipFilters);
$filterCountries = uniqueRowValues($allIpRows, 'country');
$filterAsns = uniqueRowValues($allIpRows, 'vt_asn');
$filterUsers = uniqueRowValues($allIpRows, 'user');
$reviewMode = ipReviewMode((string) ($_GET['review_mode'] ?? 'all'));
$allReviewRows = buildIpReviewRows($allIpRows, 'all');
$reviewCounts = ipReviewCounts($allReviewRows);
$reviewRows = $reviewMode === 'all'
    ? $allReviewRows
    : array_values(array_filter($allReviewRows, static fn (array $row): bool => (string) ($row['review_code'] ?? '') === $reviewMode));
$expiredIpCount = (int) ($reviewCounts['expired'] ?? 0);
$recentLoginEvents = recentLoginEvents($databaseFile, 30);
$activeUsersCount = countActiveUsers($users);
$currentRoleLabel = roleLabel(currentUserRole($users));
$currentPage = allowedAppPage((string) ($_GET['page'] ?? 'dashboard'));
$schemaVersion = sqliteSchemaVersion($databaseFile);
$backupManifests = listOperationalBackups($backupDir, 10);

$sqliteIntegrityStatus = 'error';
$sqliteIntegrityMessage = 'تعذر فحص SQLite.';

try {
    $sqliteIntegrity = (string) sqliteConnection($databaseFile)->query('PRAGMA integrity_check')->fetchColumn();
    $sqliteIntegrityStatus = $sqliteIntegrity === 'ok' ? 'ok' : 'error';
    $sqliteIntegrityMessage = $sqliteIntegrity === 'ok' ? 'SQLite integrity_check = ok' : $sqliteIntegrity;
} catch (Throwable $exception) {
    $sqliteIntegrityMessage = $exception->getMessage();
}

$settingsRealPath = realpath($appSettingsDir) ?: $appSettingsDir;
$webRealPath = realpath($webRoot) ?: $webRoot;
$settingsOutsideWeb = !str_starts_with(rtrim($settingsRealPath, '/\\') . DIRECTORY_SEPARATOR, rtrim($webRealPath, '/\\') . DIRECTORY_SEPARATOR);
$storageIssue = storageError($ipsFile, $logFile);
$databaseIssue = databaseStorageError($databaseFile);
$vtQuotaWait = (int) ($vtQuotaSnapshot['wait_seconds'] ?? 0);
$backupSnapshot = latestBackupSnapshot($backupDir, $backupMaxAgeHours);

$systemHealthChecks = [
    [
        'group' => 'الملفات',
        'name' => 'ips.txt',
        'status' => file_exists($ipsFile) && is_readable($ipsFile) && is_writable($ipsFile) ? 'ok' : 'error',
        'detail' => $ipsFile . ' · ' . filePermissionSummary($ipsFile),
    ],
    [
        'group' => 'الملفات',
        'name' => 'مجلد الإعدادات الخاصة',
        'status' => is_dir($appSettingsDir) && is_readable($appSettingsDir) && is_writable($appSettingsDir) ? 'ok' : 'error',
        'detail' => $appSettingsDir . ' · ' . filePermissionSummary($appSettingsDir),
    ],
    [
        'group' => 'الأمان',
        'name' => 'مكان الملفات الحساسة',
        'status' => $settingsOutsideWeb ? 'ok' : 'warning',
        'detail' => $settingsOutsideWeb ? 'المجلد الخاص خارج ipfeed.' : 'المجلد الخاص داخل مسار الويب؛ تأكد من حماية .htaccess أو انقله خارج الويب.',
    ],
    [
        'group' => 'الأمان',
        'name' => 'حماية .htaccess',
        'status' => file_exists($webRoot . '/.htaccess') && file_exists($webRoot . '/app/.htaccess') ? 'ok' : 'warning',
        'detail' => 'ipfeed/.htaccess و app/.htaccess',
    ],
    [
        'group' => 'SQLite',
        'name' => 'امتداد pdo_sqlite',
        'status' => extension_loaded('pdo_sqlite') ? 'ok' : 'error',
        'detail' => extension_loaded('pdo_sqlite') ? 'pdo_sqlite مفعل في PHP.' : 'فعّل امتداد pdo_sqlite في PHP.',
    ],
    [
        'group' => 'SQLite',
        'name' => 'اتصال قاعدة البيانات',
        'status' => $databaseIssue === '' ? 'ok' : 'error',
        'detail' => $databaseIssue === '' ? $databaseFile . ' · ' . filePermissionSummary($databaseFile) : $databaseIssue,
    ],
    [
        'group' => 'SQLite',
        'name' => 'سلامة قاعدة البيانات',
        'status' => $sqliteIntegrityStatus,
        'detail' => $sqliteIntegrityMessage,
    ],
    [
        'group' => 'SQLite',
        'name' => 'Schema version',
        'status' => (int) ($schemaVersion['version'] ?? 0) >= 3 ? 'ok' : 'warning',
        'detail' => 'version=' . (int) ($schemaVersion['version'] ?? 0) . ' · ' . (string) ($schemaVersion['migration'] ?? ''),
    ],
    [
        'group' => 'SQLite',
        'name' => 'حالة التطبيق الموحدة',
        'status' => sqliteCountRows($databaseFile, 'app_state') >= 0 ? 'ok' : 'warning',
        'detail' => 'app_state rows: ' . number_format(sqliteCountRows($databaseFile, 'app_state')),
    ],
    [
        'group' => 'التشغيل',
        'name' => 'قابلية الكتابة',
        'status' => $storageIssue === '' ? 'ok' : 'error',
        'detail' => $storageIssue === '' ? 'ملفات التشغيل قابلة للقراءة والكتابة.' : $storageIssue,
    ],
    [
        'group' => 'التشغيل',
        'name' => 'سجل التطبيق',
        'status' => is_dir($operationsLogsDir) && is_writable($operationsLogsDir) && (file_exists($appLogFile) ? is_writable($appLogFile) : true) ? 'ok' : 'warning',
        'detail' => $appLogFile . ' · ' . filePermissionSummary(file_exists($appLogFile) ? $appLogFile : $operationsLogsDir),
    ],
    [
        'group' => 'التشغيل',
        'name' => 'آخر نسخة احتياطية',
        'status' => (string) ($backupSnapshot['status'] ?? 'warning'),
        'detail' => (string) ($backupSnapshot['detail'] ?? 'لا توجد معلومات عن النسخ الاحتياطي.'),
    ],
    [
        'group' => 'VirusTotal',
        'name' => 'مفتاح API',
        'status' => $vtApiKey !== '' ? 'ok' : 'warning',
        'detail' => $vtApiKey !== '' ? 'مفعل من ' . (string) ($vtConfig['source_label'] ?? 'إعداد') . ' · ' . (string) ($vtConfig['masked'] ?? '') : 'غير مضبوط؛ الفحص معطل.',
    ],
    [
        'group' => 'VirusTotal',
        'name' => 'الحدود والطابور',
        'status' => $vtQuotaWait > 0 || (int) ($vtQueueStats['failed'] ?? 0) > 0 ? 'warning' : 'ok',
        'detail' => 'انتظار الطلب التالي: ' . secondsToHumanArabic($vtQuotaWait) . ' · منتظر: ' . number_format((int) ($vtQueueStats['queued'] ?? 0)) . ' · فشل: ' . number_format((int) ($vtQueueStats['failed'] ?? 0)),
    ],
];

$systemHealthSummary = \IpFeed\Services\SystemHealthService::summarize($systemHealthChecks);

$ipSort = allowedIpSort((string) ($_GET['ip_sort'] ?? 'natural'));
$ipSearchQuery = (string) ($ipFilters['query'] ?? '');
$sortedFilteredIps = sortIpsForDisplay(ipRowsToIps($filteredIpRows), $latestVtByIp, $ipSort);
$filteredRowByIp = [];

foreach ($filteredIpRows as $row) {
    $filteredRowByIp[(string) ($row['ip'] ?? '')] = $row;
}

$displayIpRows = [];
foreach ($sortedFilteredIps as $ip) {
    if (isset($filteredRowByIp[$ip])) {
        $displayIpRows[] = $filteredRowByIp[$ip];
    }
}

$displayIps = ipRowsToIps($displayIpRows);

$ipTotalRows = count($displayIps);
$ipTotalPages = max(1, (int) ceil($ipTotalRows / $rowsPerPage));
$ipPage = min(positivePageParam('ip_page'), $ipTotalPages);
$pagedIpRows = array_slice($displayIpRows, ($ipPage - 1) * $rowsPerPage, $rowsPerPage);
$pagedIps = ipRowsToIps($pagedIpRows);

$reviewTotalRows = count($reviewRows);
$reviewTotalPages = max(1, (int) ceil($reviewTotalRows / $rowsPerPage));
$reviewPage = min(positivePageParam('review_page'), $reviewTotalPages);
$pagedReviewRows = array_slice($reviewRows, ($reviewPage - 1) * $rowsPerPage, $rowsPerPage);

$logLimited = array_slice($log, 0, $maxLogRowsOnScreen);
$logTotalRows = count($logLimited);
$logTotalPages = max(1, (int) ceil($logTotalRows / $rowsPerPage));
$logPage = min(positivePageParam('log_page'), $logTotalPages);
$pagedLog = array_slice($logLimited, ($logPage - 1) * $rowsPerPage, $rowsPerPage);
require $webRoot . '/app/views/page.php';
