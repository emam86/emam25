<?php
declare(strict_types=1);

// php tests/run.php — needs a local MySQL/MariaDB. Set BNC_TEST_DSN / BNC_TEST_USER / BNC_TEST_PASS
// to point at an empty database the tests may wipe (default: bnc_test on 127.0.0.1, user bnc/bnc).

require __DIR__ . '/lib.php';

$dsn = getenv('BNC_TEST_DSN') ?: 'mysql:host=127.0.0.1;port=3306;dbname=bnc_test;charset=utf8mb4';
$tmp = __DIR__ . '/tmp';
@mkdir($tmp, 0700, true);
$images = $tmp . '/images';
if (is_link($images)) unlink($images);
if (is_dir($images)) {
    $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($images, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($entries as $entry) {
        if ($entry->isDir() && !$entry->isLink()) rmdir($entry->getPathname());
        else unlink($entry->getPathname());
    }
}
@mkdir($images, 0700, true);
$config = $tmp . '/config.php';
$installToken = 'test-install-token-0123456789abcdef';
file_put_contents($config, '<?php return ' . var_export([
    'db' => ['dsn' => $dsn, 'user' => getenv('BNC_TEST_USER') ?: 'bnc', 'pass' => getenv('BNC_TEST_PASS') ?: 'bnc'],
    'site_url' => 'https://booknilecruises.net',
    'admin_path' => '/admin',
    'install_token' => $installToken,
    'images_dir' => $tmp . '/images',
    'images_url' => '/images',
    'debug' => true,
], true) . ';');
putenv("BNC_CONFIG=$config");
require dirname(__DIR__) . '/app/bootstrap.php';

use Bnc\{Auth, Db, Migrator, Permissions, Policy, Roles, Router};

// Fresh database.
foreach (Db::all('SHOW TABLES') as $row) {
    Db::pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
    Db::pdo()->exec('DROP TABLE `' . reset($row) . '`');
}
Db::pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');

// Start the panel on PHP's built-in server.
$port = 18000 + random_int(0, 999);
$server = proc_open(
    [PHP_BINARY, '-d', 'opcache.enable=0', '-S', "127.0.0.1:$port", '-t', dirname(__DIR__) . '/public', __DIR__ . '/server.php'],
    [1 => ['file', $tmp . '/server.log', 'a'], 2 => ['file', $tmp . '/server.log', 'a']],
    $pipes,
    null,
    ['BNC_CONFIG' => $config] + getenv()
);
register_shutdown_function(fn () => proc_terminate($server));
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) usleep(100_000);
$base = "http://127.0.0.1:$port";

foreach (glob(__DIR__ . '/test_*.php') as $file) require $file;

exit(run_tests());
