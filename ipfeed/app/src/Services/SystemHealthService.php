<?php
declare(strict_types=1);

namespace IpFeed\Services;

final class SystemHealthService
{
    public static function summarize(array $checks): array
    {
        return [
            'ok' => count(array_filter($checks, static fn (array $row): bool => ($row['status'] ?? '') === 'ok')),
            'warning' => count(array_filter($checks, static fn (array $row): bool => ($row['status'] ?? '') === 'warning')),
            'error' => count(array_filter($checks, static fn (array $row): bool => ($row['status'] ?? '') === 'error')),
        ];
    }
}
