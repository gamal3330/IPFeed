<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

$privateDir = __DIR__;
$projectDir = dirname(__DIR__);
$webDir = $projectDir . '/ipfeed';

function workerConfigValue(array $config, string $path, mixed $default = null): mixed
{
    $value = $config;

    foreach (explode('.', $path) as $key) {
        if (!is_array($value) || !array_key_exists($key, $value)) {
            return $default;
        }

        $value = $value[$key];
    }

    return $value;
}

function workerCliOption(array $argv, string $name, string $default): string
{
    $prefix = '--' . $name . '=';

    foreach ($argv as $argument) {
        if (str_starts_with((string) $argument, $prefix)) {
            return substr((string) $argument, strlen($prefix));
        }
    }

    return $default;
}

$configFile = trim((string) (getenv('IP_FEED_CONFIG_FILE') ?: ''));
if ($configFile === '') {
    $configFile = $privateDir . '/config.php';
}

$config = [];
if (is_file($configFile)) {
    $loadedConfig = require $configFile;

    if (is_array($loadedConfig)) {
        $config = $loadedConfig;
    }
}

require $webDir . '/app/bootstrap.php';

date_default_timezone_set((string) workerConfigValue($config, 'timezone', 'Asia/Aden'));

$settingsDir = rtrim((string) workerConfigValue($config, 'storage_dir', $privateDir), '/\\');
$operationsLogsDir = rtrim((string) workerConfigValue($config, 'operations.logs_dir', $settingsDir . '/logs'), '/\\');
$workerLogFile = (string) workerConfigValue($config, 'operations.worker_log', $operationsLogsDir . '/vt_worker.log');
$databaseFile = (string) workerConfigValue($config, 'database', $settingsDir . '/ip_feed.sqlite');
$logFile = (string) workerConfigValue($config, 'files.log', $databaseFile);
$vtSettingsFile = (string) workerConfigValue($config, 'files.vt_settings', $settingsDir . '/vt_settings.json');
$vtRateLimitFile = (string) workerConfigValue($config, 'files.vt_rate_limit', $settingsDir . '/vt_rate_limit.json');
$vtMinIntervalSeconds = max(1, (int) workerConfigValue($config, 'virustotal.min_interval_seconds', 16));
$vtDailyQuota = max(1, (int) workerConfigValue($config, 'virustotal.daily_quota', 500));
$vtMaxServerWaitSeconds = max(0, (int) workerConfigValue($config, 'virustotal.max_server_wait_seconds', 20));
$legacyUsersFile = (string) workerConfigValue($config, 'legacy_json.users', $settingsDir . '/users.json');
$legacyLogFile = (string) workerConfigValue($config, 'legacy_json.log', $settingsDir . '/ips_log.json');
$legacyGeoCacheFile = (string) workerConfigValue($config, 'legacy_json.geo_cache', $settingsDir . '/ip_geo_cache.json');
$limit = max(1, min(50, (int) workerCliOption($argv, 'limit', '1')));
$sleepSeconds = max(0, min(60, (int) workerCliOption($argv, 'sleep', '2')));

\IpFeed\Services\AppLogger::configurePhpErrorLog($workerLogFile);

ensurePrivateSettingsDir($settingsDir);
ensureSqliteDatabase($databaseFile);
migrateLegacyJsonToSqlite($databaseFile, [
    'users' => $legacyUsersFile,
    'log' => $legacyLogFile,
    'geo_cache' => $legacyGeoCacheFile,
]);
backfillVirusTotalResultsFromLogs($databaseFile);

$vtEnvApiKey = trim((string) (getenv('VT_API_KEY') ?: ''));
$vtConfig = resolveVirusTotalConfig($vtSettingsFile, $vtEnvApiKey);
$vtApiKey = (string) ($vtConfig['api_key'] ?? '');

if ($vtApiKey === '') {
    \IpFeed\Services\AppLogger::warning($workerLogFile, 'vt_worker_missing_api_key');
    fwrite(STDERR, "VirusTotal API key is not configured.\n");
    exit(1);
}

$results = [];

for ($i = 0; $i < $limit; $i++) {
    $result = processNextVirusTotalQueueJob($databaseFile, $logFile, $vtApiKey);
    $results[] = $result;

    if (!($result['processed'] ?? false) || ($result['deferred'] ?? false)) {
        break;
    }

    if ($sleepSeconds > 0 && $i < ($limit - 1)) {
        sleep($sleepSeconds);
    }
}

$output = [
    'ok' => true,
    'processed' => count(array_filter($results, static fn (array $row): bool => (bool) ($row['processed'] ?? false))),
    'results' => $results,
    'stats' => virusTotalQueueStats($databaseFile),
];

\IpFeed\Services\AppLogger::info($workerLogFile, 'vt_worker_run', [
    'limit' => $limit,
    'sleep_seconds' => $sleepSeconds,
    'processed' => (int) $output['processed'],
    'queued' => (int) ($output['stats']['queued'] ?? 0),
    'failed' => (int) ($output['stats']['failed'] ?? 0),
]);

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
