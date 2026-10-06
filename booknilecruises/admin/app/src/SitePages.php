<?php
declare(strict_types=1);

namespace Bnc;

final class SitePages
{
    public const FIXED = ['/', '/trip/', '/destinations/', '/activities/', '/trip-types/', '/about-us/', '/contact-us/', '/faq/', '/transfers/', '/terms-and-conditions/', '/blog/', '/nile-cruise/', '/standard-5-star-nile-cruises/', '/deluxe-nile-cruises/', '/luxury-nile-cruises/', '/luxury-dahabiya-nile-cruise-packages/', '/lake-nasser-nile-cruises/', '/day-tours/', '/luxor-day-tours/', '/cairo-day-tours/', '/hurghada-day-tours/', '/egypt-tour-packages/'];

    /** Same SITE_PATH as site/src/lib/export.mjs; database paths are at most 255 characters. */
    public static function validPath(string $path): bool
    {
        return strlen($path) <= 255 && (bool) preg_match('#^/(?:[A-Za-z0-9._~%-]+/)*[A-Za-z0-9._~%-]*$#D', $path);
    }

    /** Content owns its SEO fields; do not shadow them with an override. */
    public static function editor(string $path): ?array
    {
        if (preg_match('#^/trip/([^/]+)/$#D', $path, $m)) {
            $id = Db::value('SELECT id FROM trips WHERE slug = ?', [$m[1]]);
            return ['path' => $id ? "/trips/$id/edit" : '/trips', 'label' => 'عدّل SEO على صفحة الرحلة'];
        }
        if ($id = Db::value('SELECT id FROM terms WHERE url = ?', [$path])) return ['path' => "/terms/$id/edit", 'label' => 'عدّل SEO على صفحة التصنيف'];
        if ($id = Db::value('SELECT id FROM posts WHERE url = ?', [$path])) return ['path' => "/posts/$id/edit", 'label' => 'عدّل SEO على صفحة المقال'];
        return null;
    }

    public static function live(string $path): bool
    {
        if (preg_match('#^/trip/([^/]+)/$#D', $path, $m) && Db::value("SELECT id FROM trips WHERE slug = ? AND status = 'published'", [$m[1]])) return true;
        if (Db::value("SELECT id FROM posts WHERE url = ? AND status = 'published' AND (published_at IS NULL OR published_at <= ?)", [$path, date('Y-m-d H:i:s')])) return true;
        return (bool) Db::value('SELECT id FROM terms WHERE url = ?', [$path]);
    }

    public static function sitemap(): array
    {
        $overrides = array_column(Db::all('SELECT * FROM seo_overrides'), null, 'path');
        $groups = ['الصفحات الثابتة' => [], 'الرحلات' => [], 'المقالات' => [], 'التصنيفات' => []];
        $excluded = [];
        $add = function (string $group, string $path, string $reason = '') use (&$groups, &$excluded, $overrides): void {
            if ($reason === '' && !empty($overrides[$path]['noindex'])) $reason = 'عدم الفهرسة (SEO الصفحة)';
            if ($reason !== '') $excluded[] = ['path' => $path, 'reason' => $reason];
            else $groups[$group][] = $path;
        };
        foreach (self::FIXED as $path) $add('الصفحات الثابتة', $path);
        foreach (Db::all('SELECT * FROM trips ORDER BY title') as $trip) $add('الرحلات', '/trip/' . $trip['slug'] . '/', $trip['status'] !== 'published' ? 'مسودة' : ($trip['noindex'] ? 'عدم الفهرسة' : ''));
        foreach (Db::all('SELECT * FROM posts ORDER BY published_at') as $post) $add('المقالات', $post['url'], $post['status'] !== 'published' ? 'مسودة' : ($post['published_at'] && strtotime($post['published_at']) > time() ? 'مجدول: لم يحن موعد النشر' : ($post['noindex'] ? 'عدم الفهرسة' : '')));
        foreach (Db::all("SELECT t.*, (SELECT COUNT(*) FROM trip_terms tt JOIN trips p ON p.id = tt.trip_id WHERE tt.term_id = t.id AND p.status = 'published') AS published_count FROM terms t ORDER BY t.name") as $term) $add('التصنيفات', $term['url'], $term['published_count'] ? '' : 'لا توجد رحلات منشورة');
        foreach ($groups as &$paths) $paths = array_values(array_unique($paths));
        unset($paths);
        return compact('groups', 'excluded');
    }
}
