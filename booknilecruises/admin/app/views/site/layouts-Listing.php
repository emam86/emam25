<?php declare(strict_types=1); ?>
<?php ob_start(); ?>
  <?= \Bnc\Site\View::component('PageHero', ['title' => $heading, 'intro' => $intro, 'crumbs' => $crumbs, 'image' => ($hero ? bnc_asset($hero) : null)]) ?>
  <section class="block">
    <div class="wrap">
      <?= $slot ?? "" ?>
      <?= \Bnc\Site\View::component('TripListing', ['trips' => $trips, 'emptyText' => $emptyText]) ?>
    </div>
  </section>
  <?= \Bnc\Site\View::component('CtaBand', []) ?>
<?php $childSlot = (string) ob_get_clean(); ?><?= \Bnc\Site\View::component('Base', ['title' => $title, 'description' => $description, 'canonical' => $canonical, 'ogImage' => $hero, 'jsonLd' => $jsonLd, 'slot' => $childSlot]) ?>
