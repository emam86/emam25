<?php $labels = ['trips' => 'رحلة', 'terms' => 'تصنيف', 'media' => 'صورة', 'posts' => 'مقال']; $empty = !array_filter($existing); ?>
<div class="card stack">
  <p>الخطوة دي بتنقل محتوى الموقع الحالي (الرحلات، التصنيفات، بيانات الصور، المقالات) للوحة مرة واحدة، عشان تقدر تعدله من هنا. الصور نفسها موجودة بالفعل في مجلد <code dir="ltr">/images</code> ومش بتتنقل.</p>
  <?php if ($seed === null): ?>
    <p class="flash error">ملف الاستيراد <code dir="ltr">bnc-app/seed/export.json</code> غير موجود. ارفع حزمة اللوحة كاملة.</p>
  <?php else: ?>
    <p>الملف فيه: <?php foreach ($seed as $k => $n): ?><span class="tag"><?= (int) $n ?> <?= e($labels[$k]) ?></span><?php endforeach; ?></p>
  <?php endif; ?>
  <?php if (!$empty): ?>
    <p class="flash warn">اللوحة فيها محتوى بالفعل (<?php foreach ($existing as $k => $n) if ($n) echo (int) $n . ' ' . e($labels[$k]) . ' '; ?>)، فالاستيراد مقفول عشان ما يحصلش تكرار.</p>
  <?php elseif ($seed !== null): ?>
    <form method="post" action="<?= e(url('/import')) ?>" data-confirm="استيراد محتوى الموقع الحالي للوحة؟">
      <?= csrf_field() ?><button class="btn">ابدأ الاستيراد</button>
    </form>
  <?php endif; ?>
</div>
