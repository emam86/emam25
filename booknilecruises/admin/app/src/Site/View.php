<?php
declare(strict_types=1);
namespace Bnc\Site;
final class View
{
    public static array $site = [];
    private static ?array $config = null;
    private static string $path = '/';
    public static string $pageTemplate = '';
    public static function config(): array { return self::$config ??= json_decode((string)file_get_contents(BNC_APP.'/views/site/config.json'), true, 512, JSON_THROW_ON_ERROR); }
    public static function categories(): array { return self::config()['CATEGORY_PAGES']; }
    public static function business(): array { $s=self::$site['settings']??[]; $pick=fn($k,$d)=>!empty($s[$k])?$s[$k]:$d; return ['name'=>'Book Nile Cruises','origin'=>'https://booknilecruises.net','email'=>$pick('email','info@booknilecruises.net'),'whatsapp'=>$pick('whatsapp','201096611124'),'phoneDisplay'=>$pick('phone_display','+20 109 661 1124'),'phoneAlt'=>$pick('phone_alt','+20 101 800 3960'),'address'=>$pick('address','Khaled Ibn El Waleed St., Luxor, Egypt')]; }
    public static function render(string $name, array $vars = []): string
    {
        require_once BNC_APP.'/views/site/functions.php';
        self::$site=$vars['site']??[];
        $page=$vars['page']??[]; self::$path=$page['path']??'/';
        $type=$page['type']??$name;
        $mapping=['home'=>'pages-index','trip'=>'pages-trip-slug','post'=>'pages-year-month-day-slug','blog'=>'pages-blog-index','about'=>'pages-about-us','about-us'=>'pages-about-us','contact'=>'pages-contact-us','contact-us'=>'pages-contact-us','faq'=>'pages-faq','transfers'=>'pages-transfers','terms-and-conditions'=>'pages-terms-and-conditions','404'=>'pages-404','category'=>'pages-category','trips'=>'pages-trip-index','terms'=>'pages-'.(($page['taxonomy']??'')==='destination'?'destinations':(($page['taxonomy']??'')==='trip_types'?'trip-types':'activities')).'-index'];
        if ($type==='listing') $type=isset($page['cfg'])?'category':(isset($page['term'])?'term':'trips');
        if ($type==='term') $template='pages-'.(($page['term']['taxonomy']??$page['taxonomy']??'activities')==='destination'?'destinations-slug':(($page['term']['taxonomy']??$page['taxonomy']??'activities')==='trip_types'?'trip-types-path':'activities-path'));
        else $template=$mapping[$type]??$name;
        self::$pageTemplate=$template; return self::template($template, $page);
    }
    public static function component(string $name, array $props=[]): string { return self::template((in_array($name,['Base','Page','Listing'])?'layouts-':'components-').$name, $props); }
    private static function template(string $name, array $props): string
    {
        $file=BNC_APP.'/views/site/'.$name.'.php'; if (!is_file($file)) throw new \RuntimeException('Site template not found: '.$name);
        $vars=self::variables($name,$props); extract($vars,EXTR_SKIP); ob_start(); try { require $file; return (string)ob_get_clean(); } catch (\Throwable $e) { ob_end_clean(); throw $e; }
    }
    public static function resources(): string { $version='1'; return '<link rel="stylesheet" href="/assets/site.css?v='.$version.'"><script defer src="/assets/site.js?v='.$version.'"></script>'; }
    private static function variables(string $name,array $p): array
    {
        $site=self::$site; $SITE=self::business(); $config=self::config(); $v=$p+['site'=>$site,'SITE'=>$SITE,'NAV'=>$config['NAV'],'CATEGORY_PAGES'=>$config['CATEGORY_PAGES'],'TRANSFERS'=>$config['TRANSFERS'],'slot'=>''];
        switch($name) {
        case 'components-CtaBand': $v+=['title'=>'Tell us your dates. We will send you the options.','text'=>'Message us on WhatsApp or send an email with your travel dates and number of travellers.']; break;
        case 'components-Enquiry': $v+=['trip'=>null,'id'=>'enq']; $v['subject']=$v['trip']?'Enquiry: '.$v['trip']['title']:'Trip enquiry'; break;
        case 'components-PageHero': $v+=['intro'=>null,'crumbs'=>[],'image'=>null]; break;
        case 'components-TripCard': $trip=$p['trip']; $v+=['eager'=>false,'img'=>$trip['image']['card']??$trip['image'],'price'=>\bnc_money($trip['price'],$trip['currency']),'badge'=>explode(' / ',\bnc_duration($trip['duration'])??'')[0]]; break;
        case 'components-TermGrid': break;
        case 'components-TripListing':
            $v+=['filters'=>true,'emptyText'=>'No trips are listed here yet.']; $v['emptyText'] ??= 'No trips are listed here yet.'; $d=[]; foreach($p['trips'] as $t)foreach($t['destinations'] as $term)$d[$term['slug']]=$term; usort($d,fn($a,$b)=>strcoll($a['name'],$b['name'])); $v['destinations']=$d; $v['showFilters']=$v['filters']&&count($d)>1; break;
        case 'layouts-Page': $v+=['intro'=>null,'description'=>null,'canonical'=>null,'image'=>'/images/2025/12/A-wonderful-picture-of-a-visitor-to-the-Karnak-Temple-in-Luxor.jpg','crumbs'=>[['label'=>$p['heading']]],'jsonLd'=>null,'noindex'=>false]; break;
        case 'layouts-Listing':
            $v+=['intro'=>null,'description'=>null,'canonical'=>null,'crumbs'=>[],'emptyText'=>null,'heroImage'=>null]; $hero=$v['heroImage']; foreach($p['trips'] as $t)if(!$hero&&$t['image'])$hero=$t['image']['src']; $v['hero']=$hero; $v['jsonLd']=['@context'=>'https://schema.org','@type'=>'ItemList','name'=>$p['heading'],'itemListElement'=>array_map(fn($t,$i)=>['@type'=>'ListItem','position'=>$i+1,'url'=>\bnc_absolute($t['url']),'name'=>$t['title']],array_slice($p['trips'],0,50),array_keys(array_slice($p['trips'],0,50)))]; break;
        case 'layouts-Base':
            $override=$site['seoOverrides'][self::$path]??[]; $v['title']=$override['title']??null ?: $p['title']; $v['description']=$override['description']??null ?: ($p['description']??''); $v['canonicalUrl']=\bnc_absolute($p['canonical']??self::$path); $v['ogUrl']=\bnc_absolute($p['ogImage']??'/images/2025/12/Felucca-from-Aswan.jpg'); $v['robots']=!empty($p['noindex'])||isset($site['noindexPaths'][self::$path])||App::noindex()||getenv('PUBLIC_NOINDEX')==='1'?'noindex, nofollow':'max-image-preview:large';
            $s=$site['settings']??[]; $v['VERIFY']=['google'=>$s['google_site_verification']??'SsaoNo-o-vaVrxWj1PFiSWLm9JcNNGWC_Bs663QgEbs','bing'=>$s['bing_site_verification']??'','ga4'=>$s['ga4_id']??'']; $ga=$v['VERIFY']['ga4']; $v['gtag']=$ga?"window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config',".\bnc_json($ga).");":''; $ld=$p['jsonLd']??[]; $v['ld']=isset($ld['@type'])?[$ld]:$ld; $v['isActive']=fn($href,$children=[])=>self::$path===$href||\bnc_find($children??[],fn($c)=>self::$path===$c['href']); break;
        case 'pages-index':
            $termOf=fn($slug)=>\bnc_find($site['terms']['activities'],fn($t)=>$t['slug']===$slug); $v['classes']=[];
            foreach([['standard-5-star-nile-cruises','Standard 5-Star','/images/2025/12/Felucca-from-Aswan.jpg'],['deluxe-nile-cruises','Deluxe',null],['luxury-dahabiya-nile-cruise-packages','Dahabiya','/images/2025/12/Merit-Dahabiya-Nile-Cruise1.jpg'],['lake-nasser-nile-cruises','Lake Nasser','/images/2025/12/Movenpick-Prince-Abbas-Lake-Cruise1.jpg']] as [$slug,$label,$img]) { $trips=$termOf($config['CATEGORY_PAGES'][$slug]['term'])['trips']??[]; $image=\bnc_find($trips,fn($t)=>!empty($t['image']))['image']??null; $prices=array_column($trips,'price'); $prices=array_filter($prices,fn($p)=>$p!==null); $v['classes'][]=['href'=>'/'.$slug.'/','label'=>$label,'count'=>count($trips),'from'=>$prices?min($prices):null,'img'=>\bnc_asset($img??$image['card']['src']??$image['src']??null),'srcset'=>$img?null:\bnc_srcset($image),'noun'=>str_contains($slug,'dahabiya')?'boats':'ships']; }
            $v['popular']=[]; foreach(['12-days-cairo-alexandria-nile-cruise-el-bahariya-oasis','8-days-cairo-alexandria-nile-cruise-by-flight-2','sonesta-amirat-dahabiya-nile-cruise','movenpick-prince-abbas-lake-cruise','4-days-cairo-tour-package','abu-simbel-from-aswan-by-flight-the-majestic-day-trip'] as $slug){$t=\bnc_find($site['trips'],fn($t)=>$t['slug']===$slug);if($t)$v['popular'][]=$t;}
            $v['destinations']=[]; foreach(['luxor','cairo','aswan','hurghada','alexandria'] as $slug){$t=\bnc_find($site['terms']['destination'],fn($t)=>$t['slug']===$slug);if($t)$v['destinations'][]=$t;} $v['destImg']=fn($d)=>\bnc_find($d['trips'],fn($t)=>!empty($t['image']))['image']??null; $v['post']=$site['posts'][0]??null; $v['totalCruises']=count($termOf('nile-cruise')['trips']??[]);
            $v['jsonLd']=['@context'=>'https://schema.org','@type'=>'TravelAgency','name'=>$SITE['name'],'url'=>$SITE['origin'],'logo'=>$SITE['origin'].'/img/logo.webp','email'=>$SITE['email'],'telephone'=>$SITE['phoneDisplay'],'address'=>['@type'=>'PostalAddress','streetAddress'=>'Khaled Ibn El Waleed St.','addressLocality'=>'Luxor','addressCountry'=>'EG']]; break;
        case 'pages-category':
            $slug=trim(self::$path,'/'); $cfg=$p['cfg']??$config['CATEGORY_PAGES'][$slug]; $term=$p['term']??\bnc_find($site['terms']['activities'],fn($t)=>$t['slug']===$cfg['term']); $v['cfg']=$cfg;$v['term']=$term;$v['page']=\bnc_find($site['pages'],fn($t)=>$t['slug']===$slug);$v['Astro']=['params'=>['category'=>$slug]]; $parent=isset($term['parent'])?\bnc_find($site['terms']['activities'],fn($t)=>$t['id']===$term['parent']):null; $v['crumbs']=[];foreach($config['CATEGORY_PAGES'] as $key=>$c)if($parent&&$c['term']===$parent['slug'])$v['crumbs'][]=['label'=>$c['heading'],'href'=>'/'.$key.'/'];$v['crumbs'][]=['label'=>$cfg['heading']];break;
        case 'pages-activities-path': case 'pages-trip-types-path': case 'pages-destinations-slug': $v['n']=count($p['term']['trips']);break;
        case 'pages-activities-index': case 'pages-trip-types-index': case 'pages-destinations-index':
            $taxonomy=$name==='pages-destinations-index'?'destination':($name==='pages-trip-types-index'?'trip_types':'activities'); $v['terms']=array_values(array_filter($site['terms'][$taxonomy],fn($t)=>count($t['trips'])>0));$v['page']=\bnc_find($site['pages'],fn($t)=>$t['url']===self::$path);break;
        case 'pages-about-us':
            $v['cats']=[];foreach([['nile','nile-cruise','Nile cruises','/nile-cruise/'],['day','day-tour','Day tours','/day-tours/'],['pkg','tour-packages','Tour packages','/egypt-tour-packages/']] as [$key,$slug,$label,$href]){$t=\bnc_find($site['terms']['activities'],fn($t)=>$t['slug']===$slug)??['trips'=>[]];$v[$key]=$t;$v['cats'][]=array_replace($t,['name'=>$label,'href'=>$href]);}break;
        case 'pages-blog-index': $v['posts']=$site['posts'];break;
        case 'pages-faq': $v['faqs']=$config['faqs'];$v['faqs'][0][1]='Choose a trip, then send us your travel dates and the number of travellers on WhatsApp ('.e($SITE['phoneDisplay']).') or by email ('.e($SITE['email']).'). You can use the form on any trip page to prepare the message. We confirm availability and reply with the details.'; $v['jsonLd']=['@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>array_map(fn($f)=>['@type'=>'Question','name'=>$f[0],'acceptedAnswer'=>['@type'=>'Answer','text'=>html_entity_decode(strip_tags($f[1]), ENT_QUOTES|ENT_HTML5, 'UTF-8')]],$v['faqs'])];break;
        case 'pages-terms-and-conditions': $v['POLICY']=$config['POLICY'];$v['LAST_UPDATED']='2026-10-05';break;
        case 'pages-year-month-day-slug': $post=$p['post'];$v['jsonLd']=['@context'=>'https://schema.org','@type'=>'BlogPosting','headline'=>$post['title'],'datePublished'=>$post['date'],'dateModified'=>$post['modified'],'author'=>['@type'=>'Organization','name'=>$SITE['name'],'url'=>$SITE['origin']],'publisher'=>['@type'=>'Organization','name'=>$SITE['name'],'logo'=>['@type'=>'ImageObject','url'=>$SITE['origin'].'/img/logo.webp']],'mainEntityOfPage'=>\bnc_absolute($post['url'])];if($post['image'])$v['jsonLd']['image']=\bnc_absolute($post['image']['src']);break;
        case 'pages-trip-slug':
            $trip=$p['trip'];$primary=$trip['activities'][0]??$trip['tripTypes'][0]??null; $v['primary']=$primary;$v['related']=array_slice(array_values(array_filter($primary['trips']??[],fn($t)=>$t['id']!==$trip['id'])),0,3);$v['price']=\bnc_money($trip['price'],$trip['currency']);$v['duration']=\bnc_duration($trip['duration']);$v['gallery']=array_slice($trip['gallery'],0,12);$v['days']=[];$n=0;foreach($trip['itinerary'] as $day){$day['n']=$day['html']?++$n:null;if(!$day['html'])$n=0;$v['days'][]=$day;}$v['crumbs']=[['label'=>'Trips','href'=>'/trip/']];if($primary)$v['crumbs'][]=['label'=>$primary['name'],'href'=>$primary['url']];$v['crumbs'][]=['label'=>$trip['title']];
            $ld=['@context'=>'https://schema.org','@type'=>'TouristTrip','name'=>$trip['title'],'description'=>$trip['seo']['description']?:$trip['excerpt'],'url'=>\bnc_absolute($trip['url']),'provider'=>['@type'=>'TravelAgency','name'=>$SITE['name'],'url'=>$SITE['origin'],'telephone'=>$SITE['phoneDisplay'],'email'=>$SITE['email']]];if($trip['image'])$ld['image']=\bnc_absolute($trip['image']['src']);if($primary)$ld['touristType']=$primary['name'];if($trip['destinations'])$ld['itinerary']=['@type'=>'ItemList','itemListElement'=>array_map(fn($d,$i)=>['@type'=>'ListItem','position'=>$i+1,'item'=>['@type'=>'Place','name'=>$d['name']]],$trip['destinations'],array_keys($trip['destinations']))];if($trip['price']!==null)$ld['offers']=['@type'=>'Offer','price'=>$trip['price'],'priceCurrency'=>$trip['currency'],'availability'=>'https://schema.org/InStock','url'=>\bnc_absolute($trip['url'])];$crumbs=array_merge([['label'=>'Home','href'=>'/']],$v['crumbs']);$v['jsonLd']=[$ld,['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>array_map(fn($c,$i)=>['@type'=>'ListItem','position'=>$i+1,'name'=>$c['label']]+(isset($c['href'])?['item'=>\bnc_absolute($c['href'])]:[]),$crumbs,array_keys($crumbs))]];break;
        }
        return $v;
    }
}
