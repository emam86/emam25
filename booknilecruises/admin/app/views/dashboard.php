<?php declare(strict_types=1); ?>
<?php if (\Bnc\Auth::user()['is_owner'] && !\Bnc\Content\Importer::blockers()): ?>
  <div class="flash warn">اللوحة لسه فاضية. <a href="<?= e(url('/import')) ?>">انقل محتوى الموقع الحالي للوحة</a> (مرة واحدة).</div>
<?php endif; ?>
<?php if ($pending): ?>
  <div class="flash warn">
    في تحديثات لقاعدة البيانات لم تُطبّق بعد: <?= e(implode('، ', $pending)) ?>
    <form method="post" action="<?= e(url('/system/migrate')) ?>" class="inline"><?= csrf_field() ?><button class="btn small">طبّق التحديث</button></form>
  </div>
<?php endif; ?>
<div class="stats">
  <?php foreach ($stats as $label => $n): ?>
    <div class="stat"><strong><?= (int) $n ?></strong><span><?php if ($label === 'استفسارات جديدة' && can('enquiries.view')): ?><a href="<?= e(url('/enquiries')) ?>"><?= e($label) ?></a><?php else: ?><?= e($label) ?><?php endif; ?></span></div>
  <?php endforeach; ?>
</div>
<?php if ($recent): ?>
  <h2>آخر العمليات</h2>
  <?= \Bnc\View::partial('audit/table', ['rows' => $recent]) ?>
  <p><a href="<?= e(url('/audit')) ?>">كل السجل ←</a></p>
<?php endif; ?>

<?php if (can('seo.edit')): ?><p class="card"><a href="<?= e(url('/seo/report')) ?>">فحص SEO</a> · <?= $seoReport ? 'أخطاء: ' . (int) $seoReport['error_count'] . ' · تحذيرات: ' . (int) $seoReport['warning_count'] : 'لم يُجرَ فحص بعد.' ?></p><?php endif; ?>
