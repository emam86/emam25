<?php
declare(strict_types=1);

namespace Bnc\Trips;

use Bnc\{Db, Html, Request};

final class TripForm
{
    public const JSON_FIELDS = ['highlights', 'includes', 'excludes', 'itinerary', 'faqs', 'gallery'];
    public const TEXT_FIELDS = ['title', 'slug', 'status', 'code', 'excerpt', 'price', 'sale_price', 'currency', 'duration_days', 'duration_nights', 'min_pax', 'max_pax', 'overview_html', 'seo_title', 'seo_description'];

    public static function defaults(): array
    {
        return array_fill_keys(self::TEXT_FIELDS, '') + array_fill_keys(self::JSON_FIELDS, []) + [
            'id' => null, 'updated_at' => '', 'image_id' => '', 'term_ids' => [], 'featured' => 0, 'noindex' => 0,
        ];
    }

    public static function load(array $row): array
    {
        foreach (self::JSON_FIELDS as $field) $row[$field] = json_decode($row[$field] ?? '[]', true) ?: [];
        $row['term_ids'] = array_column(Db::all('SELECT term_id FROM trip_terms WHERE trip_id = ? ORDER BY position, term_id', [$row['id']]), 'term_id');
        return $row;
    }

    public static function input(): array
    {
        $d = self::defaults();
        foreach (self::TEXT_FIELDS as $field) $d[$field] = Request::str($field);
        foreach (['featured', 'noindex'] as $field) $d[$field] = Request::bool($field) ? 1 : 0;
        $d['updated_at'] = Request::str('updated_at');
        $d['image_id'] = Request::str('image_id');
        foreach (['gallery', 'term_ids'] as $field) $d[$field] = array_values(array_filter(Request::list($field), fn ($v) => trim($v) !== '')); 
        // Preserve the loaded order when the non-JavaScript checkboxes are submitted.
        $order = array_values(array_intersect(Request::list('term_order'), $d['term_ids']));
        $d['term_ids'] = array_values(array_unique([...$order, ...$d['term_ids']]));
        foreach (['highlights', 'includes', 'excludes'] as $field) $d[$field] = array_values(array_filter(array_map('trim', preg_split('/\R/u', Request::str($field)) ?: []), fn ($v) => $v !== ''));
        foreach (['itinerary' => ['title', 'html'], 'faqs' => ['q', 'a']] as $field => $keys) {
            $rows = $_POST[$field] ?? [];
            if (!is_array($rows)) $rows = [];
            foreach ($rows as $row) {
                if (!is_array($row)) continue;
                $item = [];
                foreach ($keys as $key) $item[$key] = is_string($row[$key] ?? null) ? trim($row[$key]) : '';
                if (implode('', $item) !== '') $d[$field][] = $item;
            }
        }
        return $d;
    }

