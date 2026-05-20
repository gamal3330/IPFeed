<?php
declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | IP Feed Manager Settings Example
 |--------------------------------------------------------------------------
 | Copy this file to your runtime private directory as config.php.
 | Recommended production layout:
 |
 |   /var/www/IPFeed/ipfeed        Public web directory
 |   /var/lib/ipfeed               Private runtime directory
 |
 | Only ips.txt should be publicly reachable by FortiGate.
 */

$storageDir = rtrim((string) (getenv('IP_FEED_SETTINGS_DIR') ?: __DIR__), '/\\');
$projectDir = rtrim((string) (getenv('IP_FEED_PROJECT_DIR') ?: '/var/www/IPFeed'), '/\\');
$feedFile = (string) (getenv('IP_FEED_FEED_FILE') ?: $projectDir . '/ipfeed/ips.txt');

return [
    'timezone' => (string) (getenv('IP_FEED_TIMEZONE') ?: 'Asia/Aden'),
    'storage_dir' => $storageDir,
    'database' => $storageDir . '/ip_feed.sqlite',

    'files' => [
        'feed' => $feedFile,
        'users' => $storageDir . '/ip_feed.sqlite',
        'log' => $storageDir . '/ip_feed.sqlite',
        'geo_cache' => $storageDir . '/ip_feed.sqlite',
        'visitor_geo_cache' => $storageDir . '/ip_feed.sqlite',
        'vt_settings' => $storageDir . '/ip_feed.sqlite',
        'vt_rate_limit' => $storageDir . '/ip_feed.sqlite',
        'login_rate_limit' => $storageDir . '/ip_feed.sqlite',
    ],

    'operations' => [
        'logs_dir' => $storageDir . '/logs',
        'app_log' => $storageDir . '/logs/app.log',
        'worker_log' => $storageDir . '/logs/vt_worker.log',
        'backup_log' => $storageDir . '/logs/backup.log',
    ],

    'backup' => [
        'dir' => $storageDir . '/backups',
        'retention_days' => 14,
        'max_age_hours' => 30,
    ],

    'healthcheck' => [
        'enabled' => true,
        'token' => (string) (getenv('IP_FEED_HEALTH_TOKEN') ?: ''),
        'fail_on_warning' => false,
    ],

    'legacy_json' => [
        'users' => $storageDir . '/users.json',
        'log' => $storageDir . '/ips_log.json',
        'geo_cache' => $storageDir . '/ip_geo_cache.json',
        'visitor_geo_cache' => $storageDir . '/visitor_geo_cache.json',
        'vt_settings' => $storageDir . '/vt_settings.json',
        'vt_rate_limit' => $storageDir . '/vt_rate_limit.json',
        'login_rate_limit' => $storageDir . '/login_attempts.json',
    ],

    'ui' => [
        'max_log_rows_on_screen' => 300,
        'rows_per_page' => 10,
        'add_progress_threshold' => 20,
        'add_progress_chunk_size' => 10,
    ],

    'virustotal' => [
        'bulk_scan_limit' => 2,
        'public_api_safe_mode' => true,
        'requests_per_minute' => 4,
        'min_interval_seconds' => 16,
        'daily_quota' => 500,
        'max_server_wait_seconds' => 20,
        'result_fresh_ttl_seconds' => 86400,
    ],

    'visitor_country_restriction' => [
        'enabled' => false,
        'allowed_countries' => [
            'JO' => 'الأردن',
            'YE' => 'اليمن',
        ],
        'allow_local_private' => true,
        'cache_ttl_seconds' => 86400,
    ],

    'security' => [
        'force_default_admin_password_change' => true,
        'login_rate_limit' => [
            'enabled' => true,
            'max_attempts' => 5,
            'window_seconds' => 900,
            'lock_seconds' => 900,
        ],
    ],
];
