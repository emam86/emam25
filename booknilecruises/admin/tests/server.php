<?php
// Router for `php -S` during tests: static assets from public/, everything else through the panel.
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($uri, '/admin/assets/')) return false;
if (str_starts_with($uri, '/admin')) {
    require __DIR__ . '/../public/admin/index.php';
    return true;
}
http_response_code(404);
