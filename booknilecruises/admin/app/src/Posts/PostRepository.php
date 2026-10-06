<?php
declare(strict_types=1);

namespace Bnc\Posts;

use Bnc\{Audit, Auth, Db, HttpException, Redirects};

final class PostRepository
{
    public static function find(int $id): array
    {
        return Db::one('SELECT * FROM posts WHERE id = ?', [$id]) ?? throw new HttpException(404);
    }

    public static function save(array $d, int $id = 0): ?int
    {
        $saved = Db::tx(function () use ($d, $id, &$announce): ?int {
            $old = $id ? Db::one('SELECT * FROM posts WHERE id = ? FOR UPDATE', [$id]) : null;
            if ($id && !$old) throw new HttpException(404);
            if ($old && $old['updated_at'] !== $d['updated_at']) return null;
            if ($d['image_id'] !== '') Db::one('SELECT id FROM media WHERE id = ? FOR UPDATE', [$d['image_id']]);
            $row = PostForm::row($d, $id);
            if ($old) {
                $changed = [];
                foreach ($row as $field => $value) if ((string) $old[$field] !== (string) $value) $changed[] = $field;
                $row['updated_at'] = date('Y-m-d H:i:s', max(time(), strtotime($old['updated_at']) + 1));
                Db::update('posts', $row, 'id = ?', [$id]);
                if ($old['status'] === 'published' && $old['url'] !== $row['url']) Redirects::moved($old['url'], $row['url']);
                Audit::log('update', 'post', $id, 'عدّل المقال', ['changed' => $changed]);
            } else {
                $row['created_by'] = Auth::user()['id'];
                $id = Db::insert('posts', $row);
                Audit::log('create', 'post', $id, 'أضاف المقال');
            }
            if (Announcement::publicAt($row, date('Y-m-d H:i:s')) && !Announcement::publicAt($old, date('Y-m-d H:i:s'))) $announce = true;
            return $id;
        });
        if ($saved && !empty($announce)) Announcement::fire($saved, 'panel');
        return $saved;
    }

    public static function delete(int $id, string $target): void
    {
        Db::tx(function () use ($id, $target): void {
            $old = Db::one('SELECT * FROM posts WHERE id = ? FOR UPDATE', [$id]) ?? throw new HttpException(404);
            $existing = (int) (Db::value('SELECT id FROM redirects WHERE from_path = ?', [$old['url']]) ?? 0);
            $target = Redirects::resolve($old['url'], $target, $existing);
            if ($old['status'] === 'published') Redirects::moved($old['url'], $target);
            Db::run('DELETE FROM posts WHERE id = ?', [$id]);
            Audit::log('delete', 'post', $id, 'حذف المقال', ['redirect_to' => $target]);
        });
    }
}
