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
    public static function fire(int $id, string $actor, ?array $payload = null): void
    {
        $post = Db::one('SELECT * FROM posts WHERE id = ?', [$id]);
        if (!self::publicAt($post, date('Y-m-d H:i:s'))) return;
        if (!self::claim($id)) return;
        Delivery::fire('post.published', $payload ?? ['id' => $id, 'url' => rtrim((string) Config::get('site_url'), '/') . $post['url'], 'status' => 'published', 'published_at' => (new \DateTimeImmutable($post['published_at']))->format(DATE_ATOM)], $actor);
    }
}
