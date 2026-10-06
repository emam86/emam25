<?php
declare(strict_types=1);

const BNC_API_ENTRY = true;

// Same deployment layout as the admin entry point; no session is started.
ini_set('display_errors', '0');
foreach ([dirname(__DIR__, 2) . '/bnc-app', dirname(__DIR__, 2) . '/app'] as $dir) {
    if (is_file($dir . '/bootstrap.php')) {
        try {
            require $dir . '/bootstrap.php';
            \Bnc\Api\ApiApp::run();
        } catch (\Throwable) {
            ini_set('display_errors', '0');
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo '{"error":"Internal server error"}';
        }
        return;
    }
}
http_response_code(500);
header('Content-Type: application/json; charset=utf-8');
echo '{"error":"API app folder not found"}';
