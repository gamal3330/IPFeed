<?php
declare(strict_types=1);

namespace IpFeed\Services;

final class AppLogger
{
    public static function configurePhpErrorLog(string $logFile): void
    {
        if ($logFile === '') {
            return;
        }

        self::ensureLogFile($logFile);
        ini_set('log_errors', '1');
        ini_set('error_log', $logFile);

        register_shutdown_function(static function () use ($logFile): void {
            $error = error_get_last();

            if (!is_array($error)) {
                return;
            }

            $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

            if (!in_array((int) ($error['type'] ?? 0), $fatalTypes, true)) {
                return;
            }

            self::error($logFile, 'php_fatal_error', [
                'message' => (string) ($error['message'] ?? ''),
                'file' => (string) ($error['file'] ?? ''),
                'line' => (int) ($error['line'] ?? 0),
            ]);
        });
    }

    public static function info(string $logFile, string $event, array $context = []): void
    {
        self::write($logFile, 'info', $event, $context);
    }

    public static function warning(string $logFile, string $event, array $context = []): void
    {
        self::write($logFile, 'warning', $event, $context);
    }

    public static function error(string $logFile, string $event, array $context = []): void
    {
        self::write($logFile, 'error', $event, $context);
    }

    public static function write(string $logFile, string $level, string $event, array $context = []): void
    {
        if ($logFile === '') {
            return;
        }

        self::ensureLogFile($logFile);

        $record = [
            'time' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => $level,
            'event' => $event,
            'context' => self::sanitizeContext($context),
        ];

        @file_put_contents(
            $logFile,
            json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    private static function ensureLogFile(string $logFile): void
    {
        $dir = dirname($logFile);

        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        if (!file_exists($logFile)) {
            @touch($logFile);
        }

        @chmod($dir, 0750);
        @chmod($logFile, 0640);
    }

    private static function sanitizeContext(array $context): array
    {
        $clean = [];

        foreach ($context as $key => $value) {
            $key = (string) $key;

            if (preg_match('/(key|token|secret|password)/i', $key) === 1) {
                $clean[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $clean[$key] = self::sanitizeContext($value);
            } elseif (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            } else {
                $clean[$key] = get_debug_type($value);
            }
        }

        return $clean;
    }
}
