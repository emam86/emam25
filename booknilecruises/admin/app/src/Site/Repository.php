<?php
declare(strict_types=1);
namespace Bnc\Site;

use Bnc\Content\Exporter;

/** Request-local port of siteFromExport. The export is assembled directly from MySQL. */
final class Repository
{
    public function load(): array
    {
        $exp = Exporter::build();
        $media = [];
        foreach ($exp['media'] as $m) $media[$m['id']] = self::image($m);
        $terms = ['destination' => [], 'activities' => [], 'trip_types' => []];
        $byId = [];
        foreach ($exp['terms'] as $t) {
            $terms[$t['taxonomy']][] = ['id'=>$t['id'], 'taxonomy'=>$t['taxonomy'], 'slug'=>$t['slug'], 'name'=>$t['name'], 'parent'=>$t['parent_id'] ?? 0, 'url'=>$t['url'], 'description'=>$t['description'] ?? '', 'seo'=>self::seo($t, $t['url']), 'trips'=>[]];
            $i = count($terms[$t['taxonomy']])-1;
            $byId[$t['id']] =& $terms[$t['taxonomy']][$i];
        }
        $trips = [];
        foreach ($exp['trips'] as $t) {
            $url = '/trip/'.$t['slug'].'/';
            $cover = $media[$t['image_id'] ?? 0] ?? null;
            $gallery = array_values(array_filter(array_map(fn($id)=>$media[$id] ?? null, $t['gallery'])));
            $trip = ['id'=>$t['id'], 'slug'=>$t['slug'], 'url'=>$url, 'title'=>$t['title'], 'excerpt'=>$t['excerpt'], 'code'=>$t['code'], 'price'=>$t['price'], 'salePrice'=>$t['sale_price'], 'currency'=>$t['currency'] ?: 'USD', 'duration'=>['days'=>$t['duration_days'], 'nights'=>$t['duration_nights']], 'minPax'=>$t['min_pax'], 'maxPax'=>$t['max_pax'], 'overviewHtml'=>$t['overview_html'], 'highlights'=>$t['highlights'], 'itinerary'=>$t['itinerary'], 'includes'=>$t['includes'], 'excludes'=>$t['excludes'], 'faqs'=>$t['faqs'], 'image'=>$cover, 'gallery'=>$gallery ?: ($cover ? [$cover] : []), 'featured'=>$t['featured'], 'modified'=>$t['updated_at'], 'seo'=>self::seo($t,$url), 'destinations'=>[], 'activities'=>[], 'tripTypes'=>[]];
            foreach ($t['term_ids'] as $id) {
                if (!isset($byId[$id])) continue;
                $key = ['destination'=>'destinations','activities'=>'activities','trip_types'=>'tripTypes'][$byId[$id]['taxonomy']];
                $trip[$key][] =& $byId[$id];
            }
            $trips[] = $trip;
        }
        // ICU's English collation mirrors JavaScript localeCompare, including punctuation.
        $collator = class_exists(\Collator::class) ? new \Collator('en-US') : null;
        usort($trips, fn($a,$b)=>$collator ? $collator->compare($a['title'],$b['title']) : strnatcasecmp($a['title'],$b['title']));
        foreach ($trips as &$trip) {
            $seen = [];
            foreach (['destinations','activities','tripTypes'] as $key) foreach ($trip[$key] as $own) {
                $id = $own['id'];
                while (isset($byId[$id]) && !isset($seen[$id])) {
                    $seen[$id] = true;
                    $byId[$id]['trips'][] =& $trip;
                    $id = $byId[$id]['parent'];
                }
            }
        }
        unset($trip);
        $posts = [];
        foreach ($exp['posts'] as $p) $posts[] = ['id'=>$p['id'], 'slug'=>$p['slug'], 'url'=>$p['url'], 'title'=>$p['title'], 'html'=>$p['content_html'], 'text'=>self::text($p['content_html']), 'featuredMedia'=>$p['image_id'], 'date'=>$p['published_at'], 'modified'=>$p['updated_at'], 'seo'=>self::seo($p,$p['url']), 'image'=>$media[$p['image_id'] ?? 0] ?? null];
        $overrides = []; $noindex = [];
        foreach ($exp['seo_overrides'] as $o) { $overrides[$o['path']]=$o; if ($o['noindex']) $noindex[$o['path']]=true; }
        foreach ([...$trips,...$posts] as $item) if ($item['seo']['noindex']) $noindex[$item['url']]=true;
        return ['trips'=>$trips,'terms'=>$terms,'posts'=>$posts,'media'=>$media,'pages'=>require __DIR__.'/pages.php','settings'=>$exp['settings'],'seoOverrides'=>$overrides,'noindexPaths'=>$noindex,'redirects'=>array_column($exp['redirects'],'to','from')];
    }

    private static function seo(array $row, string $url): array
    {
        return ['title'=>$row['seo_title'] ?? '', 'description'=>$row['seo_description'] ?? '', 'canonical'=>$url, 'ogImage'=>null, 'noindex'=>(bool)($row['noindex'] ?? false)];
    }

    public static function text(string $html): string
    {
        return trim(preg_replace('/\s+/u',' ',self::decodeEntities(preg_replace('/<[^>]+>/',' ',$html))) ?? '');
    }

    public static function decodeEntities(string $text): string
    {
        $named = ['amp'=>'&', 'lt'=>'<', 'gt'=>'>', 'quot'=>'"', 'apos'=>"'", 'nbsp'=>' ', 'hellip'=>'…', 'ndash'=>'–', 'mdash'=>'—', 'rsquo'=>'’', 'lsquo'=>'‘', 'rdquo'=>'”', 'ldquo'=>'“', 'times'=>'×'];
        return preg_replace_callback('/&#x([0-9a-f]+);|&#([0-9]+);|&([a-z]+);/i', static function (array $m) use ($named): string {
            if (!empty($m[3])) return $named[strtolower($m[3])] ?? $m[0];
            $code = !empty($m[1]) ? hexdec($m[1]) : (int)($m[2] ?? 0);
            return $code >= 0 && $code <= 0x10ffff ? mb_chr((int)$code, 'UTF-8') : $m[0];
        }, $text) ?? $text;
    }

    private static function image(array $m): array
    {
        $src=$m['path']; $width=$m['width']; $height=$m['height']; $sizes=(array)$m['sizes']; $dir=dirname($src); $card=null; $entries=[];
        foreach (['medium_large','large','tourm_424X498','trip-thumb-size'] as $key) if (!empty($sizes[$key]['file'])) { $s=$sizes[$key]; $card=['src'=>$dir.'/'.$s['file'],'width'=>$s['width'],'height'=>$s['height']]; break; }
        foreach ($sizes as $s) if (!empty($s['file']) && ($s['width'] ?? 0)>=600 && ($s['height'] ?? 0)>0 && $width>0 && $height>0 && abs(($s['width']/$s['height'])/($width/$height)-1)<=0.02) $entries[$s['width']]=['src'=>$dir.'/'.$s['file'],'width'=>$s['width']];
        if ($width>0) $entries[$width]=['src'=>$src,'width'=>$width];
        ksort($entries,SORT_NUMERIC);
        return ['src'=>$src,'width'=>$width,'height'=>$height,'alt'=>self::decodeEntities($m['alt'] ?? ''),'srcset'=>array_values($entries),'card'=>$card];
    }
}
