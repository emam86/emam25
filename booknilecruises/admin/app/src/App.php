<?php
declare(strict_types=1);

namespace Bnc;

use Bnc\Controller as C;

/** Front controller for /admin. */
final class App
{
    public static function routes(): Router
    {
        $r = new Router();
        $r->get('/install', [C\InstallController::class, 'form'], 'guest');
        $r->post('/install', [C\InstallController::class, 'install'], 'guest');
        $r->get('/login', [C\AuthController::class, 'form'], 'guest');
        $r->post('/login', [C\AuthController::class, 'login'], 'guest');
        $r->post('/logout', [C\AuthController::class, 'logout'], 'guest');

        $r->get('/', [C\DashboardController::class, 'index']);
        $r->post('/system/migrate', [C\DashboardController::class, 'migrate']);
        $r->get('/account', [C\AccountController::class, 'form']);
        $r->post('/account', [C\AccountController::class, 'save']);

        $r->get('/users', [C\UsersController::class, 'index'], 'users.manage');
        $r->get('/users/new', [C\UsersController::class, 'create'], 'users.manage');
        $r->post('/users/new', [C\UsersController::class, 'store'], 'users.manage');
        $r->get('/users/{id}/edit', [C\UsersController::class, 'edit'], 'users.manage');
        $r->post('/users/{id}/edit', [C\UsersController::class, 'update'], 'users.manage');
        $r->post('/users/{id}/delete', [C\UsersController::class, 'delete'], 'users.manage');

        $r->get('/roles', [C\RolesController::class, 'index'], 'users.manage');
        $r->get('/roles/new', [C\RolesController::class, 'create'], 'users.manage');
        $r->post('/roles/new', [C\RolesController::class, 'store'], 'users.manage');
        $r->get('/roles/{id}/edit', [C\RolesController::class, 'edit'], 'users.manage');
        $r->post('/roles/{id}/edit', [C\RolesController::class, 'update'], 'users.manage');
        $r->post('/roles/{id}/delete', [C\RolesController::class, 'delete'], 'users.manage');

        $r->get('/audit', [C\AuditController::class, 'index'], 'audit.view');
        return $r;
    }

    public static function run(): void
    {
        self::securityHeaders();
        Session::start();
        $path = self::path();
        try {
            $out = self::dispatch(Request::method(), $path);
        } catch (HttpException $e) {
            http_response_code($e->status);
            $out = self::errorPage($e->status, $e->getMessage());
        }
        if ($out instanceof Redirect) {
            header('Location: ' . $out->url(), true, 303);
            return;
        }
        header('Content-Type: text/html; charset=utf-8');
        echo $out;
    }

    public static function dispatch(string $method, string $path): string|Redirect
    {
        if ($path !== '/install' && !self::installed()) return new Redirect('/install');

        $match = self::routes()->match($method, $path);
        if ($match === null) throw new HttpException(404);
        [$handler, $permission, $params] = $match;

        if ($method === 'POST' && !Csrf::valid($_POST['_csrf'] ?? null)) {
            throw new HttpException(400, 'انتهت صلاحية الصفحة. ارجع وحاول تاني.');
        }
        if ($permission !== 'guest') {
            if (!Auth::user()) {
                return new Redirect('/login', $method === 'GET' && $path !== '/' ? ['next' => $path] : []);
            }
            if ($permission !== null && !Auth::can($permission)) throw new HttpException(403);
        }
        [$class, $action] = $handler;
        return (new $class())->$action(...array_map(fn ($p) => ctype_digit($p) ? (int) $p : $p, $params));
    }

    /** Path below the admin base, e.g. "/users/3/edit". */
    public static function path(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = rtrim((string) Config::get('admin_path', '/admin'), '/');
        if ($base !== '' && str_starts_with($uri, $base)) $uri = substr($uri, strlen($base));
        return '/' . ltrim(rawurldecode($uri), '/');
    }

    public static function installed(): bool
    {
        try {
            return (int) Db::value('SELECT COUNT(*) FROM users') > 0;
        } catch (\PDOException) {
            return false;
        }
    }

    private static function securityHeaders(): void
    {
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self'; script-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'none'");
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex, nofollow');
    }

    private static function errorPage(int $status, string $message): string
    {
        $titles = [400 => 'طلب غير صالح', 403 => 'غير مسموح', 404 => 'الصفحة غير موجودة', 405 => 'طلب غير مسموح'];
        $vars = ['title' => $titles[$status] ?? 'خطأ', 'message' => $message, 'status' => $status];
        return Auth::user()
            ? View::render('partials/error', $vars)
            : View::render('partials/error', $vars, 'partials/bare');
    }
}
