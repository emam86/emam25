<?php declare(strict_types=1); ?>
<?= \Bnc\View::partial('partials/errors', ['errors' => $errors]) ?>
<div class="card stack">
  <?php if ($report): ?><p>آخر فحص: <?= e($report['created_at']) ?> · أخطاء: <?= (int) $report['error_count'] ?> · تحذيرات: <?= (int) $report['warning_count'] ?></p><?php else: ?><p>لم يُجرَ فحص بعد.</p><?php endif; ?>
  <form method="post" action="<?= e(url('/seo/report')) ?>"><?= csrf_field() ?><button class="btn" name="action" value="run">شغّل الفحص الآن</button></form>
  <form method="get" action="<?= e(url('/seo/report')) ?>"><label>الشدة <select name="severity"><?php foreach (['' => 'الكل', 'error' => 'أخطاء', 'warning' => 'تحذيرات', 'info' => 'معلومات'] as $value => $label): ?><option value="<?= e($value) ?>" <?= $severity === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label> <button class="btn">تصفية</button></form>
</div>
<div class="card stack">
  <form method="post" action="<?= e(url('/seo/report')) ?>" class="stack"><?= csrf_field() ?><label>بريد التقرير الأسبوعي <input type="email" name="seo_report_email" maxlength="190" value="<?= e(\Bnc\Settings::get('seo_report_email', '')) ?>"></label><button class="btn" name="action" value="email">حفظ البريد</button></form>
  <p>مفتاح IndexNow: <code><?= e(\Bnc\Settings::get('indexnow_key', '')) ?></code></p>
  <p>يدعم Bing وYandex ومحركات أخرى؛ Google لا يستخدم IndexNow. ملف التحقق متاح في الموقع فور توليد المفتاح.</p>
  <form method="post" action="<?= e(url('/seo/report')) ?>" data-confirm="توليد مفتاح جديد واستبدال المفتاح الحالي؟"><?= csrf_field() ?><button class="btn" name="action" value="generate">توليد مفتاح IndexNow</button></form>
</div>
<?php foreach ($groups as $level => $entities): ?>
<h2><?= e(['error' => 'أخطاء', 'warning' => 'تحذيرات', 'info' => 'معلومات'][$level]) ?></h2>
<?php foreach ($entities as $entity => $issues): ?>
<div class="card"><h3><?= e(['trip' => 'الرحلات', 'post' => 'المقالات', 'term' => 'التصنيفات', 'page' => 'الصفحات', 'redirect' => 'التحويلات', 'settings' => 'الإعدادات'][$entity]) ?></h3>
<ul><?php foreach ($issues as $issue): ?><li><strong><?= e($issue['title']) ?></strong>: <?= e($issue['message']) ?> <a href="<?= e(url($issue['edit_url'])) ?>">إصلاح</a></li><?php endforeach; ?></ul></div>
<?php endforeach; endforeach; ?>
<h2>آخر التقارير</h2>
<div class="table-wrap"><table><thead><tr><th>التاريخ</th><th>أخطاء</th><th>تحذيرات</th></tr></thead><tbody><?php foreach ($history as $row): ?><tr><td><?= e($row['created_at']) ?></td><td><?= (int) $row['error_count'] ?></td><td><?= (int) $row['warning_count'] ?></td></tr><?php endforeach; ?></tbody></table></div>
