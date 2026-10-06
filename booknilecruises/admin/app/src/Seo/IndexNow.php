<?php
declare(strict_types=1);

namespace Bnc\Seo;

use Bnc\{Config, Db, Settings};

final class IndexNow
{
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
        $client ??= new CurlIndexNowClient();
        foreach (array_chunk($urls, 10000) as $chunk) {
            try { $status = $client->submit(['host' => parse_url((string) Config::get('site_url'), PHP_URL_HOST), 'key' => $key, 'keyLocation' => rtrim((string) Config::get('site_url'), '/') . '/' . $key . '.txt', 'urlList' => $chunk]); }
            catch (\Throwable) { $status = 0; }
            Db::run("UPDATE publish_jobs SET message = CONCAT(COALESCE(message, ''), ?) WHERE id = ?", [' · IndexNow: ' . count($chunk) . ' URLs, HTTP ' . $status, $jobId]);
        }
    }
}
