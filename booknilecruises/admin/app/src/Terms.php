<?php
declare(strict_types=1);

namespace Bnc;

final class Terms
{
    public const TAXONOMIES = ['destination' => 'الوجهات', 'activities' => 'الأنشطة', 'trip_types' => 'أنواع الرحلات'];
    public const PROTECTED_ACTIVITY_SLUGS = ['nile-cruise', 'standard-5-star-nile-cruises', 'deluxe-nile-cruises', 'luxury-nile-cruises', 'dahabiya-nile-cruise', 'lake-naser-nile-cruises', 'day-tour', 'luxor-day-tours', 'cairo-day-tours', 'hurghada-day-tour', 'tour-packages'];

    public static function protected(array $term): bool
    {
        return $term['taxonomy'] === 'activities' && in_array($term['slug'], self::PROTECTED_ACTIVITY_SLUGS, true);
    }

    public static function indexPath(string $taxonomy): string
    {
        return ['destination' => '/destinations/', 'activities' => '/activities/', 'trip_types' => '/trip-types/'][$taxonomy];
    }

    public static function find(int $id): array
    {
        return Db::one('SELECT * FROM terms WHERE id = ?', [$id]) ?? throw new HttpException(404);
    }

    public static function input(?array $old): array
    {
        $d = ['taxonomy' => $old['taxonomy'] ?? Request::str('taxonomy'), 'parent_id' => Request::str('parent_id')];
        foreach (['name', 'slug', 'description', 'seo_title', 'seo_description'] as $field) $d[$field] = Request::str($field);
        return $d;
    }

    public static function validate(array $d, ?array $old): array
    {
        $errors = [];
        if (!isset(self::TAXONOMIES[$d['taxonomy']])) $errors[] = 'اختر نوع التصنيف.';
        if (trim(strip_tags($d['name'])) === '' || mb_strlen($d['name']) > 190) $errors[] = 'اكتب اسم التصنيف حتى 190 حرفًا.';
        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $d['slug']) || strlen($d['slug']) > 190) $errors[] = 'الرابط المختصر غير صالح.';
        elseif (Db::value('SELECT id FROM terms WHERE taxonomy = ? AND slug = ? AND id <> ?', [$d['taxonomy'], $d['slug'], $old['id'] ?? 0])) $errors[] = 'الرابط مستخدم في هذا النوع.';
        if ($old && self::protected($old) && $d['slug'] !== $old['slug']) $errors[] = 'لا يمكن تغيير رابط صفحة أساسية للموقع.';
        foreach (['seo_title' => 255, 'seo_description' => 500, 'description' => 65000] as $field => $max) if (mb_strlen($d[$field]) > $max) $errors[] = "الحقل $field يتجاوز $max حرفًا.";
        if (strlen($d['description']) > 65535) $errors[] = 'الوصف طويل جدًا.';
        if ($d['parent_id'] !== '') {
            $parent = ctype_digit($d['parent_id']) ? Db::one('SELECT * FROM terms WHERE id = ?', [$d['parent_id']]) : null;
            if ($d['taxonomy'] === 'destination' || !$parent || $parent['taxonomy'] !== $d['taxonomy'] || $parent['parent_id'] !== null || (int) $d['parent_id'] === (int) ($old['id'] ?? 0)) $errors[] = 'التصنيف الأب غير صالح؛ يسمح بمستوى واحد فقط.';
            if ($old && Db::value('SELECT id FROM terms WHERE parent_id = ?', [$old['id']])) $errors[] = 'لا يمكن نقل تصنيف له أبناء تحت أب آخر.';
        }
        return $errors;
    }

    public static function save(array $d, ?array $old): int
    {
        return Db::tx(function () use ($d, $old): int {
            // Serialise hierarchy edits, including parent changes and child creation.
            Db::all('SELECT id FROM terms ORDER BY id FOR UPDATE');
            $old = $old ? self::find((int) $old['id']) : null;
            $errors = self::validate($d, $old);
            if ($errors) throw new \DomainException(implode("\n", $errors));
            $parent = $d['parent_id'] !== '' ? self::find((int) $d['parent_id']) : null;
            $row = $d;
            $row['parent_id'] = $parent['id'] ?? null;
            foreach (['name', 'description', 'seo_title', 'seo_description'] as $field) $row[$field] = strip_tags($row[$field]);
            $row['url'] = self::indexPath($d['taxonomy']) . ($parent ? $parent['slug'] . '/' : '') . $d['slug'] . '/';
            if (strlen($row['url']) > 255) throw new \DomainException('الرابط الكامل يتجاوز 255 حرفًا.');
            $children = $old ? Db::all('SELECT * FROM terms WHERE parent_id = ?', [$old['id']]) : [];
            foreach ($children as $child) if (strlen(self::indexPath($d['taxonomy']) . $d['slug'] . '/' . $child['slug'] . '/') > 255) throw new \DomainException('رابط أحد التصنيفات الفرعية يتجاوز 255 حرفًا.');
            if ($old) {
                $id = (int) $old['id'];
                Db::update('terms', $row, 'id = ?', [$id]);
                Redirects::moved($old['url'], $row['url']);
                foreach ($children as $child) {
                    $url = self::indexPath($d['taxonomy']) . $d['slug'] . '/' . $child['slug'] . '/';
                    Db::update('terms', ['url' => $url], 'id = ?', [$child['id']]);
                    Redirects::moved($child['url'], $url);
                    Redirects::claim($url);
                }
            } else $id = Db::insert('terms', $row);
            Redirects::claim($row['url']);
            Audit::log($old ? 'update' : 'create', 'term', $id, 'حفظ التصنيف');
            return $id;
        });
    }

    public static function delete(int $id): void
    {
        Db::tx(function () use ($id): void {
            Db::all('SELECT id FROM terms ORDER BY id FOR UPDATE');
            $term = self::find($id);
            if (self::protected($term)) throw new \DomainException('لا يمكن حذف صفحة أساسية للموقع.');
            if (Db::value('SELECT id FROM terms WHERE parent_id = ?', [$id])) throw new \DomainException('لا يمكن حذف تصنيف له أبناء.');
            $target = $term['parent_id'] ? self::find((int) $term['parent_id'])['url'] : self::indexPath($term['taxonomy']);
            Redirects::moved($term['url'], $target);
            Db::run('DELETE FROM terms WHERE id = ?', [$id]);
            Audit::log('delete', 'term', $id, 'حذف التصنيف');
        });
    }
}
