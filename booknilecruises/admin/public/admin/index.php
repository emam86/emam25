<?php
declare(strict_types=1);

// Admin panel entry point. All code lives outside public_html, in bnc-app/.
foreach ([dirname(__DIR__, 2) . '/bnc-app', dirname(__DIR__, 2) . '/app'] as $dir) {
    if (is_file($dir . '/bootstrap.php')) {
        require $dir . '/bootstrap.php';
        \Bnc\App::run();
        return;
    }
}
http_response_code(500);
echo 'Admin app folder (bnc-app) not found next to public_html.';
