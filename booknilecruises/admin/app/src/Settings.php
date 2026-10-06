<?php
declare(strict_types=1);

namespace Bnc;

/** Site-wide settings kept in the `settings` table, with their defaults. */
final class Settings
{
    /** Keys published to the site (see site/src/lib/export.mjs) and their defaults. */
    public const SITE = [
        'email' => 'info@booknilecruises.net',
        'whatsapp' => '201096611124',
        'phone_display' => '+20 109 661 1124',
        'phone_alt' => '+20 101 800 3960',
        'address' => 'Khaled Ibn El Waleed St., Luxor, Egypt',
        'google_site_verification' => 'SsaoNo-o-vaVrxWj1PFiSWLm9JcNNGWC_Bs663QgEbs',
        'bing_site_verification' => '',
        'ga4_id' => '',
    ];

    private static ?array $cache = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        self::$cache ??= array_column(Db::all('SELECT `key`, `value` FROM settings'), 'value', 'key');
        return self::$cache[$key] ?? $default ?? (self::SITE[$key] ?? null);
    }

    public static function set(string $key, ?string $value): void
    {
        Db::run('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)', [$key, $value]);
        self::$cache = null;
    }

    /** The settings the public site uses, defaults filled in. */
    public static function site(): array
    {
        $out = [];
        foreach (self::SITE as $key => $default) $out[$key] = self::get($key, $default);
        return $out;
    }

    public static function reset(): void
    {
        self::$cache = null;
    }
}
