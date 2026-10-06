<?php
declare(strict_types=1);
$publicUrl = rtrim((string) \Bnc\Config::get('site_url'), '/') . $media['path'];
?>
<p><a href="<?= e(url('/media')) ?>">العودة إلى الصور</a></p>
<?= \Bnc\View::partial('partials/errors', ['errors' => $errors]) ?>
<div class="stack card">
  <img class="media-preview" src="<?= e(\Bnc\Media\Files::url($media['path'])) ?>" alt="<?= e($media['alt']) ?>">
  <dl class="media-meta">
    <dt>الأبعاد</dt><dd dir="ltr"><?= (int) $media['width'] ?> × <?= (int) $media['height'] ?> px</dd>
    <dt>الحجم</dt><dd><?= e(number_format((int) $media['filesize'] / 1024, 1)) ?> كيلوبايت</dd>
    <dt>النوع</dt><dd><?= e($media['mime']) ?></dd>
    <dt>المسار</dt><dd dir="ltr"><?= e($media['path']) ?></dd>
    <dt>الرابط العام</dt><dd dir="ltr"><a href="<?= e($publicUrl) ?>" target="_blank" rel="noopener"><?= e($publicUrl) ?></a></dd>
  </dl>
  <div><button type="button" class="btn small" data-copy="<?= e($publicUrl) ?>">نسخ الرابط</button> <span data-copy-status role="status" aria-live="polite"></span></div>
  <?php if (can('media.upload')): ?>
  <form method="post" action="<?= e(url("/media/{$media['id']}/edit")) ?>" class="stack">
    <?= csrf_field() ?>
    <label>النص البديل <input name="alt" maxlength="255" value="<?= e($media['alt']) ?>"></label>
    <div><button class="btn">حفظ النص البديل</button></div>
  </form>
  <?php else: ?><p>النص البديل: <?= e($media['alt'] ?: '—') ?></p><?php endif; ?>
</div>
<h2>النسخ المصغرة</h2>
<?php if (!$sizes): ?><p class="muted">لا توجد نسخ مصغرة؛ الصورة أصغر من المقاسات المطلوبة.</p>
<?php else: ?>
<div class="table-wrap">
<table>
  <thead><tr><th>المقاس</th><th>الأبعاد</th><th>الملف</th></tr></thead>
  <tbody>
  <?php foreach ($sizes as $key => $size): ?>
    <?php if (!is_array($size) || !isset($size['file']) || basename($size['file']) !== $size['file']) continue; ?>
    <tr><td><?= e($key) ?></td><td dir="ltr"><?= (int) $size['width'] ?> × <?= (int) $size['height'] ?></td>
      <td><a href="<?= e(\Bnc\Media\Files::url(dirname($media['path']) . '/' . $size['file'])) ?>" target="_blank" rel="noopener"><?= e($size['file']) ?></a></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<h2>مستخدمة في</h2>
<?php if (!$uses): ?><p class="muted">الصورة غير مستخدمة.</p>
<?php else: ?>
<ul><?php foreach ($uses as $use): ?>
  <li><?= $use['type'] === 'trip' ? 'رحلة' : 'مقال' ?>: <a href="<?= e(url($use['url'])) ?>"><?= e($use['title']) ?></a></li>
<?php endforeach; ?></ul>
<?php endif; ?>
<?php if (can('media.delete')): ?>
<form method="post" action="<?= e(url("/media/{$media['id']}/delete")) ?>" data-confirm="حذف الصورة وكل نسخها نهائيًا؟">
  <?= csrf_field() ?><button class="btn danger">حذف الصورة</button>
</form>
<?php endif; ?>
