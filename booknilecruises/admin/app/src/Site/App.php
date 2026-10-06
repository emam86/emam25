<?php
declare(strict_types=1);
namespace Bnc\Site;

use Bnc\Config;
use Bnc\Settings;
use Bnc\SitePages;

final class App
{
    public static function run(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Frame-Options: SAMEORIGIN');
        $method=$_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method,['GET','HEAD'],true)) {
            header('Allow: GET, HEAD'); header('Cache-Control: no-store'); http_response_code(405); echo 'Method not allowed'; return;
        }
        $path=parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH);
        if (!is_string($path) || !SitePages::validPath($path) || str_contains($path,'//')) { self::send(['status'=>404,'type'=>'text/plain; charset=utf-8','body'=>'Page not found'],$method); return; }
        $cache=new Cache(); $state=Cache::state();
        if ($state['noindex']) header('X-Robots-Tag: noindex, nofollow');
        if ($response=$cache->get($path,$state)) { self::send($response,$method); return; }
        $site=(new Repository())->load();
        if ($path==='/sitemap.xml') $response=['status'=>200,'type'=>'application/xml; charset=utf-8','body'=>self::sitemap($site)];
        elseif ($path==='/robots.txt') $response=['status'=>200,'type'=>'text/plain; charset=utf-8','body'=>self::noindex() ? "User-agent: *\nDisallow: /\n" : "User-agent: *\nAllow: /\n\nSitemap: https://booknilecruises.net/sitemap.xml\n"];
        elseif (!empty($site['settings']['indexnow_key']) && $path==='/'.$site['settings']['indexnow_key'].'.txt') $response=['status'=>200,'type'=>'text/plain; charset=utf-8','body'=>$site['settings']['indexnow_key']];
        else {
            $router=new Router();
            $page=$router->resolve($path,$site);
            if ($page['type']==='404' && $path!=='/' && !str_ends_with($path,'/') && !str_contains(basename($path),'.')) {
                $candidate=$router->resolve($path.'/',$site);
                if ($candidate['type']!=='404' && $candidate['type']!=='redirect') { self::redirect($path.'/',true); return; }
                if ($candidate['type']==='redirect') { self::redirect($candidate['location']); return; }
            }
            if ($page['type']==='redirect') { self::redirect($page['location']); return; }
            $response=['status'=>$page['type']==='404' ? 404 : 200,'type'=>'text/html; charset=utf-8','body'=>View::render('page',['site'=>$site,'page'=>$page])];
        }
        // Not-found pages are not cached: made-up URLs must not be able to fill the disk.
        if ($response['status'] !== 404) $cache->put($path,$state,$response);
        self::send($response,$method);
    }

    public static function noindex(): bool
    {
        return getenv('PUBLIC_NOINDEX')==='1' || Config::get('site_noindex', false) || Settings::get('site_noindex','0')==='1';
    }

    private static function redirect(string $location, bool $query=false): void
    {
        if ($query && !empty($_SERVER['QUERY_STRING'])) $location.='?'.$_SERVER['QUERY_STRING'];
        header('Cache-Control: public, max-age=3600'); header('Location: '.$location,true,301);
    }

    private static function send(array $response,string $method): void
    {
        // Edits must show at once: browsers and CDNs revalidate every time, and an unchanged
        // page costs only a 304 thanks to the ETag.
        $etag='"'.substr(hash('sha256',$response['body']),0,32).'"';
        header('Content-Type: '.$response['type']);
        header('Cache-Control: no-cache');
        header('ETag: '.$etag);
        if ($response['status']===200 && trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''))===$etag) { http_response_code(304); return; }
        http_response_code($response['status']);
        if ($method!=='HEAD') echo $response['body'];
    }

    public static function sitemap(array $site): string
    {
        $urls=SitePages::FIXED;
        $lastmod=[];
        foreach ($site['trips'] as $t) { $urls[]=$t['url']; $lastmod[$t['url']]=$t['modified']; }
        foreach ($site['posts'] as $p) $urls[]=$p['url'];
        foreach ($site['terms'] as $terms) foreach ($terms as $term) if (count($term['trips'])) $urls[]=$term['url'];
        $rows=[];
        foreach (array_unique($urls) as $url) if (!isset($site['noindexPaths'][$url])) $rows[]='  <url><loc>https://booknilecruises.net'.e($url).'</loc>'.(isset($lastmod[$url]) ? '<lastmod>'.e(substr($lastmod[$url],0,10)).'</lastmod>' : '').'</url>';
        return '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n".implode("\n",$rows)."\n</urlset>\n";
    }
}
