<?php declare(strict_types=1); ?>
<?= \Bnc\View::partial('partials/errors', ['errors' => $errors]) ?>
<div class="card stack">
  <h2>إعدادات النشر</h2>
  <?php foreach (['token' => 'رمز GitHub', 'repo' => 'المستودع', 'workflow' => 'ملف سير العمل'] as $key => $label): ?><p><?= e($label) ?>: <span class="tag"><?= $config[$key] ? 'تم الإعداد' : 'غير مُعدّ' ?></span></p><?php endforeach; ?>
  <form method="post" action="<?= e(url('/publish')) ?>"><?= csrf_field() ?><button class="btn">نشر الموقع الآن</button></form>
</div>
<h2>آخر ٢٠ طلب نشر</h2>
<div class="table-wrap"><table><thead><tr><th>الوقت</th><th>بواسطة</th><th>الحالة</th><th>تشغيل GitHub</th><th>الرسالة</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?= e($row['created_at']) ?></td><td><?= e($row['triggered_by']) ?></td><td><span class="tag"><?= e(['queued' => 'في الانتظار', 'running' => 'قيد التنفيذ', 'succeeded' => 'نجح', 'failed' => 'فشل'][$row['status']]) ?></span></td><td><?php if ($row['run_url']): ?><a href="<?= e($row['run_url']) ?>" target="_blank" rel="noopener">عرض التشغيل ↗</a><?php endif; ?></td><td><?= e($row['message']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
