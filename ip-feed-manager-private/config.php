<?php
declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | IP Feed Manager Settings
 |--------------------------------------------------------------------------
 | هذا الملف هو نقطة الإعداد الرئيسية للنظام. أبقِ هذا المجلد خارج ipfeed
 | أو محمياً من الويب. يجب أن يبقى ips.txt وحده داخل ipfeed ليقرأه FortiGate.
 */

return [
    'timezone' => 'Asia/Aden',
    'storage_dir' => __DIR__,
    'database' => __DIR__ . '/ip_feed.sqlite',

    'files' => [
        'feed' => dirname(__DIR__) . '/ipfeed/ips.txt',
        'users' => __DIR__ . '/ip_feed.sqlite',
        'log' => __DIR__ . '/ip_feed.sqlite',
        'geo_cache' => __DIR__ . '/ip_feed.sqlite',
        'visitor_geo_cache' => __DIR__ . '/ip_feed.sqlite',
        'vt_settings' => __DIR__ . '/ip_feed.sqlite',
        'vt_rate_limit' => __DIR__ . '/ip_feed.sqlite',
        'login_rate_limit' => __DIR__ . '/ip_feed.sqlite',
    ],

    'operations' => [
        'logs_dir' => __DIR__ . '/logs',
        'app_log' => __DIR__ . '/logs/app.log',
        'worker_log' => __DIR__ . '/logs/vt_worker.log',
        'backup_log' => __DIR__ . '/logs/backup.log',
    ],

    'backup' => [
        'dir' => __DIR__ . '/backups',
        'retention_days' => 14,
        'max_age_hours' => 30,
    ],

    'healthcheck' => [
        'enabled' => true,
        'token' => '',
        'fail_on_warning' => false,
    ],

    'legacy_json' => [
        'users' => __DIR__ . '/users.json',
        'log' => __DIR__ . '/ips_log.json',
        'geo_cache' => __DIR__ . '/ip_geo_cache.json',
        'visitor_geo_cache' => __DIR__ . '/visitor_geo_cache.json',
        'vt_settings' => __DIR__ . '/vt_settings.json',
        'vt_rate_limit' => __DIR__ . '/vt_rate_limit.json',
        'login_rate_limit' => __DIR__ . '/login_attempts.json',
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