    public static function validate(array $d, int $id = 0): array
    {
        $errors = [];
        foreach (['title' => 255, 'code' => 40, 'excerpt' => 500, 'seo_title' => 255, 'seo_description' => 500] as $field => $max) {
            if (mb_strlen($d[$field]) > $max) $errors[] = "الحقل $field يتجاوز $max حرفًا.";
        }
        if (trim(strip_tags($d['title'])) === '') $errors[] = 'عنوان الرحلة مطلوب.';
        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $d['slug']) || strlen($d['slug']) > 190) $errors[] = 'الرابط المختصر غير صالح.';
        elseif (Db::value('SELECT id FROM trips WHERE slug = ? AND id <> ?', [$d['slug'], $id])) $errors[] = 'الرابط المختصر مستخدم بالفعل.';
        if (!in_array($d['status'], ['draft', 'published'], true)) $errors[] = 'اختر حالة الرحلة.';
        if (!in_array($d['currency'], ['USD', 'EUR', 'GBP', 'EGP'], true)) $errors[] = 'اختر العملة.';
        // Price, duration and group size are optional: some trips are priced on request.
        foreach (['price', 'sale_price'] as $field) {
            if ($d[$field] !== '' && !preg_match('/^\d{1,8}(\.\d{1,2})?$/', $d[$field])) $errors[] = 'اكتب سعرًا صحيحًا.';
        }
        if ($d['sale_price'] !== '' && $d['price'] === '') $errors[] = 'اكتب السعر الأصلي قبل سعر العرض.';
        elseif ($d['sale_price'] !== '' && (float) $d['sale_price'] >= (float) $d['price']) $errors[] = 'سعر العرض يجب أن يكون أقل من السعر.';
        foreach (['duration_days' => [0, 365], 'duration_nights' => [0, 365], 'min_pax' => [1, 999], 'max_pax' => [1, 999]] as $field => [$min, $max]) {
            if ($d[$field] !== '' && (!ctype_digit($d[$field]) || (int) $d[$field] < $min || (int) $d[$field] > $max)) $errors[] = "قيمة $field يجب أن تكون بين $min و$max.";
        }
        if ($d['min_pax'] !== '' && $d['max_pax'] !== '' && (int) $d['min_pax'] > (int) $d['max_pax']) $errors[] = 'الحد الأدنى للمسافرين أكبر من الحد الأقصى.';
        foreach (['highlights', 'includes', 'excludes'] as $field) {
            if (count($d[$field]) > 50) $errors[] = 'الحد الأقصى للقائمة 50 بندًا.';
            foreach ($d[$field] as $text) if (mb_strlen($text) > 500) $errors[] = 'البند يتجاوز 500 حرف.';
        }
        foreach (['itinerary' => [60, 'title'], 'faqs' => [40, 'q']] as $field => [$max, $key]) {
            if (count($d[$field]) > $max) $errors[] = "عدد الصفوف يتجاوز $max.";
            foreach ($d[$field] as $row) {
                // A day may have text without a title (the site then shows just "Day N"); a FAQ needs its question.
                $missing = $field === 'faqs' ? $row['q'] === '' : $row['title'] === '' && trim(strip_tags($row['html'])) === '';
                if ($missing || mb_strlen($row[$key]) > 500) $errors[] = $field === 'faqs' ? 'اكتب السؤال (حتى 500 حرف) لكل سؤال.' : 'اكتب عنوان اليوم (حتى 500 حرف) أو نصه.';
            }
        }
        foreach ([...$d['gallery'], ...($d['image_id'] === '' ? [] : [$d['image_id']])] as $media) {
            if (!ctype_digit((string) $media) || !Db::value('SELECT id FROM media WHERE id = ?', [$media])) $errors[] = 'الصورة المختارة غير موجودة.';
        }
        foreach ($d['term_ids'] as $term) if (!ctype_digit((string) $term) || !Db::value('SELECT id FROM terms WHERE id = ?', [$term])) $errors[] = 'التصنيف غير موجود.';
        return array_unique($errors);
    }

    public static function row(array $d): array
    {
        $row = array_intersect_key($d, array_flip([...self::TEXT_FIELDS, ...self::JSON_FIELDS, 'image_id', 'featured', 'noindex']));
        foreach (['title', 'code', 'excerpt', 'seo_title', 'seo_description'] as $field) $row[$field] = strip_tags($row[$field]);
        $row['overview_html'] = Html::clean($row['overview_html']);
        foreach (['highlights', 'includes', 'excludes'] as $field) $row[$field] = array_values(array_filter(array_map(fn ($text) => trim(strip_tags($text)), $row[$field]), fn ($text) => $text !== '')); 
        foreach (['itinerary' => ['title', 'html'], 'faqs' => ['q', 'a']] as $field => [$text, $html]) {
            foreach ($row[$field] as &$item) { $item[$text] = strip_tags($item[$text]); $item[$html] = Html::clean($item[$html]); }
            unset($item);
        }
        $row['gallery'] = array_map('intval', $row['gallery']);
        foreach (self::JSON_FIELDS as $field) $row[$field] = json_encode($row[$field], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        foreach (['image_id', 'price', 'sale_price', 'duration_days', 'duration_nights', 'min_pax', 'max_pax'] as $field) if ($row[$field] === '') $row[$field] = null;
        return $row;
    }
}
