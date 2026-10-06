<?php declare(strict_types=1); ?>
<?php ob_start(); ?>
  <?= \Bnc\Site\View::component('PageHero', ['title' => 'Trip types', 'intro' => 'Our trips grouped by type.', 'crumbs' => [['label' => 'Trip types']], 'image' => bnc_asset('/images/2025/12/A-wonderful-picture-of-a-visitor-to-the-Karnak-Temple-in-Luxor.jpg')]) ?>
  <section class="block"><div class="wrap"><?= \Bnc\Site\View::component('TermGrid', ['terms' => $terms]) ?></div></section>
  <?= \Bnc\Site\View::component('CtaBand', []) ?>
<?php $childSlot = (string) ob_get_clean(); ?><?= \Bnc\Site\View::component('Base', ['title' => ((($page['seo'] ?? null)['title'] ?? null) ?: 'Trip types - Book Nile cruises'), 'description' => 'Browse Egypt trips by type: Nile cruises and day tours in Luxor, Aswan, Cairo and Hurghada.', 'canonical' => '/trip-types/', 'slot' => $childSlot]) ?>
