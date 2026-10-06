<?php
declare(strict_types=1);

namespace Bnc\Trips;

use Bnc\{Audit, Auth, Db, HttpException, Redirects};

final class TripRepository
{
    public static function find(int $id): array
    {
        return Db::one('SELECT * FROM trips WHERE id = ?', [$id]) ?? throw new HttpException(404);
    }

    public static function terms(int $id, array $ids): void
    {
        Db::run('DELETE FROM trip_terms WHERE trip_id = ?', [$id]);
        foreach (array_values(array_unique(array_map('intval', $ids))) as $position => $term) Db::insert('trip_terms', ['trip_id' => $id, 'term_id' => $term, 'position' => $position]);
    }

    /** Returns null on an optimistic-lock conflict. */
    public static function save(array $d, int $id = 0): ?int
    {
        return Db::tx(function () use ($d, $id): ?int {
            $old = $id ? Db::one('SELECT * FROM trips WHERE id = ? FOR UPDATE', [$id]) : null;
            if ($id && !$old) throw new HttpException(404);
            if ($old && $old['updated_at'] !== $d['updated_at']) return null;
            // Gallery ids have no foreign key; lock their media rows through commit.
            $mediaIds = array_values(array_unique([...$d['gallery'], ...($d['image_id'] === '' ? [] : [$d['image_id']])]));
            sort($mediaIds, SORT_NUMERIC);
            if ($mediaIds) Db::all('SELECT id FROM media WHERE id IN (' . implode(',', array_fill(0, count($mediaIds), '?')) . ') ORDER BY id FOR UPDATE', $mediaIds);
            $errors = TripForm::validate($d, $id);
            if ($errors) throw new \DomainException(implode("\n", $errors));
            $row = TripForm::row($d);
            $row['updated_by'] = Auth::user()['id'];
            if ($old) {
                $changed = [];
                foreach ($row as $field => $value) if ((string) $old[$field] !== (string) $value) $changed[] = $field;
                $oldTerms = TripForm::load($old)['term_ids'];
                if (array_map('intval', $oldTerms) !== array_map('intval', $d['term_ids'])) $changed[] = 'term_ids';
                // DATETIME has second precision; advance even for two saves in one second.
                $row['updated_at'] = date('Y-m-d H:i:s', max(time(), strtotime($old['updated_at']) + 1));
                Db::update('trips', $row, 'id = ?', [$id]);
                if ($old['status'] === 'published' && $row['status'] === 'published' && $old['slug'] !== $row['slug']) Redirects::moved('/trip/' . $old['slug'] . '/', '/trip/' . $row['slug'] . '/');
                Audit::log('update', 'trip', $id, 'عدّل الرحلة', ['changed' => $changed, 'old_path' => $old['status'] === 'published' ? '/trip/' . $old['slug'] . '/' : null]);
            } else {
                $row['created_by'] = Auth::user()['id'];
                $id = Db::insert('trips', $row);
                Audit::log('create', 'trip', $id, 'أضاف الرحلة');
            }
            if ($row['status'] === 'published') Redirects::claim('/trip/' . $row['slug'] . '/');
            self::terms($id, $d['term_ids']);
            return $id;
        });
    }

    public static function duplicate(int $id): int
    {
        return Db::tx(function () use ($id): int {
            $row = Db::one('SELECT * FROM trips WHERE id = ? FOR UPDATE', [$id]) ?? throw new HttpException(404);
            $terms = TripForm::load($row)['term_ids'];
            $base = rtrim(substr($row['slug'], 0, 180), '-');
            $slug = $base . '-copy';
            for ($n = 2; Db::value('SELECT id FROM trips WHERE slug = ?', [$slug]); $n++) $slug = $base . '-copy-' . $n;
            unset($row['id'], $row['created_at'], $row['updated_at'], $row['legacy_id']);
            $row['slug'] = $slug;
            $row['title'] = mb_substr($row['title'], 0, 248) . ' (copy)';
            $row['status'] = 'draft';
            $row['created_by'] = $row['updated_by'] = Auth::user()['id'];
            $new = Db::insert('trips', $row);
            self::terms($new, $terms);
            Audit::log('duplicate', 'trip', $new, 'نسخ الرحلة', ['original_id' => $id]);
            return $new;
        });
    }
}
