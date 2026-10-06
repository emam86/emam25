<?php
declare(strict_types=1);
namespace Bnc\Site;

final class Router
{
    public function resolve(string $path, array $site): array
    {
        $page=['path'=>$path];
        $fixed=['/'=>'home','/trip/'=>'listing','/destinations/'=>'terms','/activities/'=>'terms','/trip-types/'=>'terms','/about-us/'=>'about','/contact-us/'=>'contact','/faq/'=>'faq','/transfers/'=>'transfers','/terms-and-conditions/'=>'terms-and-conditions','/blog/'=>'blog'];
        if (isset($fixed[$path])) {
            $page['type']=$fixed[$path];
            if ($page['type']==='terms') $page['taxonomy']=['/destinations/'=>'destination','/activities/'=>'activities','/trip-types/'=>'trip_types'][$path];
            if ($path==='/trip/') $page['trips']=$site['trips'];
            return $page;
        }
        $slug=trim($path,'/');
        if ($path === '/'.$slug.'/' && isset(View::categories()[$slug])) return $page+['type'=>'listing','cfg'=>View::categories()[$slug],'category'=>$slug];
        foreach ($site['trips'] as $trip) if ($trip['url']===$path) return $page+['type'=>'trip','trip'=>$trip];
        foreach ($site['posts'] as $post) if ($post['url']===$path) return $page+['type'=>'post','post'=>$post];
        foreach ($site['terms'] as $taxonomy=>$terms) foreach ($terms as $term) if ($term['url']===$path && ($taxonomy!=='trip_types' || count($term['trips'])>0)) return $page+['type'=>'listing','term'=>$term,'taxonomy'=>$taxonomy];
        foreach ($site['redirects'] as $from=>$to) if (rtrim($from,'/')===rtrim($path,'/')) return $page+['type'=>'redirect','location'=>$to];
        if ($to=LegacyRedirects::resolve($path)) return $page+['type'=>'redirect','location'=>$to];
        return $page+['type'=>'404'];
    }
}
