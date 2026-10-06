<?php
declare(strict_types=1);

namespace Bnc;

final class Config
{
    private static array $data = [];

    public static function load(array $data): void
    {
        self::$data = $data;
    }

    /** Dot-path lookup: Config::get('db.dsn'). */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$data;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) return $default;
            $value = $value[$part];
        }
        return $value;
    }
}
