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
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function allowedAppPage(string $page): string
{
    $page = strtolower(trim($page));

    return in_array($page, ['dashboard', 'settings', 'health'], true) ? $page : 'dashboard';
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
