<?php
declare(strict_types=1);

namespace Bnc;

/** Every permission the panel knows about, grouped for the role editor. */
final class Permissions
{
    public const GROUPS = [
        'الرحلات' => [
            'trips.view' => 'عرض الرحلات',
            'trips.create' => 'إضافة رحلات',
            'trips.edit' => 'تعديل الرحلات والتصنيفات',
            'trips.delete' => 'حذف الرحلات',
        ],
        'المقالات' => [
            'posts.view' => 'عرض المقالات',
            'posts.create' => 'إضافة مقالات',
            'posts.edit' => 'تعديل المقالات',
            'posts.delete' => 'حذف المقالات',
        ],
        'الصور' => [
            'media.upload' => 'رفع الصور',
            'media.delete' => 'حذف الصور',
        ],
        'SEO' => [
            'seo.edit' => 'تعديل SEO الصفحات وفحص SEO',
            'sitemap.edit' => 'السايت ماب والتحويلات',
        ],
        'الاستفسارات' => [
            'enquiries.view' => 'عرض الاستفسارات',
            'enquiries.manage' => 'تحديث وحذف الاستفسارات',
        ],
        'النظام' => [
            'settings.edit' => 'الإعدادات (التواصل، Google verification، Analytics)',
            'api.manage' => 'مفاتيح الـ API والـ Webhooks (n8n / Make)',
            'users.manage' => 'المستخدمين والأدوار',
            'audit.view' => 'سجل العمليات',
        ],
    ];

    /** Roles created on install. The owner role always has every permission. */
    public const PRESETS = [
        'owner' => ['المالك', ['*']],
        'admin' => ['مدير', [
            'trips.view', 'trips.create', 'trips.edit', 'trips.delete',
            'posts.view', 'posts.create', 'posts.edit', 'posts.delete',
            'media.upload', 'media.delete', 'seo.edit', 'sitemap.edit',
            'enquiries.view', 'enquiries.manage', 'settings.edit',
            'api.manage', 'users.manage', 'audit.view',
        ]],
        'editor' => ['محرر', [
            'trips.view', 'trips.create', 'trips.edit',
            'posts.view', 'posts.create', 'posts.edit', 'media.upload',
        ]],
        'seo' => ['SEO', ['trips.view', 'trips.edit', 'posts.view', 'posts.edit', 'seo.edit', 'sitemap.edit']],
        'sales' => ['مبيعات', ['trips.view', 'enquiries.view', 'enquiries.manage']],
    ];

    /** @return array<string,string> key => label */
    public static function all(): array
    {
        return array_merge(...array_values(self::GROUPS));
    }

    public static function exists(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    /** Keep only known permission keys, in canonical order. */
    public static function clean(array $keys): array
    {
        return array_values(array_filter(array_keys(self::all()), fn ($k) => in_array($k, $keys, true)));
    }
}
