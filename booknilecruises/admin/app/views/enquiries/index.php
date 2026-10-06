<?php declare(strict_types=1); ?>
<?= \Bnc\View::partial('partials/errors', ['errors' => $errors]) ?>
<form method="get" class="row">
  <input name="q" value="<?= e($q) ?>" placeholder="بحث بالاسم أو التواصل أو الرسالة" aria-label="بحث">
  <select name="status" aria-label="الحالة"><option value="">كل الحالات</option><?php foreach (\Bnc\Enquiries\EnquiryService::STATUSES as $value => $label): ?><option value="<?= e($value) ?>"<?= $status === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
  <button class="btn">بحث</button><a class="btn" href="<?= e(url('/enquiries.csv', ['q' => $q, 'status' => $status])) ?>">تصدير CSV</a>
</form>
<div class="table-wrap"><table><thead><tr><th>الاسم</th><th>التواصل</th><th>الرحلة</th><th>القناة</th><th>الحالة</th><th>الوقت</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><a href="<?= e(url('/enquiries/' . $row['id'])) ?>"><?= e($row['name']) ?></a></td><td><?= e($row['email']) ?><br><?= e($row['phone']) ?></td><td><?= e($row['trip_title']) ?></td><td><?= e(['form' => 'نموذج', 'whatsapp' => 'واتساب', 'email' => 'بريد'][$row['channel']] ?? $row['channel']) ?></td><td><span class="tag"><?= e(\Bnc\Enquiries\EnquiryService::STATUSES[$row['status']]) ?></span></td><td><?= e($row['created_at']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<p><?= (int) $total ?> استفسار — صفحة <?= (int) $page ?> / <?= (int) $pages ?></p>
<div class="row"><?php if ($page > 1): ?><a href="<?= e(url('/enquiries', ['q' => $q, 'status' => $status, 'page' => $page - 1])) ?>">السابق</a><?php endif; ?><?php if ($page < $pages): ?><a href="<?= e(url('/enquiries', ['q' => $q, 'status' => $status, 'page' => $page + 1])) ?>">التالي</a><?php endif; ?></div>
<?php if (can('enquiries.manage')): ?><form method="post" action="<?= e(url('/enquiries/notify')) ?>" class="card stack">
<?= csrf_field() ?><label>بريد إشعارات الاستفسارات<input type="email" name="enquiry_notify_email" value="<?= e($notify) ?>" maxlength="190"></label><p>اتركه فارغًا لإيقاف إشعارات البريد.</p><button class="btn">حفظ بريد الإشعارات</button>
</form><?php endif; ?>
