<?php
declare(strict_types=1);

namespace Bnc\Seo;

use Bnc\{Db, Settings};

final class IndexNow
{
    public static function urls(int $jobId): array
    {
        $job = Db::one('SELECT * FROM publish_jobs WHERE id = ?', [$jobId]);
        if (!$job || $job['status'] !== 'succeeded') return [];
        $previous = Db::value("SELECT finished_at FROM publish_jobs WHERE status = 'succeeded' AND id <> ? AND finished_at IS NOT NULL AND (finished_at < ? OR (finished_at = ? AND id < ?)) ORDER BY finished_at DESC, id DESC LIMIT 1", [$jobId, $job['finished_at'], $job['finished_at'], $jobId]) ?? '1970-01-01 00:00:00';
        $paths = [];
        foreach (Db::all('SELECT slug FROM trips WHERE updated_at > ?', [$previous]) as $r) $paths[] = '/trip/' . $r['slug'] . '/';
        foreach (['posts', 'terms'] as $table) foreach (Db::all("SELECT url FROM $table WHERE updated_at > ?", [$previous]) as $r) $paths[] = $r['url'];
        foreach (Db::all('SELECT from_path FROM redirects WHERE created_at > ?', [$previous]) as $r) $paths[] = $r['from_path'];
        return array_values(array_unique(array_map(fn ($path) => 'https://booknilecruises.net' . $path, $paths)));
    }
    public static function submit(int $jobId, ?IndexNowClient $client = null): void
    {
        $key = (string) Settings::get('indexnow_key', '');
        if (!preg_match('/^[a-f0-9]{32}$/D', $key)) return;
        $urls = self::urls($jobId);
        if (!$urls) return;
        $client ??= new CurlIndexNowClient();
        foreach (array_chunk($urls, 10000) as $chunk) {
            try { $status = $client->submit(['host' => 'booknilecruises.net', 'key' => $key, 'keyLocation' => 'https://booknilecruises.net/' . $key . '.txt', 'urlList' => $chunk]); }
            catch (\Throwable) { $status = 0; }
            Db::run("UPDATE publish_jobs SET message = CONCAT(COALESCE(message, ''), ?) WHERE id = ?", [' · IndexNow: ' . count($chunk) . ' URLs, HTTP ' . $status, $jobId]);
        }
    }
}
