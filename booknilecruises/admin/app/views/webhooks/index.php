<?php declare(strict_types=1); ?>
<p><a class="btn" href="<?= e(url('/webhooks/new')) ?>">إضافة Webhook</a></p>
<div class="table-wrap"><table><thead><tr><th>الاسم</th><th>الرابط</th><th>الأحداث</th><th>الحالة</th><th>آخر نتيجة</th><th>آخر إرسال</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?= e($row['name']) ?></td><td><?= e($row['url']) ?></td><td><?= e(implode(', ', json_decode($row['events'], true))) ?></td><td><?= $row['is_active'] ? 'نشط' : 'غير نشط' ?></td><td><?= e($row['last_status']) ?></td><td><?= e($row['last_called_at']) ?></td><td><a href="<?= e(url('/webhooks/' . $row['id'] . '/edit')) ?>">تعديل</a></td></tr><?php endforeach; ?></tbody></table></div>
