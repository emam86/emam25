<?php declare(strict_types=1); ?>
<div class="row"><?php if (can('posts.create')): ?><a class="btn" href="<?= e(url('/posts/new')) ?>">إضافة مقال</a><?php endif; ?></div>
<form method="get" class="row"><input name="q" value="<?= e($q) ?>" placeholder="بحث المقالات"><button class="btn">بحث</button></form>
<div class="table-wrap"><table><thead><tr><th>العنوان</th><th>الحالة</th><th>تاريخ النشر</th><th>المصدر</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?= e($row['title']) ?></td><td><?= e($row['status']) ?><?php if ($row['published_at'] && strtotime($row['published_at']) > time()): ?> <span class="tag">مجدول (scheduled)</span><?php endif; ?></td><td><?= e($row['published_at']) ?></td><td><?= e($row['source']) ?></td><td><?php if (can('posts.edit')): ?><a href="<?= e(url("/posts/{$row['id']}/edit")) ?>">تعديل</a><?php endif; ?> <?php if (can('posts.delete')): ?><a href="<?= e(url("/posts/{$row['id']}/delete")) ?>">حذف</a><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<p><?= (int) $total ?> مقال — صفحة <?= (int) $page ?> / <?= (int) $pages ?></p>
<div class="row"><?php if ($page > 1): ?><a href="<?= e(url('/posts', ['q' => $q, 'page' => $page - 1])) ?>">السابق</a><?php endif; ?><?php if ($page < $pages): ?><a href="<?= e(url('/posts', ['q' => $q, 'page' => $page + 1])) ?>">التالي</a><?php endif; ?></div>
