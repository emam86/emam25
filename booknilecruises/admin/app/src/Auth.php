<?php
declare(strict_types=1);

namespace Bnc;

final class Auth
{
    public const MAX_FAILS_PER_EMAIL = 5;
    public const MAX_FAILS_PER_IP = 20;
    public const LOCK_MINUTES = 15;
    public const MIN_PASSWORD = 10;

    private static ?array $user = null;
    private static bool $loaded = false;

    /**
     * Try to sign in. Returns null on success, or an Arabic error message.
     */
    public static function attempt(string $email, string $password, string $ip): ?string
    {
        $email = mb_strtolower(trim($email));
        if (self::isLocked($email, $ip)) {
            return 'محاولات دخول كثيرة. حاول بعد ' . self::LOCK_MINUTES . ' دقيقة.';
        }
        $user = Db::one('SELECT * FROM users WHERE email = ?', [$email]);
        // Verify against a dummy hash when the user is missing, so timing doesn't reveal which emails exist.
        $hash = $user['password_hash'] ?? '$2y$10$WOvoiN7UNOcH5nSjGF9gpevkoxM5PkFzJVF7AnBDdPinuV8e3IgI6';
        $ok = password_verify($password, $hash) && $user && (int) $user['is_active'] === 1;
        Db::insert('login_attempts', ['email' => $email, 'ip' => $ip, 'succeeded' => $ok ? 1 : 0]);
        if (!$ok) {
            return 'الإيميل أو كلمة السر غير صحيحة.';
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Db::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$user['id']]);
            $user = Db::one('SELECT * FROM users WHERE id = ?', [$user['id']]);
        }
        Session::regenerate();
        $_SESSION['uid'] = (int) $user['id'];
        $_SESSION['pwv'] = self::passwordVersion($user['password_hash']);
        Db::update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
        self::$loaded = false;
        Audit::log('login', 'user', $user['id'], 'تسجيل دخول');
        return null;
    }

    public static function isLocked(string $email, string $ip): bool
    {
        $since = date('Y-m-d H:i:s', time() - self::LOCK_MINUTES * 60);
        $byEmail = (int) Db::value(
            // Failures since the last successful sign-in (ids, since timestamps only have one-second resolution).
            'SELECT COUNT(*) FROM login_attempts WHERE email = ? AND succeeded = 0 AND created_at > ?
             AND id > COALESCE((SELECT MAX(id) FROM login_attempts WHERE email = ? AND succeeded = 1), 0)',
            [$email, $since, $email]
        );
        $byIp = (int) Db::value('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND succeeded = 0 AND created_at > ?', [$ip, $since]);
        return $byEmail >= self::MAX_FAILS_PER_EMAIL || $byIp >= self::MAX_FAILS_PER_IP;
    }

    public static function logout(): void
    {
        if (self::user()) Audit::log('logout', 'user', self::user()['id'], 'تسجيل خروج');
        Session::destroy();
        self::$user = null;
        self::$loaded = true;
    }

    /** The signed-in user (with role and permissions), or null. */
    public static function user(): ?array
    {
        if (self::$loaded) return self::$user;
        self::$loaded = true;
        self::$user = null;
        $uid = $_SESSION['uid'] ?? null;
        if (!$uid) return null;
        $user = Db::one('SELECT * FROM users WHERE id = ?', [$uid]);
        // A password change or deactivation ends every other session of that user.
        if (!$user || (int) $user['is_active'] !== 1 || ($_SESSION['pwv'] ?? '') !== self::passwordVersion($user['password_hash'])) {
            unset($_SESSION['uid'], $_SESSION['pwv']);
            return null;
        }
        $role = Roles::find((int) $user['role_id']);
        $user['role'] = $role;
        $user['permissions'] = $role ? Roles::permissionsOf($role) : [];
        $user['is_owner'] = $role && Roles::isOwner($role);
        unset($user['password_hash']);
        return self::$user = $user;
    }

    public static function can(string $permission): bool
    {
        $user = self::user();
        return $user !== null && ($user['is_owner'] || in_array($permission, $user['permissions'], true));
    }

    /** Called after the current user changes their own password, to keep this session alive. */
    public static function refreshSession(string $newHash): void
    {
        Session::regenerate();
        $_SESSION['pwv'] = self::passwordVersion($newHash);
        self::$loaded = false;
    }

    public static function passwordProblem(string $password): ?string
    {
        if (mb_strlen($password) < self::MIN_PASSWORD) return 'كلمة السر لازم تكون ' . self::MIN_PASSWORD . ' حروف على الأقل.';
        if (mb_strlen($password) > 200) return 'كلمة السر طويلة جدًا.';
        return null;
    }

    private static function passwordVersion(string $hash): string
    {
        return substr(hash('sha256', $hash), 0, 16);
    }

    /** For tests: forget the cached user. */
    public static function reset(): void
    {
        self::$user = null;
        self::$loaded = false;
    }
}
