<?php declare(strict_types=1); ?>
<?php ob_start(); ?>
  <?= \Bnc\Site\View::component('PageHero', ['title' => $heading, 'intro' => $intro, 'crumbs' => ($crumbs ?? [['label' => $heading]]), 'image' => bnc_asset($image)]) ?>
  <?= $slot ?? "" ?>
<?php $childSlot = (string) ob_get_clean(); ?><?= \Bnc\Site\View::component('Base', ['title' => $title, 'description' => $description, 'canonical' => $canonical, 'ogImage' => $image, 'jsonLd' => $jsonLd, 'noindex' => $noindex, 'slot' => $childSlot]) ?>
