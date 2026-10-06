<?php
declare(strict_types=1);

namespace Bnc\Seo;

use Bnc\{Config, Db, Settings};

final class IndexNow
{
    /** Capture paths before commit, submit only after the transaction succeeds. */
    public static function contentPaths(string $entity, int|string|null $id, ?array $details = null): array
    {
        $paths = [];
        if ($entity === 'trip') {
            $slug = Db::value("SELECT slug FROM trips WHERE id = ? AND status = 'published'", [$id]);
            if ($slug) $paths[] = '/trip/' . $slug . '/';
        } elseif ($entity === 'post') {
            $path = Db::value("SELECT url FROM posts WHERE id = ? AND status = 'published' AND (published_at IS NULL OR published_at <= ?)", [$id, date('Y-m-d H:i:s')]);
            if ($path) $paths[] = $path;
        } elseif ($entity === 'term') {
            $path = Db::value('SELECT url FROM terms WHERE id = ?', [$id]);
            if ($path) $paths[] = $path;
        } elseif (in_array($entity, ['seo_override', 'seo'], true)) {
            $path = $details['path'] ?? (is_string($id) ? $id : null);
            if (is_string($path) && \Bnc\SitePages::validPath($path)) $paths[] = $path;
        } elseif ($entity === 'redirect') {
            $path = $details['new']['from_path'] ?? Db::value('SELECT from_path FROM redirects WHERE id = ?', [$id]);
            if ($path) $paths[] = $path;
        }
        elseif (in_array($entity, ['settings', 'media', 'site', 'import'], true)) {
            $map = \Bnc\SitePages::sitemap();
            foreach ($map['groups'] as $group) foreach ($group as $path) $paths[] = is_array($path) ? $path['path'] : $path;
        }
        if (isset($details['old_path'])) $paths[] = $details['old_path'];
        foreach ($details['paths'] ?? [] as $path) $paths[] = $path;
        if (isset($details['old']['from_path'])) $paths[] = $details['old']['from_path'];
        return array_values(array_unique($paths));
    }

    public static function submitPaths(array $paths, ?IndexNowClient $client = null): void
    {
        $key = (string) Settings::get('indexnow_key', '');
        if (!preg_match('/^[a-f0-9]{32}$/D', $key) || !$paths) return;
        $site = rtrim((string) Config::get('site_url'), '/');
        // Real submissions can be switched off (the test suite does); an injected client always runs.
        if ($client === null && !Config::get('indexnow_enabled', true)) return;
        $client ??= new CurlIndexNowClient();
        foreach (array_chunk(array_values(array_unique($paths)), 10000) as $chunk) {
            try {
                $status = $client->submit(['host' => parse_url($site, PHP_URL_HOST), 'key' => $key, 'keyLocation' => $site . '/' . $key . '.txt', 'urlList' => array_map(static fn ($path) => $site . $path, $chunk)]);
                error_log('[bnc] IndexNow: ' . count($chunk) . ' URLs, HTTP ' . $status);
            } catch (\Throwable $e) { error_log('[bnc] IndexNow failed: ' . $e->getMessage()); }
        }
    }

    public static function urls(int $jobId): array
    {
        $job = Db::one('SELECT * FROM publish_jobs WHERE id = ?', [$jobId]);
        if (!$job || $job['status'] !== 'succeeded') return [];
        [$from, $to] = \Bnc\Publish\Changes::window($jobId);
        return \Bnc\Publish\Changes::since($from, $to)['urls'];
    }
    public static function submit(int $jobId, ?IndexNowClient $client = null): void
    {
        $key = (string) Settings::get('indexnow_key', '');
        if (!preg_match('/^[a-f0-9]{32}$/D', $key)) return;
        $urls = self::urls($jobId);
        if (!$urls) return;
        if ($client === null && !Config::get('indexnow_enabled', true)) return;
        $client ??= new CurlIndexNowClient();
        foreach (array_chunk($urls, 10000) as $chunk) {
            try { $status = $client->submit(['host' => parse_url((string) Config::get('site_url'), PHP_URL_HOST), 'key' => $key, 'keyLocation' => rtrim((string) Config::get('site_url'), '/') . '/' . $key . '.txt', 'urlList' => $chunk]); }
            catch (\Throwable) { $status = 0; }
            Db::run("UPDATE publish_jobs SET message = CONCAT(COALESCE(message, ''), ?) WHERE id = ?", [' · IndexNow: ' . count($chunk) . ' URLs, HTTP ' . $status, $jobId]);
        }
    }
}
