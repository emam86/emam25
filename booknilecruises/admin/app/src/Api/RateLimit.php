<?php
declare(strict_types=1);

namespace Bnc\Api;

use Bnc\Db;

final class RateLimit
{
    /** Rolling windows; one lock makes the count and reservation atomic. */
    public static function take(string $lock, array $limits): void
    {
        Db::tx(function () use ($lock, $limits): void {
            Db::run('INSERT IGNORE INTO api_locks (name) VALUES (?)', [$lock]);
            Db::one('SELECT name FROM api_locks WHERE name = ? FOR UPDATE', [$lock]);
            foreach ($limits as [$bucket, $seconds, $max]) {
                $since = date('Y-m-d H:i:s', time() - $seconds);
                if ((int) Db::value('SELECT COUNT(*) FROM api_requests WHERE bucket = ? AND created_at > ?', [$bucket, $since]) >= $max) throw new ApiException(429, 'Rate limited');
            }
            foreach ($limits as [$bucket]) Db::insert('api_requests', ['bucket' => $bucket]);
            Db::run('DELETE FROM api_requests WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
        });
    }
}
