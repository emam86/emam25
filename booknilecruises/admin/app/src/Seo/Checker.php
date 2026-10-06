<?php
declare(strict_types=1);

namespace Bnc\Seo;

use Bnc\{Db, Settings};

/** Database-only checks of the content eligible for publication. */
final class Checker
{
    public function run(): array
    {
        $issues = [];
        $add = static function (string $severity, string $code, array $item, string $message, ?string $url = null) use (&$issues): void {
            $issues[] = ['severity' => $severity, 'code' => $code, 'entity' => $item['entity'], 'entity_id' => $item['id'], 'title' => $item['title'], 'message' => $message, 'edit_url' => $url ?? $item['edit_url']];
        };
        $trips = Db::all("SELECT * FROM trips WHERE status = 'published' ORDER BY id");
        $posts = Db::all("SELECT * FROM posts WHERE status = 'published' AND (published_at IS NULL OR published_at <= ?) ORDER BY id", [date('Y-m-d H:i:s')]);
        $terms = Db::all('SELECT * FROM terms ORDER BY id');
        $media = array_column(Db::all('SELECT id, alt FROM media'), 'alt', 'id');
        $relations = Db::all('SELECT tt.* FROM trip_terms tt JOIN trips t ON t.id = tt.trip_id WHERE t.status = ?', ['published']);
        $tripTerms = []; $usedTerms = [];
        foreach ($relations as $r) { $tripTerms[$r['trip_id']][] = $r['term_id']; $usedTerms[$r['term_id']] = true; }
        $items = []; $live = [];
        foreach (['trip' => $trips, 'post' => $posts, 'term' => $terms] as $entity => $rows) {
            foreach ($rows as $r) {
                $r['entity'] = $entity; $r['title'] = $r['title'] ?? $r['name'];
                $r['edit_url'] = '/' . ['trip' => 'trips', 'post' => 'posts', 'term' => 'terms'][$entity] . '/' . $r['id'] . '/edit';
                $r['url'] = $entity === 'trip' ? '/trip/' . $r['slug'] . '/' : $r['url'];
                $items[] = $r; $live[self::path($r['url'])] = true;
            }
        }
        $redirects = Db::all('SELECT * FROM redirects ORDER BY id');
        $redirectMap = [];
        foreach ($redirects as $redirect) $redirectMap[self::path($redirect['from_path'])] = $redirect['to_path'];
        $duplicates = [];
        foreach ($items as $r) foreach (['seo_title', 'seo_description'] as $field) {
            $value = mb_strtolower(trim((string) $r[$field]));
            if ($value !== '') $duplicates[$field][$value][] = $r;
        }
        foreach ($items as $r) {
            foreach (['seo_title', 'seo_description'] as $field) {
                $value = mb_strtolower(trim((string) $r[$field]));
                $others = array_filter($duplicates[$field][$value] ?? [], fn ($other) => $other['entity'] !== $r['entity'] || $other['id'] !== $r['id']);
                if ($others) $add('error', $field === 'seo_title' ? 'duplicate_title' : 'duplicate_description', $r, 'اكتب قيمة SEO فريدة؛ القيمة مكررة مع: ' . implode('، ', array_map(fn ($o) => $o['title'] . ' (' . $o['entity'] . ' #' . $o['id'] . ')', $others)));
            }
            if ($r['entity'] === 'term') {
                if (!isset($usedTerms[$r['id']])) $add('info', 'category_empty', $r, 'التصنيف مستبعد من خريطة الموقع؛ أضف رحلة منشورة إليه.');
                if (trim((string) $r['seo_description']) === '') $add('warning', 'category_description_missing', $r, 'أضف وصف SEO للتصنيف.');
                continue;
            }
            $title = trim((string) $r['seo_title']); $description = trim((string) $r['seo_description']);
            if ($title === '') $add('warning', 'title_missing', $r, 'أضف عنوان SEO؛ يستخدم الموقع حالياً «' . $r['title'] . ' - Book Nile cruises».');
            elseif (mb_strlen($title) > 60) $add('warning', 'title_long', $r, 'اختصر عنوان SEO إلى 60 حرفاً أو أقل.');
            if ($description === '') $add('error', 'description_missing', $r, 'أضف وصف meta للصفحة.');
            elseif (mb_strlen($description) < 70 || mb_strlen($description) > 160) $add('warning', 'description_length', $r, 'اجعل وصف meta بين 70 و160 حرفاً.');
            if (!$r['image_id']) $add('error', 'cover_missing', $r, 'أضف صورة غلاف.');
            $gallery = $r['entity'] === 'trip' ? json_decode((string) ($r['gallery'] ?? '[]'), true) ?? [] : [];
            if ($r['entity'] === 'trip') {
                if (count(array_unique($gallery)) < 3) $add('warning', 'gallery_short', $r, 'أضف ثلاث صور على الأقل إلى معرض الرحلة.');
                if (empty($tripTerms[$r['id']])) $add('error', 'category_missing', $r, 'اختر تصنيفاً للرحلة.');
                if ($r['price'] === null || $r['price'] === '') $add('warning', 'price_missing', $r, 'أضف سعر الرحلة.');
            }
            $missingAlt = array_values(array_filter(array_unique(array_filter([$r['image_id'], ...$gallery])), fn ($id) => isset($media[$id]) && trim($media[$id]) === ''));
            if ($missingAlt) $add('warning', 'alt_missing', $r, 'أضف نصاً بديلاً للصور المستخدمة: ' . count($missingAlt) . ' صورة.', '/media/' . $missingAlt[0]);
            $html = (string) ($r[$r['entity'] === 'trip' ? 'overview_html' : 'content_html'] ?? '');
            // Count what the visitor reads on the page: for a trip that is the overview plus itinerary,
            // FAQs and lists, not the overview paragraph alone.
            $pageText = $html;
            if ($r['entity'] === 'trip') {
                foreach (['itinerary' => ['title', 'html'], 'faqs' => ['q', 'a']] as $field => $keys) {
                    foreach (json_decode((string) ($r[$field] ?? '[]'), true) ?: [] as $row) foreach ($keys as $k) $pageText .= ' ' . ($row[$k] ?? '');
                }
                foreach (['highlights', 'includes', 'excludes'] as $field) $pageText .= ' ' . implode(' ', json_decode((string) ($r[$field] ?? '[]'), true) ?: []);
            }
            $words = preg_split('/\s+/u', trim(html_entity_decode(strip_tags(preg_replace('/<[^>]+>/', ' ', $pageText)), ENT_QUOTES | ENT_HTML5, 'UTF-8')), -1, PREG_SPLIT_NO_EMPTY);
            $minimum = $r['entity'] === 'trip' ? 150 : 300;
            if (count($words ?: []) < $minimum) $add('warning', 'content_short', $r, "وسّع المحتوى إلى $minimum كلمة على الأقل.");
            if ($r['noindex']) $add('info', 'noindex', $r, 'عدم الفهرسة مفعّل؛ ألغِه إذا أردت ظهور الصفحة في البحث.');
            $fields = [$html, (string) ($r['excerpt'] ?? '')];
            if ($r['entity'] === 'trip') {
                foreach (json_decode((string) ($r['itinerary'] ?? '[]'), true) ?? [] as $day) $fields[] = $day['html'] ?? '';
                foreach (json_decode((string) ($r['faqs'] ?? '[]'), true) ?? [] as $faq) $fields[] = $faq['a'] ?? '';
            }
            $links = [];
            foreach ($fields as $field) {
                if (trim($field) === '') continue;
                $doc = new \DOMDocument(); $previous = libxml_use_internal_errors(true);
                $doc->loadHTML('<?xml encoding="UTF-8">' . $field, LIBXML_NONET);
                libxml_clear_errors(); libxml_use_internal_errors($previous);
                foreach ($doc->getElementsByTagName('a') as $a) {
                    $path = self::path($a->getAttribute('href'));
                    if ($path !== null) $links[$path] = true;
                }
            }
            foreach (array_keys($links) as $path) {
                if (isset($redirectMap[$path])) $add('warning', 'link_redirected', $r, 'الرابط ' . $path . ' يؤدي إلى تحويل؛ حدّثه إلى ' . $redirectMap[$path]);
                elseif (preg_match('#^/(?:trip|activities|destinations|trip-types)/.+|^/\d{4}/\d{2}/\d{2}/.+#', $path) || Db::value('SELECT id FROM posts WHERE url = ?', [$path])) {
                    if (!isset($live[$path])) $add('error', 'broken_link', $r, 'رابط معطّل: ' . $path . '؛ صحّح الرابط أو انشر الصفحة المقصودة.');
                }
            }
        }
        foreach ($redirects as $r) {
            $item = ['entity' => 'redirect', 'id' => $r['id'], 'title' => $r['from_path'], 'edit_url' => '/redirects/' . $r['id'] . '/edit'];
            $seen = [self::path($r['from_path']) => true]; $next = self::path($r['to_path']); $chain = false; $loop = false;
            while ($next !== null && isset($redirectMap[$next])) {
                if (isset($seen[$next])) { $loop = true; break; }
                $seen[$next] = true; $chain = true; $next = self::path($redirectMap[$next]);
            }
            if ($loop) $add('error', 'redirect_loop', $item, 'أصلح حلقة التحويل واختر وجهة نهائية.');
            elseif ($chain) $add('error', 'redirect_chain', $item, 'اختصر سلسلة التحويل إلى الوجهة النهائية.');
            if (isset($live[self::path($r['from_path'])])) $add('warning', 'redirect_live', $item, 'مصدر التحويل صفحة حية؛ أزل التحويل أو غيّر مصدره.');
        }
        foreach (['google_site_verification' => ['warning', 'google_verification_missing', 'أضف رمز تحقق Google.', '/settings'], 'ga4_id' => ['info', 'ga4_missing', 'أضف معرّف GA4 لتفعيل التحليلات.', '/settings'], 'indexnow_key' => ['info', 'indexnow_missing', 'ولّد مفتاح IndexNow لإرسال الروابط.', '/seo/report']] as $key => [$severity, $code, $message, $url]) {
            // No default argument: an unset key falls back to the site default (Google's code is preset).
            if (trim((string) Settings::get($key)) === '') $add($severity, $code, ['entity' => 'settings', 'id' => $key, 'title' => $key, 'edit_url' => $url], $message);
        }
        return Db::tx(function () use ($issues): array {
            $counts = array_count_values(array_column($issues, 'severity'));
            $id = Db::insert('seo_reports', ['issues' => json_encode($issues, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'error_count' => $counts['error'] ?? 0, 'warning_count' => $counts['warning'] ?? 0]);
            $keep = array_column(Db::all('SELECT id FROM seo_reports ORDER BY id DESC LIMIT 30'), 'id');
            Db::run('DELETE FROM seo_reports WHERE id NOT IN (' . implode(',', array_fill(0, count($keep), '?')) . ')', $keep);
            return Db::one('SELECT * FROM seo_reports WHERE id = ?', [$id]);
        });
    }

    public static function path(string $url): ?string
    {
        $parts = parse_url($url);
        if ($parts === false || (isset($parts['host']) && !in_array(strtolower($parts['host']), ['booknilecruises.net', 'www.booknilecruises.net'], true))) return null;
        $path = $parts['path'] ?? '';
        if (!str_starts_with($path, '/')) return null;
        $path = trim(rawurldecode($path), '/');
        return $path === '' ? '/' : '/' . $path . '/';
    }
}
