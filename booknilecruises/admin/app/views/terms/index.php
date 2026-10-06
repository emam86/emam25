<?php declare(strict_types=1); ?>
<p><a class="btn" href="<?= e(url('/terms/new')) ?>">+ إضافة تصنيف</a></p>
<?php foreach (\Bnc\Terms::TAXONOMIES as $taxonomy => $label): ?>
<h2><?= e($label) ?></h2>
<div class="table-wrap"><table><thead><tr><th>الاسم</th><th>الرابط المختصر</th><th>الرابط العام</th><th>الأب</th><th>الرحلات</th><th></th></tr></thead><tbody>
<?php foreach ($terms as $term): if ($term['taxonomy'] !== $taxonomy) continue; ?>
  <tr><td><?= e($term['name']) ?></td><td><?= e($term['slug']) ?></td><td dir="ltr"><?= e($term['url']) ?></td><td><?= e($term['parent_name'] ?? '—') ?></td><td><?= (int) $term['trip_count'] ?></td><td class="actions"><a href="<?= e(url("/terms/{$term['id']}/edit")) ?>">تعديل</a>
  <?php if (!\Bnc\Terms::protected($term)): ?><form method="post" action="<?= e(url("/terms/{$term['id']}/delete")) ?>" data-confirm="حذف التصنيف؟"><?= csrf_field() ?><button class="link danger">حذف</button></form><?php else: ?><span class="muted">صفحة أساسية</span><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php endforeach; ?>
