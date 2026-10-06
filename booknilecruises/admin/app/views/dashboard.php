<?php if ($pending): ?>
  <div class="flash warn">
    في تحديثات لقاعدة البيانات لم تُطبّق بعد: <?= e(implode('، ', $pending)) ?>
    <form method="post" action="<?= e(url('/system/migrate')) ?>" class="inline"><?= csrf_field() ?><button class="btn small">طبّق التحديث</button></form>
  </div>
<?php endif; ?>
<div class="stats">
  <?php foreach ($stats as $label => $n): ?>
    <div class="stat"><strong><?= (int) $n ?></strong><span><?= e($label) ?></span></div>
  <?php endforeach; ?>
</div>
<?php if ($recent): ?>
  <h2>آخر العمليات</h2>
  <?= \Bnc\View::partial('audit/table', ['rows' => $recent]) ?>
  <p><a href="<?= e(url('/audit')) ?>">كل السجل ←</a></p>
<?php endif; ?>
