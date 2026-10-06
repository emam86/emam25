<?php
// Router for `php -S` during tests: static assets from public/, everything else through the panel.
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($uri, '/admin/assets/')) return false;
// Uploaded photos (the test config keeps them in tests/tmp/images).
if (preg_match('#^/images/([A-Za-z0-9_./-]+)$#', $uri, $m) && !str_contains($m[1], '..') && is_file(__DIR__ . '/tmp/images/' . $m[1])) {
    header('Content-Type: ' . (mime_content_type(__DIR__ . '/tmp/images/' . $m[1]) ?: 'application/octet-stream'));
    readfile(__DIR__ . '/tmp/images/' . $m[1]);
    return true;
}
if (str_starts_with($uri, '/admin')) {
    require __DIR__ . '/../public/admin/index.php';
    return true;
}
http_response_code(404);
