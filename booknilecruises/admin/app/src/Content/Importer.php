<?php
declare(strict_types=1);

namespace Bnc\Content;

use Bnc\Audit;
use Bnc\Db;
use Bnc\Html;
use Bnc\Settings;
use RuntimeException;

/**
 * One-time import of the existing site (the seed export written by
 * site/scripts/export-seed.mjs) into an empty panel. Keeps the WordPress ids,
 * so photo and category references stay valid, and sanitises every HTML field.
 */
final class Importer
{
    /** @return array<string,int> counts per table */
    public static function run(array $export, string $actor = 'import'): array
    {
        if (($export['version'] ?? null) !== Exporter::VERSION) throw new RuntimeException('ملف الاستيراد بإصدار غير معروف.');
        foreach (['media', 'terms', 'trips', 'posts'] as $table) {
            if ((int) Db::value("SELECT COUNT(*) FROM $table") > 0) {
                throw new RuntimeException("الاستيراد يعمل على لوحة فارغة فقط، وجدول $table فيه بيانات.");
            }
        }
        return Db::tx(function () use ($export, $actor): array {
            foreach ($export['media'] as $m) {
                Db::insert('media', [
                    'id' => (int) $m['id'],
                    'path' => self::str($m['path'], 255),
                    'width' => $m['width'],
                    'height' => $m['height'],
                    'mime' => $m['mime'],
                    'alt' => self::str($m['alt'] ?? '', 255),
                    'sizes' => json_encode($m['sizes'] ?: new \stdClass(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]);
            }
            // Parents first, so the self-referencing foreign key is satisfied.
            $terms = $export['terms'];
            usort($terms, fn ($a, $b) => (int) ($a['parent_id'] !== null) <=> (int) ($b['parent_id'] !== null));
            $order = array_flip(array_map(fn ($t) => $t['id'], $export['terms']));
            foreach ($terms as $t) {
                Db::insert('terms', [
                    'id' => (int) $t['id'],
                    'taxonomy' => $t['taxonomy'],
                    'slug' => $t['slug'],
                    'name' => $t['name'],
                    'parent_id' => $t['parent_id'],
                    'url' => $t['url'],
                    'description' => $t['description'] ?? '',
                    'seo_title' => $t['seo_title'] ?? '',
                    'seo_description' => $t['seo_description'] ?? '',
                    'sort_order' => $order[$t['id']],
                ]);
            }
            foreach ($export['trips'] as $i => $t) {
                Db::insert('trips', [
                    'id' => (int) $t['id'],
                    'legacy_id' => (int) $t['id'],
                    'slug' => $t['slug'],
                    'title' => $t['title'],
                    'status' => $t['status'] === 'published' ? 'published' : 'draft',
                    'excerpt' => $t['excerpt'],
                    'code' => self::str($t['code'] ?? '', 40),
                    'price' => $t['price'],
                    'sale_price' => $t['sale_price'],
                    'currency' => $t['currency'] ?: 'USD',
                    'duration_days' => $t['duration_days'],
                    'duration_nights' => $t['duration_nights'],
                    'min_pax' => $t['min_pax'],
                    'max_pax' => $t['max_pax'],
                    'overview_html' => Html::clean($t['overview_html']),
                    'highlights' => self::json(self::lines($t['highlights'])),
                    'itinerary' => self::json(self::rows(array_map(fn ($d) => ['title' => trim($d['title']), 'html' => Html::clean($d['html'])], $t['itinerary']))),
                    'includes' => self::json(self::lines($t['includes'])),
                    'excludes' => self::json(self::lines($t['excludes'])),
                    'faqs' => self::json(self::rows(array_map(fn ($f) => ['q' => trim($f['q']), 'a' => Html::clean($f['a'])], $t['faqs']))),
                    'image_id' => $t['image_id'],
                    'gallery' => self::json($t['gallery']),
                    'featured' => $t['featured'] ? 1 : 0,
                    'sort_order' => $i,
                    'seo_title' => self::str($t['seo_title'] ?? '', 255),
                    'seo_description' => self::str($t['seo_description'] ?? '', 500),
                    'noindex' => !empty($t['noindex']) ? 1 : 0,
                    'created_at' => self::time($t['updated_at']),
                    'updated_at' => self::time($t['updated_at']),
                ]);
                foreach (array_values($t['term_ids']) as $pos => $termId) {
                    Db::insert('trip_terms', ['trip_id' => (int) $t['id'], 'term_id' => (int) $termId, 'position' => $pos]);
                }
            }
            foreach ($export['posts'] as $p) {
                Db::insert('posts', [
                    'id' => (int) $p['id'],
                    'slug' => $p['slug'],
                    'url' => $p['url'],
                    'title' => $p['title'],
                    'status' => $p['status'] === 'published' ? 'published' : 'draft',
                    'excerpt' => $p['excerpt'] ?? '',
                    'content_html' => Html::clean($p['content_html']),
                    'image_id' => $p['image_id'],
                    'seo_title' => self::str($p['seo_title'] ?? '', 255),
                    'seo_description' => self::str($p['seo_description'] ?? '', 500),
                    'noindex' => !empty($p['noindex']) ? 1 : 0,
                    'source' => 'import',
                    'published_at' => self::time($p['published_at']),
                    'created_at' => self::time($p['published_at']),
                    'updated_at' => self::time($p['updated_at']),
                ]);
            }
            foreach ($export['settings'] ?? [] as $key => $value) {
                if (array_key_exists($key, Settings::SITE)) Settings::set($key, (string) $value);
            }
            foreach ($export['seo_overrides'] ?? [] as $o) {
                Db::insert('seo_overrides', ['path' => $o['path'], 'title' => $o['title'], 'description' => $o['description'], 'noindex' => $o['noindex'] ? 1 : 0]);
            }
            foreach ($export['redirects'] ?? [] as $r) {
                Db::insert('redirects', ['from_path' => $r['from'], 'to_path' => $r['to'], 'source' => 'manual']);
            }
            $counts = [
                'media' => count($export['media']),
                'terms' => count($export['terms']),
                'trips' => count($export['trips']),
                'posts' => count($export['posts']),
            ];
            Audit::log('import', 'site', null, 'استيراد محتوى الموقع الحالي', $counts, $actor);
            return $counts;
        });
    }

    private static function json(array $v): string
    {
        return json_encode(array_values($v), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** Plain-text list items as the trip form stores them: trimmed, no empty ones. */
    private static function lines(array $items): array
    {
        return array_values(array_filter(array_map(fn ($v) => trim((string) $v), $items), fn ($v) => $v !== ''));
    }

    /** Drop rows WordPress left completely empty. */
    private static function rows(array $rows): array
    {
        return array_values(array_filter($rows, fn ($r) => implode('', $r) !== ''));
    }

    private static function str(string $v, int $max): string
    {
        return mb_substr($v, 0, $max);
    }

    /** "2025-12-01T17:38:21" (WordPress, site time) → DATETIME. */
    private static function time(?string $iso): ?string
    {
        if ($iso === null || $iso === '') return null;
        $t = \DateTime::createFromFormat('Y-m-d\TH:i:s', substr($iso, 0, 19));
        if (!$t) throw new RuntimeException("تاريخ غير صالح: $iso");
        return $t->format('Y-m-d H:i:s');
    }
}
