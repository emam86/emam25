<?php
declare(strict_types=1);

// Apply pending database migrations: php bnc-app/bin/migrate.php
require dirname(__DIR__) . '/bootstrap.php';

$applied = \Bnc\Migrator::run();
echo $applied ? 'Applied: ' . implode(', ', $applied) . "\n" : "Nothing to apply.\n";
