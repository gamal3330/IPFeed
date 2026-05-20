<?php
declare(strict_types=1);

namespace IpFeed\Controllers;

final class WebController
{
    public function __construct(private readonly string $webRoot)
    {
    }

    public function handle(): void
    {
        $webRoot = $this->webRoot;
        require $webRoot . '/app/controllers/web.php';
    }
}
