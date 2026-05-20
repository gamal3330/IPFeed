<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

(new \IpFeed\Controllers\WebController(__DIR__))->handle();
