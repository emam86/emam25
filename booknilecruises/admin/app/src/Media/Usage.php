<?php
declare(strict_types=1);

namespace Bnc\Media;

use Bnc\Db;

final class Usage
{
    /** @return list<array{type:string,id:int,title:string,url:string}> */
    public static function of(array $media): array
    {
        $needles = [$media['path']];
        foreach (Files::sizes($media) as $size) {
            if (is_array($size) && isset($size['file']) && is_string($size['file']) && $size['file'] !== '') $needles[] = $size['file'];
        }
        $uses = [];
        foreach (['trips' => ['overview_html', 'itinerary', 'faqs'], 'posts' => ['content_html']] as $table => $fields) {
            $columns = implode(', ', $fields);
            $gallery = $table === 'trips' ? ', gallery' : '';
            foreach (Db::all("SELECT id, title, image_id, $columns $gallery FROM $table") as $row) {
                $used = (int) ($row['image_id'] ?? 0) === (int) $media['id'];
                if ($table === 'trips') {
                    $ids = json_decode((string) ($row['gallery'] ?? '[]'), true);
                    if (is_array($ids) && in_array((int) $media['id'], array_map('intval', array_filter($ids, 'is_scalar')), true)) $used = true;
                }
                foreach ($fields as $field) {
                    $text = (string) ($row[$field] ?? '');
                    if (in_array($field, ['itinerary', 'faqs'], true)) {
                        $decoded = json_decode($text, true);
                        $text = $decoded === null ? $text : json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    }
                    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    foreach ($needles as $needle) if (str_contains($text, $needle)) $used = true;
                }
                if ($used) $uses[] = ['type' => $table === 'trips' ? 'trip' : 'post', 'id' => (int) $row['id'], 'title' => $row['title'], 'url' => "/$table/{$row['id']}/edit"];
            }
        }
        return $uses;
    }
}
