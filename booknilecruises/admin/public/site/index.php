<?php
declare(strict_types=1);

// Deployed to public_html/index.php; implementation and cache stay outside public_html.
define('BNC_SITE_ENTRY', true);
ini_set('display_errors','0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
foreach ([dirname(__DIR__, 2).'/bnc-app', dirname(__DIR__, 2).'/app', dirname(__DIR__).'/bnc-app'] as $dir) {
    if (is_file($dir.'/bootstrap.php')) {
        require $dir.'/bootstrap.php';
        \Bnc\Site\App::run();
        return;
    }
}
error_log('[bnc] Public site app folder not found');
http_response_code(500);
header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
echo 'The site is temporarily unavailable. Please try again later.';
