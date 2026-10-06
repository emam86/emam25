<?php
/** @var string $content @var string $title */
$me = \Bnc\Auth::user();
$current = \Bnc\App::path();
$flash = \Bnc\Session::takeFlash();
?><!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title ?? 'لوحة التحكم') ?> · Book Nile Cruises</title>
<link rel="stylesheet" href="<?= e(url('/assets/admin.css')) ?>">
<script src="<?= e(url('/assets/admin.js')) ?>" defer></script>
</head>
<body>
<header class="top">
  <a class="brand" href="<?= e(url('/')) ?>">Book Nile Cruises <small>لوحة التحكم</small></a>
  <div class="who">
    <span><?= e($me['name']) ?> · <?= e($me['role']['name'] ?? '') ?></span>
    <a href="<?= e(\Bnc\Config::get('site_url', '/')) ?>" target="_blank" rel="noopener">الموقع ↗</a>
    <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="link">خروج</button></form>
  </div>
</header>
<div class="shell">
  <nav class="side" aria-label="القائمة">
    <ul>
      <?php foreach (\Bnc\Nav::visible() as $item):
        $active = $item['path'] === '/' ? $current === '/' : str_starts_with($current, $item['path']); ?>
        <li><a href="<?= e(url($item['path'])) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= e($item['label']) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </nav>
  <main>
    <h1><?= e($title ?? '') ?></h1>
    <?php foreach ($flash as [$type, $msg]): ?>
      <p class="flash <?= e($type) ?>" role="status"><?= e($msg) ?></p>
    <?php endforeach; ?>
    <?= $content ?>
  </main>
</div>
</body>
</html>
