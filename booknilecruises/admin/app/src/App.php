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

        $r->get('/media', [C\MediaController::class, 'index']);
        $r->get('/media/picker.json', [C\MediaController::class, 'picker']);
        $r->post('/media/upload', [C\MediaController::class, 'upload'], 'media.upload');
        $r->get('/media/{id}', [C\MediaController::class, 'show']);
        $r->post('/media/{id}/edit', [C\MediaController::class, 'edit'], 'media.upload');
        $r->post('/media/{id}/delete', [C\MediaController::class, 'delete'], 'media.delete');

        $r->get('/trips', [C\TripsController::class, 'index'], 'trips.view');
        $r->get('/trips/new', [C\TripsController::class, 'create'], 'trips.create');
        $r->post('/trips/new', [C\TripsController::class, 'store'], 'trips.create');
        $r->get('/trips/{id}/edit', [C\TripsController::class, 'edit'], 'trips.edit');
        $r->post('/trips/{id}/edit', [C\TripsController::class, 'update'], 'trips.edit');
        $r->post('/trips/{id}/duplicate', [C\TripsController::class, 'duplicate'], 'trips.create');
        $r->get('/trips/{id}/delete', [C\TripsController::class, 'confirmDelete'], 'trips.delete');
        $r->post('/trips/{id}/delete', [C\TripsController::class, 'delete'], 'trips.delete');
        $r->get('/terms', [C\TermsController::class, 'index'], 'trips.edit');
        $r->get('/terms/new', [C\TermsController::class, 'create'], 'trips.edit');
        $r->post('/terms/new', [C\TermsController::class, 'store'], 'trips.edit');
        $r->get('/terms/{id}/edit', [C\TermsController::class, 'edit'], 'trips.edit');
        $r->post('/terms/{id}/edit', [C\TermsController::class, 'update'], 'trips.edit');
        $r->post('/terms/{id}/delete', [C\TermsController::class, 'delete'], 'trips.edit');

        $r->get('/posts', [C\PostsController::class, 'index'], 'posts.view');
        $r->get('/posts/new', [C\PostsController::class, 'create'], 'posts.create');
        $r->post('/posts/new', [C\PostsController::class, 'store'], 'posts.create');
        $r->get('/posts/{id}/edit', [C\PostsController::class, 'edit'], 'posts.edit');
        $r->post('/posts/{id}/edit', [C\PostsController::class, 'update'], 'posts.edit');
        $r->post('/posts/{id}/delete', [C\PostsController::class, 'delete'], 'posts.delete');
        $r->get('/redirects', [C\RedirectsController::class, 'index'], 'sitemap.edit');
        $r->get('/redirects/new', [C\RedirectsController::class, 'create'], 'sitemap.edit');
        $r->post('/redirects/new', [C\RedirectsController::class, 'store'], 'sitemap.edit');
        $r->get('/redirects/{id}/edit', [C\RedirectsController::class, 'edit'], 'sitemap.edit');
        $r->post('/redirects/{id}/edit', [C\RedirectsController::class, 'update'], 'sitemap.edit');
        $r->post('/redirects/{id}/delete', [C\RedirectsController::class, 'delete'], 'sitemap.edit');
        $r->get('/posts/{id}/delete', [C\PostsController::class, 'confirmDelete'], 'posts.delete');
        $r->get('/seo/report', [C\SeoReportController::class, 'index'], 'seo.edit');
        $r->post('/seo/report', [C\SeoReportController::class, 'save'], 'seo.edit');
        $r->get('/seo', [C\SeoController::class, 'index'], 'seo.edit');
        $r->get('/seo/edit', [C\SeoController::class, 'edit'], 'seo.edit');
        $r->post('/seo/edit', [C\SeoController::class, 'save'], 'seo.edit');
        $r->get('/sitemap', [C\SitemapController::class, 'index']);
        $r->get('/settings', [C\SettingsController::class, 'form'], 'settings.edit');
        $r->post('/settings', [C\SettingsController::class, 'save'], 'settings.edit');

        $r->get('/publish', [C\PublishController::class, 'index'], 'publish');
        $r->post('/publish', [C\PublishController::class, 'publish'], 'publish');
        $r->get('/enquiries', [C\EnquiriesController::class, 'index'], 'enquiries.view');
        $r->post('/enquiries/notify', [C\EnquiriesController::class, 'notify'], 'enquiries.manage');
        $r->get('/enquiries.csv', [C\EnquiriesController::class, 'csv'], 'enquiries.view');
        $r->get('/enquiries/{id}', [C\EnquiriesController::class, 'show'], 'enquiries.view');
        $r->post('/enquiries/{id}', [C\EnquiriesController::class, 'update'], 'enquiries.manage');
        $r->post('/enquiries/{id}/delete', [C\EnquiriesController::class, 'delete'], 'enquiries.manage');
        $r->get('/api-keys', [C\ApiKeysController::class, 'index'], 'api.manage');
        $r->post('/api-keys', [C\ApiKeysController::class, 'create'], 'api.manage');
        $r->post('/api-keys/{id}/revoke', [C\ApiKeysController::class, 'revoke'], 'api.manage');
        $r->post('/api-keys/{id}/delete', [C\ApiKeysController::class, 'delete'], 'api.manage');
        $r->get('/webhooks', [C\WebhooksController::class, 'index'], 'api.manage');
        $r->get('/webhooks/new', [C\WebhooksController::class, 'create'], 'api.manage');
        $r->post('/webhooks/new', [C\WebhooksController::class, 'store'], 'api.manage');
        $r->get('/webhooks/{id}/edit', [C\WebhooksController::class, 'edit'], 'api.manage');
        $r->post('/webhooks/{id}/edit', [C\WebhooksController::class, 'update'], 'api.manage');
        $r->post('/webhooks/{id}/delete', [C\WebhooksController::class, 'delete'], 'api.manage');
        $r->post('/webhooks/{id}/regenerate', [C\WebhooksController::class, 'regenerate'], 'api.manage');
        $r->post('/webhooks/{id}/test', [C\WebhooksController::class, 'test'], 'api.manage');

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
        if ($out instanceof Json) {
            http_response_code($out->status);
            header('Content-Type: application/json; charset=utf-8');
            echo $out->body();
            return;
        }
        if ($out instanceof \Bnc\Enquiries\Csv) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="enquiries.csv"');
            echo $out->body;
            return;
        }
        header('Content-Type: text/html; charset=utf-8');
        echo $out;
    }

    public static function dispatch(string $method, string $path): string|Redirect|Json|\Bnc\Enquiries\Csv
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
