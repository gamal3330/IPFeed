<?php
declare(strict_types=1);

namespace IpFeed\Config;

final class AppConfig
{
    public static function value(array $config, string $path, mixed $default = null): mixed
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
}
