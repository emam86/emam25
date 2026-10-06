<?php declare(strict_types=1); ?>
<?php ob_start(); ?>
  <?= \Bnc\Site\View::component('PageHero', ['title' => 'Destinations', 'intro' => 'Where our trips go. Pick a city to see its Nile cruises, day tours and packages.', 'crumbs' => [['label' => 'Destinations']], 'image' => bnc_asset('/images/2025/12/A-wonderful-picture-of-a-visitor-to-the-Karnak-Temple-in-Luxor.jpg')]) ?>
  <section class="block"><div class="wrap"><?= \Bnc\Site\View::component('TermGrid', ['terms' => $terms]) ?></div></section>
  <?= \Bnc\Site\View::component('CtaBand', []) ?>
<?php $childSlot = (string) ob_get_clean(); ?><?= \Bnc\Site\View::component('Base', ['title' => ((($page['seo'] ?? null)['title'] ?? null) ?: 'Destinations - Book Nile cruises'), 'description' => 'Egypt destinations with Book Nile Cruises: Luxor, Aswan, Cairo, Alexandria, Hurghada, Sharm El Sheikh and Siwa.', 'canonical' => '/destinations/', 'slot' => $childSlot]) ?>
