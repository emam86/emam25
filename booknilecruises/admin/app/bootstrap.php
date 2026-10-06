<?php
declare(strict_types=1);

// Shared start-up for the admin panel, the API and the CLI scripts.

const BNC_APP = __DIR__;

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Bnc\\')) return;
    $file = BNC_APP . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) require $file;
});

require BNC_APP . '/src/helpers.php';

(static function (): void {
    $path = getenv('BNC_CONFIG') ?: dirname(BNC_APP) . '/bnc-config.php';
    if (!is_file($path)) {
        http_response_code(500);
        exit("Missing configuration file. Copy config.sample.php to bnc-config.php next to the app folder.\n");
    }
    \Bnc\Config::load(require $path);
})();

date_default_timezone_set(\Bnc\Config::get('timezone', 'Africa/Cairo'));
error_reporting(E_ALL);
ini_set('display_errors', \Bnc\Config::get('debug') ? '1' : '0');
ini_set('log_errors', '1');

set_exception_handler(static function (Throwable $e): void {
    error_log('[bnc] ' . $e);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e . "\n");
        exit(1);
    }
    if (!headers_sent()) http_response_code(500);
    echo \Bnc\Config::get('debug')
        ? '<pre>' . e((string) $e) . '</pre>'
        : 'حدث خطأ غير متوقع. حاول مرة أخرى.';
});
