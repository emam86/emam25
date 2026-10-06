<?php declare(strict_types=1); ?>
<p>نظرة عامة للروابط في sitemap.xml عند النشر القادم. يتم تحديث حالة المقالات المجدولة عند النشر.</p>
<div class="row"><a href="<?= e(rtrim((string) \Bnc\Config::get('site_url'), '/') . '/sitemap.xml') ?>" target="_blank" rel="noopener">السايت ماب الحالي</a><a href="https://search.google.com/search-console" target="_blank" rel="noopener">Google Search Console</a></div>
<?php foreach ($groups as $label => $paths): ?><section class="card"><h2><?= e($label) ?> (<?= count($paths) ?>)</h2><ul><?php foreach ($paths as $path): ?><li dir="ltr"><?= e($path) ?></li><?php endforeach; ?></ul></section><?php endforeach; ?>
<section class="card"><h2>المستبعد (<?= count($excluded) ?>)</h2><div class="table-wrap"><table><thead><tr><th>المسار</th><th>السبب</th></tr></thead><tbody><?php foreach ($excluded as $row): ?><tr><td dir="ltr"><?= e($row['path']) ?></td><td><?= e($row['reason']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
