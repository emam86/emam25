<?php
declare(strict_types=1);

namespace Bnc\Api;

use Bnc\{Audit, Config, Db, Html, Redirects};
use Bnc\Media\{Deletion, Uploader};
use Bnc\Posts\Announcement;
use Bnc\Webhooks\Delivery;

final class Posts
{
    private const TEXT = ['title' => 255, 'content_html' => 1048576, 'excerpt' => 500, 'seo_title' => 255, 'seo_description' => 500];

    public static function save(array $input, array $key, int $id = 0, ?callable $download = null): array
    {
        $old = $id ? Db::one('SELECT * FROM posts WHERE id = ?', [$id]) : null;
        if ($id && !$old) throw new ApiException(404, 'Post not found');
        if ($old && !str_starts_with($old['source'], 'api:')) throw new ApiException(403, 'Only API-created posts may be edited');
        $row = $old ? array_intersect_key($old, array_flip([...array_keys(self::TEXT), 'slug', 'status', 'published_at', 'image_id'])) : ['title' => '', 'content_html' => '', 'excerpt' => '', 'seo_title' => '', 'seo_description' => '', 'slug' => '', 'status' => 'draft', 'published_at' => date('Y-m-d H:i:s'), 'image_id' => null];
        foreach (self::TEXT as $field => $max) if (array_key_exists($field, $input) || !$old) {
            $value = Input::text($input, $field, $max, in_array($field, ['title', 'content_html'], true));
            $row[$field] = $field === 'content_html' ? Html::clean($value) : trim(strip_tags($value));
            if ($field === 'title' && $row[$field] === '') Input::invalid('title');
        }
        if (array_key_exists('status', $input)) {
            $row['status'] = Input::text($input, 'status', 20, true);
            if (!in_array($row['status'], ['draft', 'published'], true)) Input::invalid('status');
            if ($row['status'] === 'published') Keys::scope($key, 'posts.publish');
        }
        if (array_key_exists('published_at', $input) && $input['published_at'] !== '') $row['published_at'] = Input::datetime(Input::text($input, 'published_at', 40), 'published_at')->format('Y-m-d H:i:s');
        $row['published_at'] ??= date('Y-m-d H:i:s');
        if ($old && $old['status'] === 'published' && strtotime($old['published_at'] ?? $old['created_at']) > time() && $row['status'] === 'published' && strtotime($row['published_at']) <= time()) Keys::scope($key, 'posts.publish');
        if (array_key_exists('slug', $input)) $row['slug'] = Input::text($input, 'slug', 190);
        if ($row['slug'] === '') $row['slug'] = self::slug($row['title']);
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $row['slug'])) Input::invalid('slug');
        $actor = 'api:' . $key['name'];
        $imageAlt = array_key_exists('image_alt', $input) ? Input::text($input, 'image_alt', 255) : null;
        $newMediaId = null;
        if (array_key_exists('image_url', $input) && $input['image_url'] !== '') {
            $url = Input::text($input, 'image_url', 2000);
            try {
                $tmp = ($download ?? SafeHttp::image(...))($url);
                try { $row['image_id'] = $newMediaId = (new Uploader())->saveLocal($tmp, basename(parse_url($url, PHP_URL_PATH) ?: 'image'), $actor); }
                finally { unlink($tmp); }
            } catch (\RuntimeException) { Input::invalid('image_url', 'Image URL is blocked, invalid or unavailable'); }
        }
        $announce = false;
        try {
            $id = Db::tx(function () use (&$row, $id, $old, $actor, $imageAlt, &$announce): int {
                // Serialize URL allocation with panel post saves.
                Db::run("INSERT IGNORE INTO api_locks (name) VALUES ('posts')");
                Db::one("SELECT name FROM api_locks WHERE name = 'posts' FOR UPDATE");
                if ($old) {
                    $current = Db::one('SELECT * FROM posts WHERE id = ? FOR UPDATE', [$id]);
                    if (!$current) throw new ApiException(404, 'Post not found');
                    if ($current['updated_at'] !== $old['updated_at']) throw new ApiException(409, 'Post changed; retry with current values');
                }
                $base = $row['slug'];
                $prefix = (new \DateTimeImmutable($row['published_at']))->format('/Y/m/d/');
                $row['url'] = $prefix . $row['slug'] . '/';
                for ($n = 2; Db::value('SELECT id FROM posts WHERE url = ? AND id <> ?', [$row['url'], $id]); $n++) {
                    $row['slug'] = rtrim(substr($base, 0, 180), '-') . '-' . $n;
                    $row['url'] = $prefix . $row['slug'] . '/';
                }
                $row['updated_at'] = date('Y-m-d H:i:s', $old ? max(time(), strtotime($old['updated_at']) + 1) : time());
                if ($old) {
                    Db::update('posts', $row, 'id = ?', [$id]);
                    if ($old['status'] === 'published' && ($old['published_at'] === null || $old['published_at'] <= date('Y-m-d H:i:s')) && Announcement::publicAt($row, date('Y-m-d H:i:s')) && $old['url'] !== $row['url']) Redirects::moved($old['url'], $row['url']);
                } else $id = Db::insert('posts', $row + ['source' => $actor]);
                if (Announcement::publicAt($row, date('Y-m-d H:i:s'))) Redirects::claim($row['url']);
                if ($imageAlt !== null && $row['image_id']) {
                    Db::update('media', ['alt' => strip_tags($imageAlt)], 'id = ?', [$row['image_id']]);
                    Audit::log('update', 'media', $row['image_id'], 'تعديل النص البديل', null, $actor);
                }
                Audit::log($old ? 'update' : 'create', 'post', $id, 'حفظ مقال عبر API', null, $actor);
                if (!Announcement::publicAt($old, date('Y-m-d H:i:s'))) $announce = Announcement::claim($id);
                return $id;
            });
        } catch (\Throwable $e) {
            if ($newMediaId !== null) {
                $media = Db::one('SELECT * FROM media WHERE id = ?', [$newMediaId]);
                if ($media) Deletion::delete($media);
            }
            throw $e;
        }
        $due = $row['status'] === 'published' && strtotime($row['published_at']) <= time();
        $result = ['id' => $id, 'url' => rtrim((string) Config::get('site_url'), '/') . $row['url'], 'status' => $row['status'] === 'published' && !$due ? 'scheduled' : $row['status'], 'published_at' => (new \DateTimeImmutable($row['published_at']))->format(DATE_ATOM), 'edit_url' => rtrim((string) Config::get('site_url'), '/') . rtrim((string) Config::get('admin_path', '/admin'), '/') . "/posts/$id/edit"];
        if ($announce) Delivery::fire('post.published', $result, $actor);
        return $result;
    }

    private static function slug(string $title): string
    {
        $value = function_exists('transliterator_transliterate') ? transliterator_transliterate('Any-Latin; Latin-ASCII', $title) : iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
        return trim(substr(preg_replace('/[^a-z0-9]+/', '-', strtolower($value ?: 'post')), 0, 180), '-') ?: 'post';
    }
}
