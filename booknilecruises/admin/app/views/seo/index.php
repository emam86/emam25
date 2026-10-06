<?php declare(strict_types=1); ?>
<p>الرحلات والتصنيفات والمقالات لها حقول SEO في صفحات تعديلها: <a href="<?= e(url('/trips')) ?>">الرحلات</a>، <a href="<?= e(url('/terms')) ?>">التصنيفات</a>، <a href="<?= e(url('/posts')) ?>">المقالات</a>.</p>
<form method="get" action="<?= e(url('/seo/edit')) ?>" class="row"><label>مسار صفحة أخرى <input name="path" required placeholder="/example/" dir="ltr"></label><button class="btn">إضافة أو تعديل</button></form>
<div class="table-wrap"><table><thead><tr><th>الصفحة</th><th>العنوان</th><th>الوصف</th><th>عدم الفهرسة</th><th></th></tr></thead><tbody>
<?php foreach ($paths as $path): $override = $overrides[$path] ?? []; ?><tr><td dir="ltr"><?= e($path) ?></td><td><?= e($override['title'] ?? '') ?></td><td><?= e($override['description'] ?? '') ?></td><td><?= !empty($override['noindex']) ? 'نعم' : 'لا' ?></td><td><a href="<?= e(url('/seo/edit', ['path' => $path])) ?>">تعديل</a></td></tr><?php endforeach; ?>
</tbody></table></div>
