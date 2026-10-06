<?php declare(strict_types=1); ?>
<?php if (can('media.upload')): ?>
<form method="post" action="<?= e(url('/media/upload')) ?>" enctype="multipart/form-data" class="stack card media-upload">
  <?= csrf_field() ?>
  <label>رفع صور <input type="file" name="photos[]" accept="image/*" multiple required></label>
  <span class="muted">JPEG، PNG، WebP أو GIF — حتى <?= e(\Bnc\Config::get('max_upload_mb', 15)) ?> ميجابايت للصورة و40 ميجابكسل.</span>
  <div><button class="btn">رفع الصور</button></div>
</form>
<?php endif; ?>
<form method="get" action="<?= e(url('/media')) ?>" class="filters">
  <label>بحث بالمسار أو النص البديل <input name="q" value="<?= e($q) ?>"></label>
  <button class="btn small">بحث</button>
  <span class="muted"><?= (int) $total ?> صورة</span>
</form>
<?php if (!$rows): ?><p class="muted">لا توجد صور.</p><?php endif; ?>
<div class="media-grid">
<?php foreach ($rows as $media): ?>
  <a class="card media-tile" href="<?= e(url("/media/{$media['id']}")) ?>">
    <img src="<?= e(\Bnc\Media\Files::url(\Bnc\Media\Files::thumb($media))) ?>" alt="<?= e($media['alt']) ?>" loading="lazy">
    <span><?= e($media['alt'] ?: basename($media['path'])) ?></span>
    <small class="muted" dir="ltr"><?= (int) $media['width'] ?> × <?= (int) $media['height'] ?></small>
  </a>
<?php endforeach; ?>
</div>
<?php if ($pages > 1): ?>
<nav class="pager">
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <?php if ($i === $page): ?><span aria-current="page"><?= $i ?></span>
    <?php else: ?><a href="<?= e(url('/media', ['q' => $q, 'page' => $i])) ?>"><?= $i ?></a><?php endif; ?>
  <?php endfor; ?>
</nav>
<?php endif; ?>
