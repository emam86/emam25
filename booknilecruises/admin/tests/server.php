<?php
declare(strict_types=1);
// Router for `php -S` during tests: static assets from public/, everything else through the panel.
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($uri, '/admin/assets/')) return false;
// Uploaded photos (the test config keeps them in tests/tmp/images).
if (preg_match('#^/images/([A-Za-z0-9_./-]+)$#', $uri, $m) && !str_contains($m[1], '..') && is_file(__DIR__ . '/tmp/images/' . $m[1])) {
    header('Content-Type: ' . (mime_content_type(__DIR__ . '/tmp/images/' . $m[1]) ?: 'application/octet-stream'));
    readfile(__DIR__ . '/tmp/images/' . $m[1]);
    return true;
}
// Public site assets use their deployed URL under /assets, not /site/assets.
if (preg_match('#^/assets/([A-Za-z0-9_.-]+)$#', $uri, $m) && is_file(__DIR__ . '/../public/site/assets/' . $m[1])) {
    $ext = pathinfo($m[1], PATHINFO_EXTENSION);
    header('Content-Type: ' . (['css' => 'text/css', 'js' => 'application/javascript', 'webp' => 'image/webp'][$ext] ?? 'application/octet-stream'));
    readfile(__DIR__ . '/../public/site/assets/' . $m[1]);
    return true;
}
if ($uri === '/img/logo.webp') {
    header('Content-Type: image/webp');
    readfile(__DIR__ . '/../public/site/img/logo.webp');
    return true;
}
if (str_starts_with($uri, '/api')) {
    require __DIR__ . '/../public/api/index.php';
    return true;
}
if (str_starts_with($uri, '/admin')) {
    require __DIR__ . '/../public/admin/index.php';
    return true;
}
require __DIR__ . '/../public/site/index.php';
return true;
