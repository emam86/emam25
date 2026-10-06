<?php
declare(strict_types=1);

namespace Bnc;

final class Request
{
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? '') === '443'
            || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    /** Client IP. Hostinger passes the visitor address in REMOTE_ADDR. */
    public static function ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    }

    /** Trimmed string from POST (or GET when $fromQuery). */
    public static function str(string $key, string $default = '', bool $fromQuery = false): string
    {
        $src = $fromQuery ? $_GET : $_POST;
        $v = $src[$key] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }

    public static function int(string $key, int $default = 0, bool $fromQuery = false): int
    {
        $v = self::str($key, '', $fromQuery);
        return preg_match('/^-?\d+$/', $v) ? (int) $v : $default;
    }

    /** @return list<string> */
    public static function list(string $key): array
    {
        $v = $_POST[$key] ?? [];
        return is_array($v) ? array_values(array_filter($v, 'is_string')) : [];
    }

    public static function bool(string $key): bool
    {
        return isset($_POST[$key]) && $_POST[$key] !== '0' && $_POST[$key] !== '';
    }
}
