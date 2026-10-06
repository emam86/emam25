<form method="get" action="<?= e(url('/audit')) ?>" class="filters">
  <label>العنصر
    <select name="entity">
      <option value="">الكل</option>
      <?php foreach ($entities as $en): ?><option<?= $en === $filters['entity'] ? ' selected' : '' ?>><?= e($en) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>المستخدم <input name="actor" value="<?= e($filters['actor']) ?>" dir="ltr"></label>
  <button class="btn small">تصفية</button>
  <span class="muted"><?= (int) $total ?> عملية</span>
</form>
<?= \Bnc\View::partial('audit/table', ['rows' => $rows]) ?>
<?php if ($pages > 1): ?>
  <nav class="pager">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <?php if ($i === $page): ?><span aria-current="page"><?= $i ?></span>
      <?php else: ?><a href="<?= e(url('/audit', array_filter($filters) + ['page' => $i])) ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
  </nav>
<?php endif; ?>
