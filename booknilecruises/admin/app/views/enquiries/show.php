<?php declare(strict_types=1); ?>
<?= \Bnc\View::partial('partials/errors', ['errors' => $errors]) ?>
<div class="card stack"><dl>
<?php foreach (['name' => 'الاسم', 'email' => 'البريد', 'phone' => 'الهاتف', 'trip_title' => 'الرحلة', 'travel_date' => 'تاريخ السفر', 'adults' => 'البالغون', 'children' => 'الأطفال', 'message' => 'الرسالة', 'page_url' => 'صفحة الموقع', 'channel' => 'القناة', 'ip' => 'عنوان IP', 'created_at' => 'وقت الاستفسار', 'notes' => 'الملاحظات'] as $field => $label): ?><dt><?= e($label) ?></dt><dd><?= nl2br(e($row[$field])) ?></dd><?php endforeach; ?>
</dl><p>الحالة: <span class="tag"><?= e(\Bnc\Enquiries\EnquiryService::STATUSES[$row['status']] ?? $row['status']) ?></span></p></div>
<?php if (can('enquiries.manage')): ?>
<form method="post" action="<?= e(url('/enquiries/' . $row['id'])) ?>" class="card stack"><?= csrf_field() ?>
<label>الحالة<select name="status"><?php foreach (\Bnc\Enquiries\EnquiryService::STATUSES as $value => $label): ?><option value="<?= e($value) ?>"<?= $row['status'] === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
<label>الملاحظات<textarea name="notes" rows="6" maxlength="10000"><?= e($row['notes']) ?></textarea></label><button class="btn">حفظ</button></form>
<form method="post" action="<?= e(url('/enquiries/' . $row['id'] . '/delete')) ?>" data-confirm="حذف الاستفسار نهائيًا؟"><?= csrf_field() ?><button class="btn danger">حذف الاستفسار</button></form>
<?php endif; ?><p><a href="<?= e(url('/enquiries')) ?>">كل الاستفسارات</a></p>
