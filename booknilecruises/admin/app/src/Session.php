<?php
declare(strict_types=1);

namespace Bnc;

final class Session
{
    public const IDLE_SECONDS = 4 * 3600;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        $secure = Request::isHttps() || Config::get('force_secure_cookies');
        session_name($secure ? '__Host-bnc_admin' : 'bnc_admin');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',             // required by the __Host- prefix
            'secure' => (bool) $secure,
            'httponly' => true,
            'samesite' => 'Lax',       // Strict would log people out when they follow a link from email; CSRF tokens cover POSTs
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) self::IDLE_SECONDS);
        session_start();

        $now = time();
        if (isset($_SESSION['seen']) && $now - $_SESSION['seen'] > self::IDLE_SECONDS) {
            self::destroy();
            session_start();
        }
        $_SESSION['seen'] = $now;
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 3600] + array_intersect_key($p, array_flip(['path', 'domain', 'secure', 'httponly', 'samesite'])));
            session_destroy();
        }
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['flash'][] = [$type, $message];
    }

    /** @return list<array{0:string,1:string}> */
    public static function takeFlash(): array
    {
        $f = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $f;
    }
}
