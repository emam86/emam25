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
            ['label' => 'الصور', 'path' => '/media', 'perm' => 'media.upload'],
            ['label' => 'المستخدمين', 'path' => '/users', 'perm' => 'users.manage'],
            ['label' => 'الأدوار والصلاحيات', 'path' => '/roles', 'perm' => 'users.manage'],
            ['label' => 'سجل العمليات', 'path' => '/audit', 'perm' => 'audit.view'],
            ['label' => 'حسابي', 'path' => '/account', 'perm' => null],
        ];
    }

    public static function visible(): array
    {
        return array_values(array_filter(self::items(), fn ($i) => $i['perm'] === null || Auth::can($i['perm'])));
    }
}
