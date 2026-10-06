<?php
declare(strict_types=1);
namespace Bnc\Publish;
use Bnc\{Config, Db};
final class Changes
{
    public static function window(int $id): array
    {
        $job = Db::one("SELECT * FROM publish_jobs WHERE id = ? AND status = 'succeeded'", [$id]);
        if (!$job) return [null, null];
        $from = Db::value("SELECT created_at FROM publish_jobs WHERE status = 'succeeded' AND id <> ? AND (finished_at < ? OR (finished_at = ? AND id < ?)) ORDER BY finished_at DESC, id DESC LIMIT 1", [$id, $job['finished_at'], $job['finished_at'], $id]);
        return [$from, $job['finished_at']];
    }
    public static function since(?string $from, string $to): array
    {
        $from ??= '1970-01-01 00:00:00';
        $trips = Db::all("SELECT * FROM trips WHERE status = 'published' AND updated_at >= ? AND updated_at <= ?", [$from, $to]);
        $posts = Db::all("SELECT * FROM posts WHERE status = 'published' AND published_at <= ? AND ((updated_at >= ? AND updated_at <= ?) OR (published_at >= ? AND published_at <= ?))", [$to, $from, $to, $from, $to]);
        $terms = Db::all("SELECT * FROM terms WHERE updated_at >= ? AND updated_at <= ? AND EXISTS (SELECT 1 FROM trip_terms tt JOIN trips t ON t.id = tt.trip_id WHERE tt.term_id = terms.id AND t.status = 'published')", [$from, $to]);
        $paths = array_map(fn ($r) => '/trip/' . $r['slug'] . '/', $trips);
        foreach ([$posts, $terms] as $rows) foreach ($rows as $r) $paths[] = $r['url'];
        foreach (Db::all('SELECT from_path FROM redirects WHERE created_at >= ? AND created_at <= ?', [$from, $to]) as $r) $paths[] = $r['from_path'];
        return ['urls' => array_values(array_unique(array_map(fn ($p) => rtrim((string) Config::get('site_url'), '/') . $p, $paths))), 'posts' => $posts];
    }
}
