<?php
declare(strict_types=1);
namespace Bnc\Site;

final class LegacyRedirects
{
    public const MAP = [
        '/sample-page/' => '/',
        '/tt/' => '/',
        '/check/' => '/',
        '/pricing-plan/' => '/egypt-tour-packages/',
        '/services/' => '/about-us/',
        '/tour-guide/' => '/about-us/',
        '/activities-2/' => '/activities/',
        '/destination/' => '/destinations/',
        '/trip-types-2/' => '/trip-types/',
        '/tours/' => '/trip/',
        '/terms-and-conditions-2/' => '/terms-and-conditions/',
        '/travelers-information/' => '/contact-us/',
        '/travellers-information/' => '/contact-us/',
        '/shop/' => '/trip/',
        '/tourm-shop/' => '/trip/',
        '/cart/' => '/contact-us/',
        '/tourm-cart/' => '/contact-us/',
        '/checkout/' => '/contact-us/',
        '/checkout-2/' => '/contact-us/',
        '/tourm-checkout/' => '/contact-us/',
        '/wp-travel-engine-cart/' => '/contact-us/',
        '/wp-travel-engine-cart-2/' => '/contact-us/',
        '/wp-travel-engine-checkout/' => '/contact-us/',
        '/wp-travel-engine-checkout-2/' => '/contact-us/',
        '/wp-travel-engine-confirmation-page/' => '/contact-us/',
        '/wp-travel-engine-wishlist/' => '/trip/',
        '/wishlist/' => '/trip/',
        '/wishlist-2/' => '/trip/',
        '/my-account/' => '/contact-us/',
        '/my-account-2/' => '/contact-us/',
        '/my-account-2-2/' => '/contact-us/',
        '/my-account-3/' => '/contact-us/',
        '/my-account-4/' => '/contact-us/',
        '/thank-you/' => '/contact-us/',
        '/thank-you-2/' => '/contact-us/',
        '/enquiry-thank-you-page/' => '/contact-us/',
        '/enquiry-thank-you-page-2/' => '/contact-us/',
        '/trip-search-result/' => '/trip/',
        '/trip-search-result-2/' => '/trip/',
        '/product/v-neck-t-shirt/' => '/',
        '/product/trendy-sunglass/' => '/',
        '/product/hoodie-with-zipper/' => '/',
        '/product/sony-black-headphone/' => '/',
        '/product/beach-hat/' => '/',
        '/product/hamok/' => '/',
        '/product/beach-football/' => '/',
        '/product/beach-casual-shoe/' => '/',
        '/product-category/uncategorized/' => '/',
        '/product-category/beach/' => '/',
        '/product-category/play/' => '/',
        '/product-tag/beach/' => '/',
        '/product-tag/living/' => '/',
        '/product-tag/sofa/' => '/',
        '/product-tag/sports/' => '/',
        '/category/uncategorized/' => '/blog/',
        '/trip-difficulty/easy/' => '/trip/',
        '/trip-difficulty/medium/' => '/trip/',
        '/feed/' => '/blog/',
        '/sitemap.rss' => '/sitemap.xml',
    ];

    public static function resolve(string $path): ?string
    {
        foreach (self::MAP as $from => $to) if (str_starts_with($path, $from)) return $to . substr($path, strlen($from));
        if (preg_match('#^/wp-content/uploads/(.*)$#D', $path, $m)) return '/images/' . $m[1];
        if (preg_match('#^/(sitemap_index|wp-sitemap[a-z0-9_-]*|[a-z0-9_-]+-sitemap[0-9]*)\.xml$#D', $path)) return '/sitemap.xml';
        if (str_starts_with($path, '/author/')) return '/about-us/';
        if ($path === '/wp-admin' || $path === '/wp-admin/' || $path === '/wp-login.php') return '/';
        return null;
    }
}
