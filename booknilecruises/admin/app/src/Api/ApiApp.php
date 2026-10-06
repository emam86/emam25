<?php
declare(strict_types=1);

namespace Bnc\Api;

use Bnc\{Config, Db, HttpException, Json, Request, Router};
use Bnc\Content\Exporter;
use Bnc\Enquiries\{EnquiryService, Notifier};

/** Token-only entry point: never starts or reads a panel session. */
final class ApiApp
{
    private static ?array $notification = null;

    public static function routes(): Router
    {
        $r = new Router();
        $r->post('/seo/check', ['seoCheck'], 'workflow');
        $r->get('/export', ['export'], 'workflow');
        $r->post('/enquiries', ['enquiry'], 'public');
        $r->add('OPTIONS', '/enquiries', ['options'], 'public');
        $r->get('/v1/trips', ['trips'], 'trips.read');
        $r->post('/v1/posts', ['post'], 'posts.write');
        $r->add('PATCH', '/v1/posts/{id}', ['post'], 'posts.write');
        $r->get('/v1/enquiries', ['enquiries'], 'enquiries.read');
        return $r;
    }

    public static function path(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = rtrim((string) Config::get('api_path', '/api'), '/');
        if ($base !== '' && $uri !== $base && !str_starts_with($uri, $base . '/')) return '/__not_found__';
        return '/' . ltrim(rawurldecode(substr($uri, strlen($base))), '/');
    }

