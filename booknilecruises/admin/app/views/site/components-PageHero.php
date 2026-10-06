<?php declare(strict_types=1); ?>
<section class="page-hero">
  <?php if ($image): ?><img class="bg"<?= bnc_attr('src', $image) ?> alt="" /><?php endif; ?>
  <div class="wrap">
    <?php if ((count($crumbs ?? []) > 0)): ?><nav class="crumbs" aria-label="Breadcrumb">
        <a href="/">Home</a>
        <?php foreach ($crumbs as $c):  ?><span aria-hidden="true">/</span><?php if (($c['href'] ?? null)): ?><a<?= bnc_attr('href', ($c['href'] ?? null)) ?>><?= e(($c['label'] ?? null)) ?></a><?php else: ?><span aria-current="page"><?= e(($c['label'] ?? null)) ?></span><?php endif; ?><?php endforeach; ?>
      </nav><?php endif; ?>
    <h1><?= e($title) ?></h1>
    <?php if ($intro): ?><p><?= e($intro) ?></p><?php endif; ?>
    <?= $slot ?? "" ?>
  </div>
</section>
