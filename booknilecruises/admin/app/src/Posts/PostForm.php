<?php
declare(strict_types=1);

namespace Bnc\Posts;

use Bnc\{Db, Html, Request};

final class PostForm
{
    public const FIELDS = ['title', 'slug', 'status', 'published_at', 'excerpt', 'content_html', 'image_id', 'seo_title', 'seo_description'];

    public static function defaults(): array
    {
        return array_replace(array_fill_keys(self::FIELDS, ''), ['id' => null, 'updated_at' => '', 'noindex' => 0, 'status' => 'draft', 'published_at' => date('Y-m-d\TH:i')]);
    }

    public static function input(): array
    {
        $d = self::defaults();
        foreach ([...self::FIELDS, 'updated_at'] as $field) $d[$field] = Request::str($field);
        $d['noindex'] = Request::bool('noindex') ? 1 : 0;
        return $d;
    }

    public static function date(string $value): ?\DateTimeImmutable
    {
        foreach (['!Y-m-d\TH:i', '!Y-m-d\TH:i:s', '!Y-m-d H:i:s'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date && (!$errors || (!$errors['warning_count'] && !$errors['error_count'])) && $date->format(substr($format, 1)) === $value && (int) $date->format('Y') >= 1000) return $date;
        }
        return null;
    }

    public static function row(array $d, int $id): array
    {
        $errors = [];
        foreach (['title' => 255, 'excerpt' => 500, 'seo_title' => 255, 'seo_description' => 500] as $field => $max) {
            if (mb_strlen($d[$field]) > $max) $errors[] = "الحقل $field يتجاوز $max حرفًا.";
            $d[$field] = trim(strip_tags($d[$field]));
        }
        if ($d['title'] === '') $errors[] = 'عنوان المقال مطلوب.';
        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/D', $d['slug']) || strlen($d['slug']) > 190) $errors[] = 'الرابط المختصر غير صالح.';
        if (!in_array($d['status'], ['draft', 'published'], true)) $errors[] = 'اختر حالة المقال.';
        $date = self::date($d['published_at']);
        if (!$date) $errors[] = 'اكتب تاريخ ووقت نشر صالحين.';
        $url = $date ? $date->format('/Y/m/d/') . $d['slug'] . '/' : '';
        if (Db::value('SELECT id FROM posts WHERE url = ? AND id <> ?', [$url, $id])) $errors[] = 'رابط المقال مستخدم بالفعل.';
        if ($d['image_id'] !== '' && (!ctype_digit((string) $d['image_id']) || !Db::value('SELECT id FROM media WHERE id = ?', [$d['image_id']]))) $errors[] = 'الصورة غير موجودة.';
        if (strlen($d['content_html']) > 16777215) $errors[] = 'المحتوى طويل جدًا.';
        if ($errors) throw new \DomainException(implode("\n", $errors));
        $row = array_intersect_key($d, array_flip([...self::FIELDS, 'noindex']));
        $row['published_at'] = $date->format('Y-m-d H:i:s');
        $row['url'] = $url;
        $row['image_id'] = $d['image_id'] === '' ? null : (int) $d['image_id'];
        $row['content_html'] = Html::clean($d['content_html']);
        return $row;
    }
}
