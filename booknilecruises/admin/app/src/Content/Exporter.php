<?php
declare(strict_types=1);

namespace Bnc\Content;

use Bnc\Db;
use Bnc\Settings;

/**
 * Builds the content export the site is built from. The format is defined in
 * booknilecruises/site/src/lib/export.mjs; keep the two in step (version 1).
 * Only published trips and posts are included, and posts only once their publish time has come.
 */
final class Exporter
{
    public const VERSION = 1;

    public static function build(): array
    {
        $trips = Db::all("SELECT * FROM trips WHERE status = 'published' ORDER BY sort_order, id");
        $termIds = [];
        foreach (Db::all('SELECT trip_id, term_id FROM trip_terms ORDER BY trip_id, position, term_id') as $row) {
            $termIds[(int) $row['trip_id']][] = (int) $row['term_id'];
        }
        // Posts scheduled for later stay out until their time comes (the public site reads them when their deadline arrives).
        $posts = Db::all("SELECT * FROM posts WHERE status = 'published' AND (published_at IS NULL OR published_at <= ?) ORDER BY published_at, id", [date('Y-m-d H:i:s')]);

        return [
            'version' => self::VERSION,
            'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'settings' => Settings::site(),
            'media' => array_map(self::media(...), Db::all('SELECT * FROM media ORDER BY id')),
            'terms' => array_map(self::term(...), Db::all('SELECT * FROM terms ORDER BY sort_order, id')),
            'trips' => array_map(fn ($t) => self::trip($t, $termIds[(int) $t['id']] ?? []), $trips),
            'posts' => array_map(self::post(...), $posts),
            'seo_overrides' => array_map(fn ($o) => [
                'path' => $o['path'],
                'title' => self::nullable($o['title']),
                'description' => self::nullable($o['description']),
                'noindex' => (bool) $o['noindex'],
            ], Db::all('SELECT * FROM seo_overrides ORDER BY path')),
            'redirects' => array_map(fn ($r) => ['from' => $r['from_path'], 'to' => $r['to_path']], Db::all('SELECT * FROM redirects ORDER BY id')),
        ];
    }

    public static function json(): string
    {
        return json_encode(self::build(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function media(array $m): array
    {
        $sizes = json_decode((string) ($m['sizes'] ?? '{}'), true);
        return [
            'id' => (int) $m['id'],
            'path' => $m['path'],
            'width' => self::int($m['width']),
            'height' => self::int($m['height']),
            'alt' => (string) $m['alt'],
            'mime' => $m['mime'],
            'sizes' => is_array($sizes) && $sizes ? $sizes : new \stdClass(),
        ];
    }

    private static function term(array $t): array
    {
        return [
            'id' => (int) $t['id'],
            'taxonomy' => $t['taxonomy'],
            'slug' => $t['slug'],
            'name' => $t['name'],
            'parent_id' => self::int($t['parent_id']),
            'url' => $t['url'],
            'description' => (string) $t['description'],
            'seo_title' => (string) $t['seo_title'],
            'seo_description' => (string) $t['seo_description'],
        ];
    }

    private static function trip(array $t, array $termIds): array
    {
        return [
            'id' => (int) $t['id'],
            'slug' => $t['slug'],
            'title' => $t['title'],
            'status' => $t['status'],
            'excerpt' => (string) $t['excerpt'],
            'code' => (string) $t['code'],
            'price' => self::number($t['price']),
            'sale_price' => self::number($t['sale_price']),
            'currency' => $t['currency'],
            'duration_days' => self::int($t['duration_days']),
            'duration_nights' => self::int($t['duration_nights']),
            'min_pax' => self::int($t['min_pax']),
            'max_pax' => self::int($t['max_pax']),
            'overview_html' => (string) $t['overview_html'],
            'highlights' => self::list($t['highlights']),
            'itinerary' => self::list($t['itinerary']),
            'includes' => self::list($t['includes']),
            'excludes' => self::list($t['excludes']),
            'faqs' => self::list($t['faqs']),
            'image_id' => self::int($t['image_id']),
            'gallery' => array_map('intval', self::list($t['gallery'])),
            'featured' => (bool) $t['featured'],
            'seo_title' => (string) $t['seo_title'],
            'seo_description' => (string) $t['seo_description'],
            'noindex' => (bool) $t['noindex'],
            'term_ids' => $termIds,
            'updated_at' => self::time($t['updated_at']),
        ];
    }

    private static function post(array $p): array
    {
        return [
            'id' => (int) $p['id'],
            'slug' => $p['slug'],
            'url' => $p['url'],
            'title' => $p['title'],
            'status' => $p['status'],
            'excerpt' => (string) $p['excerpt'],
            'content_html' => $p['content_html'],
            'image_id' => self::int($p['image_id']),
            'seo_title' => (string) $p['seo_title'],
            'seo_description' => (string) $p['seo_description'],
            'noindex' => (bool) $p['noindex'],
            'published_at' => self::time($p['published_at'] ?? $p['created_at']),
            'updated_at' => self::time($p['updated_at']),
        ];
    }

    private static function int(mixed $v): ?int
    {
        return $v === null || $v === '' ? null : (int) $v;
    }

    /** DECIMAL comes back as a string; whole amounts are exported as integers. */
    private static function number(mixed $v): int|float|null
    {
        if ($v === null || $v === '') return null;
        $f = (float) $v;
        return floor($f) === $f ? (int) $f : $f;
    }

    private static function list(mixed $json): array
    {
        $v = json_decode((string) ($json ?? '[]'), true);
        return is_array($v) ? array_values($v) : [];
    }

    private static function time(?string $dt): ?string
    {
        return $dt === null ? null : str_replace(' ', 'T', $dt);
    }

    private static function nullable(?string $v): ?string
    {
        return $v === null || $v === '' ? null : $v;
    }
}
