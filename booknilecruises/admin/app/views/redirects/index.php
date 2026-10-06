<?php declare(strict_types=1); ?>
<a class="btn" href="<?= e(url('/redirects/new')) ?>">إضافة تحويل</a>
<form method="get" class="row"><input name="q" value="<?= e($q) ?>" placeholder="بحث التحويلات"><button class="btn">بحث</button></form>
<div class="table-wrap"><table><thead><tr><th>من</th><th>إلى</th><th>المصدر</th><th>التاريخ</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td dir="ltr"><?= e($row['from_path']) ?><?php if (\Bnc\SitePages::live($row['from_path'])): ?><p class="flash">هذه الصفحة موجودة؛ التحويل سيخفيها.</p><?php endif; ?></td><td dir="ltr"><?= e($row['to_path']) ?></td><td><?= e($row['source']) ?></td><td><?= e($row['created_at']) ?></td><td><a href="<?= e(url("/redirects/{$row['id']}/edit")) ?>">تعديل</a><form method="post" action="<?= e(url("/redirects/{$row['id']}/delete")) ?>" data-confirm="حذف التحويل؟"><?= csrf_field() ?><button class="btn small danger">حذف</button></form></td></tr><?php endforeach; ?>
</tbody></table></div>
