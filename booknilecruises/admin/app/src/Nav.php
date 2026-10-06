<?php
declare(strict_types=1);

namespace Bnc;

/** Sidebar entries. Later phases add their sections here. */
final class Nav
{
    /** @return list<array{label:string,path:string,perm:?string}> */
    public static function items(): array
    {
        return [
            ['label' => 'الرئيسية', 'path' => '/', 'perm' => null],
            ['label' => 'الرحلات', 'path' => '/trips', 'perm' => 'trips.view'],
            ['label' => 'التصنيفات', 'path' => '/terms', 'perm' => 'trips.edit'],
            ['label' => 'المقالات', 'path' => '/posts', 'perm' => 'posts.view'],
            ['label' => 'SEO الصفحات', 'path' => '/seo', 'perm' => 'seo.edit'],
            ['label' => 'فحص SEO', 'path' => '/seo/report', 'perm' => 'seo.edit'],
            ['label' => 'التحويلات', 'path' => '/redirects', 'perm' => 'sitemap.edit'],
            ['label' => 'السايت ماب', 'path' => '/sitemap', 'perm' => 'sitemap.edit'],
            ['label' => 'النشر', 'path' => '/publish', 'perm' => 'publish'],
            ['label' => 'الاستفسارات', 'path' => '/enquiries', 'perm' => 'enquiries.view'],
            ['label' => 'مفاتيح API', 'path' => '/api-keys', 'perm' => 'api.manage'],
            ['label' => 'Webhooks', 'path' => '/webhooks', 'perm' => 'api.manage'],
            ['label' => 'الإعدادات', 'path' => '/settings', 'perm' => 'settings.edit'],
            ['label' => 'الصور', 'path' => '/media', 'perm' => 'media.upload'],
            ['label' => 'المستخدمين', 'path' => '/users', 'perm' => 'users.manage'],
            ['label' => 'الأدوار والصلاحيات', 'path' => '/roles', 'perm' => 'users.manage'],
            ['label' => 'سجل العمليات', 'path' => '/audit', 'perm' => 'audit.view'],
            ['label' => 'حسابي', 'path' => '/account', 'perm' => null],
        ];
    }

    public static function visible(): array
    {
        $items = array_values(array_filter(self::items(), fn ($i) => $i['perm'] === null || Auth::can($i['perm'])));
        foreach ($items as &$item) if ($item['path'] === '/enquiries') $item['label'] .= ' (' . (int) Db::value("SELECT COUNT(*) FROM enquiries WHERE status = 'new'") . ')';
        return $items;
    }
}
