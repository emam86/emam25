<?php declare(strict_types=1); ?>
<?php ob_start(); ?>
  <?= \Bnc\Site\View::component('PageHero', ['title' => 'Activities', 'intro' => 'Nile cruises, day tours and tour packages, grouped by type.', 'crumbs' => [['label' => 'Activities']], 'image' => bnc_asset('/images/2025/12/A-wonderful-picture-of-a-visitor-to-the-Karnak-Temple-in-Luxor.jpg')]) ?>
  <section class="block"><div class="wrap"><?= \Bnc\Site\View::component('TermGrid', ['terms' => $terms]) ?></div></section>
  <?= \Bnc\Site\View::component('CtaBand', []) ?>
<?php $childSlot = (string) ob_get_clean(); ?><?= \Bnc\Site\View::component('Base', ['title' => ((($page['seo'] ?? null)['title'] ?? null) ?: 'Activities - Book Nile cruises'), 'description' => 'Browse Egypt trips by activity: Nile cruises by class, day tours by city and multi-day tour packages.', 'canonical' => '/activities/', 'slot' => $childSlot]) ?>
