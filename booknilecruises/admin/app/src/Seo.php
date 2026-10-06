<?php
declare(strict_types=1);

namespace Bnc;

final class Seo
{
    public static function save(array $d): void
    {
        $errors = [];
        if (!SitePages::validPath($d['path'])) $errors[] = 'مسار الصفحة غير صالح.';
        if (SitePages::editor($d['path'])) $errors[] = 'عدّل SEO على صفحة الرحلة أو التصنيف أو المقال.';
        foreach (['title' => 255, 'description' => 500] as $field => $max) if (mb_strlen($d[$field]) > $max) $errors[] = "الحقل $field يتجاوز $max حرفًا.";
        if ($errors) throw new \DomainException(implode("\n", $errors));
        Db::tx(function () use ($d): void {
            $old = Db::one('SELECT * FROM seo_overrides WHERE path = ? FOR UPDATE', [$d['path']]);
            $row = ['title' => trim(strip_tags($d['title'])), 'description' => trim(strip_tags($d['description'])), 'noindex' => $d['noindex']];
            $clear = $row['title'] === '' && $row['description'] === '' && !$row['noindex'];
            if ($clear) Db::run('DELETE FROM seo_overrides WHERE path = ?', [$d['path']]);
            else Db::run('INSERT INTO seo_overrides (path, title, description, noindex) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description), noindex = VALUES(noindex)', [$d['path'], $row['title'], $row['description'], $row['noindex']]);
            Audit::log($clear ? 'delete' : ($old ? 'update' : 'create'), 'seo', null, 'حفظ SEO الصفحة', ['path' => $d['path'], 'old' => $old, 'new' => $clear ? null : $row]);
        });
    }
}
