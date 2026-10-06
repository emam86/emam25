<?php
declare(strict_types=1);
namespace Bnc\Posts;
use Bnc\{Config, Db};
use Bnc\Webhooks\Delivery;
final class Announcement
{
    public static function publicAt(?array $post, string $at): bool
    {
        return $post && $post['status'] === 'published' && $post['published_at'] !== null && $post['published_at'] <= $at;
    }
    /** Claim once; callers saving content can include this in their transaction. */
    public static function claim(int $id): bool
    {
        return Db::run("UPDATE posts SET announced_at = ?, updated_at = updated_at WHERE id = ? AND announced_at IS NULL AND status = 'published' AND published_at <= ?", [date('Y-m-d H:i:s'), $id, date('Y-m-d H:i:s')])->rowCount() > 0;
    }
    /** Safe to run repeatedly or concurrently: claim() atomically prevents duplicate events. */
    public static function due(): int
    {
        $count = 0;
        foreach (Db::all("SELECT id, url FROM posts WHERE status = 'published' AND announced_at IS NULL AND published_at <= ? ORDER BY id", [date('Y-m-d H:i:s')]) as $post) {
            $claimed = Db::tx(static function () use ($post): bool {
                if (!self::claim((int) $post['id'])) return false;
                \Bnc\Audit::log('announce', 'post', (int) $post['id'], 'حل موعد نشر المقال المجدول', null, 'scheduled-posts');
                return true;
            });
            if (!$claimed) continue;
            $row = Db::one('SELECT * FROM posts WHERE id = ?', [$post['id']]);
            if ($row) Delivery::fire('post.published', ['id' => (int) $row['id'], 'url' => rtrim((string) Config::get('site_url'), '/') . $row['url'], 'status' => 'published', 'published_at' => (new \DateTimeImmutable($row['published_at']))->format(DATE_ATOM)], 'scheduled-posts');
            $count++;
        }
        return $count;
    }

    public static function fire(int $id, string $actor, ?array $payload = null): void
    {
        $post = Db::one('SELECT * FROM posts WHERE id = ?', [$id]);
        if (!self::publicAt($post, date('Y-m-d H:i:s'))) return;
        if (!self::claim($id)) return;
        Delivery::fire('post.published', $payload ?? ['id' => $id, 'url' => rtrim((string) Config::get('site_url'), '/') . $post['url'], 'status' => 'published', 'published_at' => (new \DateTimeImmutable($post['published_at']))->format(DATE_ATOM)], $actor);
    }
}
