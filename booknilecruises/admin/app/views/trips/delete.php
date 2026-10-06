<?php declare(strict_types=1); ?>
<?= \Bnc\View::partial('partials/errors', ['errors' => $errors]) ?>
<form method="post" action="<?= e(url("/trips/{$trip['id']}/delete")) ?>" class="stack card" data-confirm="حذف الرحلة نهائيًا؟">
  <?= csrf_field() ?>
  <p>حذف <?= e($trip['title']) ?>؟ ستبقى الصور في المكتبة.</p>
<?php if ($trip['status'] === 'published'): ?>
  <label>تحويل الرابط القديم إلى <select name="redirect_to" required><?php foreach ($targets as $path => $label): ?><option value="<?= e($path) ?>"<?= $target === $path ? ' selected' : '' ?>><?= e($label) ?> — <?= e($path) ?></option><?php endforeach; ?></select></label>
<?php endif; ?>
  <div class="row"><button class="btn danger">حذف</button><a href="<?= e(url('/trips')) ?>">إلغاء</a></div>
</form>
