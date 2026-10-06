<?php
declare(strict_types=1);

// Import the current site into an empty panel:
//   php bnc-app/bin/import-site.php export.json
// (export.json comes from: cd site && node scripts/export-seed.mjs export.json)
require dirname(__DIR__) . '/bootstrap.php';

$file = $argv[1] ?? '';
if (!is_file($file)) {
    fwrite(STDERR, "Usage: php import-site.php <export.json>\n");
    exit(2);
}
\Bnc\Migrator::run();
$counts = \Bnc\Content\Importer::run(json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR), 'cli');
foreach ($counts as $table => $n) echo "$table: $n\n";
