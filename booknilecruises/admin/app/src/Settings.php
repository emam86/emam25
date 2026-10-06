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

    /** SETTING_RULES from site/src/lib/export.mjs. */
    private const RULES = [
        'email' => '/^(?:[^\s@<>"]+@[^\s@<>"]+\.[A-Za-z]{2,})?$/uD',
        'whatsapp' => '/^[0-9]{0,20}$/D',
        'phone_display' => '/^[+0-9 ()-]{0,30}$/D',
        'phone_alt' => '/^[+0-9 ()-]{0,30}$/D',
        'address' => '/^[^<>]{0,200}$/usD',
        'google_site_verification' => '/^[A-Za-z0-9_-]{0,100}$/D',
        'bing_site_verification' => '/^[A-Za-z0-9_-]{0,100}$/D',
        'ga4_id' => '/^(?:G-[A-Z0-9]{4,20})?$/D',
    ];

    public static function input(): array
    {
        $d = [];
        foreach (self::SITE as $key => $default) $d[$key] = Request::str($key);
        foreach (['google_site_verification' => 'google-site-verification', 'bing_site_verification' => 'msvalidate.01'] as $key => $name) {
            if (!str_starts_with($d[$key], '<')) continue;
            $doc = new \DOMDocument();
            $previous = libxml_use_internal_errors(true);
            $doc->loadHTML($d[$key], LIBXML_NONET);
            libxml_clear_errors(); libxml_use_internal_errors($previous);
            $meta = $doc->getElementsByTagName('meta');
            if ($meta->length === 1 && $meta->item(0)->hasAttribute('content') && $meta->item(0)->getAttribute('name') === $name) $d[$key] = $meta->item(0)->getAttribute('content');
        }
        return $d;
    }

    public static function saveSite(array $d): void
    {
        $errors = [];
        foreach (self::RULES as $key => $rule) if (!preg_match($rule, $d[$key] ?? '')) $errors[] = "قيمة $key غير صالحة.";
        if ($errors) throw new \DomainException(implode("\n", $errors));
        Db::tx(function () use ($d): void {
            self::reset();
            $old = self::site();
            foreach (self::SITE as $key => $default) self::set($key, $d[$key]);
            Audit::log('update', 'settings', null, 'عدّل إعدادات الموقع', ['old' => $old, 'new' => $d]);
        });
        self::reset();
    }

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