    public static function run(): void
    {
        ini_set('display_errors', '0');
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex, nofollow');
        header('X-Content-Type-Options: nosniff');
        try {
            $body = '';
            if (in_array(Request::method(), ['POST', 'PATCH'], true)) {
                if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 1048576) throw new ApiException(413, 'Body exceeds 1 MB');
                $body = (string) file_get_contents('php://input', false, null, 0, 1048577);
                if (strlen($body) > 1048576) throw new ApiException(413, 'Body exceeds 1 MB');
            }
            $out = self::dispatch(Request::method(), self::path(), $body);
        } catch (ApiException $e) { $out = self::error($e); }
        catch (\Throwable $e) { error_log('[bnc] API request failed: ' . $e); $out = new Json(['error' => 'Internal server error'], 500); }
        http_response_code($out->status);
        if ($out->status !== 204) echo $out->body();
        if (self::$notification !== null) {
            if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
            Notifier::send(self::$notification);
            self::$notification = null;
        }
    }

    /** Also used by tests without HTTP. All errors are JSON, including router errors. */
    public static function dispatch(string $method, string $path, string $body = ''): Json
    {
        self::$notification = null;
        try {
            if (strlen($body) > 1048576) throw new ApiException(413, 'Body exceeds 1 MB');
            $match = self::routes()->match($method, $path);
            if (!$match) throw new ApiException(404, 'Not found');
            [$handler, $scope, $params] = $match;
            if ($handler[0] === 'options') return new Json([], 204);
            $key = null;
            if ($scope === 'workflow') self::workflowToken();
            elseif ($scope !== 'public') $key = Keys::authenticate(self::token(), $scope);
            if ($scope === 'public') EnquiryService::origin($_SERVER['HTTP_ORIGIN'] ?? null);
            $d = [];
            if (in_array($method, ['POST', 'PATCH'], true)) {
                $type = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? 'application/json')[0]));
                if ($handler[0] === 'enquiry' && $type === 'application/x-www-form-urlencoded') parse_str($body, $d);
                else {
                    try { $object = json_decode($body, false, 64, JSON_THROW_ON_ERROR); }
                    catch (\JsonException) { throw new ApiException(400, 'Bad JSON'); }
                    if (!$object instanceof \stdClass) throw new ApiException(400, 'Expected a JSON object');
                    $d = (array) $object;
                }
            }
            return match ($handler[0]) {
                'export' => new Json([], 200, Exporter::json()),
                'seoCheck' => self::seoCheck($d),
                'enquiry' => self::enquiry($d),
                'trips' => self::trips(),
                'post' => new Json(Posts::save($d, $key, isset($params[0]) ? (int) $params[0] : 0), $method === 'POST' ? 201 : 200),
                'enquiries' => self::enquiries(),
            };
        } catch (ApiException $e) { return self::error($e); }
        catch (HttpException $e) { return new Json(['error' => $e->status === 405 ? 'Method not allowed' : 'Not found'], $e->status); }
        catch (\Throwable $e) { error_log('[bnc] API request failed: ' . $e); return new Json(['error' => 'Internal server error'], 500); }
    }

    private static function error(ApiException $e): Json
    {
        return new Json(['error' => $e->getMessage()] + ($e->fields ? ['fields' => $e->fields] : []), $e->status);
    }

    private static function token(): string
    {
        $header = self::authorization();
        if (preg_match('/^Bearer ([^\s]+)$/iD', $header, $m)) return $m[1];
        return (string) ($_SERVER['HTTP_X_API_KEY'] ?? '');
    }

    /** The Authorization header, wherever the web server put it (see public/api/.htaccess). */
    private static function authorization(): string
    {
        $value = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
        if ($value === null && function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $v) if (strcasecmp($name, 'Authorization') === 0) $value = $v;
        }
        return trim((string) $value);
    }

    private static function workflowToken(): void
    {
        $expected = (string) Config::get('export_token', '');
        $header = self::authorization();
        if (strlen($expected) < 32 || !preg_match('/^Bearer ([^\s]+)$/iD', $header, $m) || !hash_equals($expected, $m[1])) throw new ApiException(401, 'Invalid export token');
    }

    private static function seoCheck(array $d): Json
    {
        if (array_key_exists('email', $d) && !is_bool($d['email'])) Input::invalid('email');
        $report = (new \Bnc\Seo\Checker())->run();
        if ($d['email'] ?? false) \Bnc\Seo\ReportMailer::send($report);
        return new Json(['errors' => (int) $report['error_count'], 'warnings' => (int) $report['warning_count'], 'report_id' => (int) $report['id']]);
    }

    private static function enquiry(array $d): Json
    {
        self::$notification = EnquiryService::create($d);
        return new Json(['ok' => true], 201);
    }

    private static function trips(): Json
    {
        $site = rtrim((string) Config::get('site_url'), '/');
        $rows = Db::all("SELECT t.*, m.path AS cover FROM trips t LEFT JOIN media m ON m.id = t.image_id WHERE t.status = 'published' ORDER BY t.sort_order, t.id");
        $categories = [];
        if ($rows) {
            $ids = array_column($rows, 'id');
            foreach (Db::all('SELECT tt.trip_id, terms.id, terms.slug, terms.name, terms.taxonomy FROM terms JOIN trip_terms tt ON tt.term_id = terms.id WHERE tt.trip_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY tt.position, terms.id', $ids) as $category) {
                $tripId = $category['trip_id']; unset($category['trip_id']); $categories[$tripId][] = $category;
            }
        }
        $trips = [];
        foreach ($rows as $r) $trips[] = [
            'id' => (int) $r['id'], 'slug' => $r['slug'], 'title' => $r['title'], 'url' => $site . '/trip/' . $r['slug'] . '/',
            'price' => $r['price'] === null ? null : (float) $r['price'], 'currency' => $r['currency'],
            'duration_days' => $r['duration_days'] === null ? null : (int) $r['duration_days'], 'duration_nights' => $r['duration_nights'] === null ? null : (int) $r['duration_nights'],
            'categories' => $categories[$r['id']] ?? [],
            'cover_image_url' => $r['cover'] ? $site . $r['cover'] : null,
        ];
        return new Json(['trips' => $trips]);
    }

    private static function enquiries(): Json
    {
        $where = ['1=1']; $params = [];
        $cursor = Request::str('since_id', '0', true);
        if (!ctype_digit($cursor) || strlen($cursor) > 18) Input::invalid('since_id');
        $where[] = 'id > ?'; $params[] = (int) $cursor;
        $since = Request::str('since', '', true); $status = Request::str('status', '', true);
        if ($since !== '') { $where[] = 'created_at >= ?'; $params[] = Input::datetime($since, 'since')->format('Y-m-d H:i:s'); }
        if ($status !== '') {
            if (!isset(EnquiryService::STATUSES[$status])) Input::invalid('status');
            $where[] = 'status = ?'; $params[] = $status;
        }
        $rows = Db::all('SELECT * FROM enquiries WHERE ' . implode(' AND ', $where) . ' ORDER BY id LIMIT 200', $params);
        return new Json(['enquiries' => $rows, 'next_since_id' => $rows ? (int) end($rows)['id'] : (int) $cursor]);
    }
}
