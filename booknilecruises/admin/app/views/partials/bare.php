<?php $flash = \Bnc\Session::takeFlash(); ?><!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title ?? 'لوحة التحكم') ?> · Book Nile Cruises</title>
<link rel="stylesheet" href="<?= e(url('/assets/admin.css')) ?>">
</head>
<body class="bare">
<main class="card narrow">
  <p class="brand-lg">Book Nile Cruises</p>
  <?php foreach ($flash as [$type, $msg]): ?>
    <p class="flash <?= e($type) ?>" role="status"><?= e($msg) ?></p>
  <?php endforeach; ?>
  <?= $content ?>
</main>
</body>
</html>
