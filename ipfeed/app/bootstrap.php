<?php
declare(strict_types=1);

if (!defined('IP_FEED_APP')) {
    define('IP_FEED_APP', true);
}

$projectRoot = dirname(__DIR__, 2);
$composerAutoload = $projectRoot . '/vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'IpFeed\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

if (is_file($composerAutoload)) {
    require $composerAutoload;
} else {
    require_once __DIR__ . '/database.php';
    require_once __DIR__ . '/support.php';
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/users.php';
    require_once __DIR__ . '/virustotal.php';
    require_once __DIR__ . '/ip_feed.php';
    require_once __DIR__ . '/geo.php';
}
