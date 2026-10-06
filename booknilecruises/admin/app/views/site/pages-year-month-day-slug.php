<?php declare(strict_types=1); ?>
<?php ob_start(); ?>
  <?php ob_start(); ?>
    <p class="date"><?= e(date('j F Y', strtotime(($post['date'] ?? null)))) ?></p>
  <?php $childSlot = (string) ob_get_clean(); ?><?= \Bnc\Site\View::component('PageHero', ['title' => ($post['title'] ?? null), 'crumbs' => [['label' => 'Blog', 'href' => '/blog/'], ['label' => ($post['title'] ?? null)]], 'image' => (($post['image'] ?? null) ? bnc_asset((($post['image'] ?? null)['src'] ?? null)) : null), 'slot' => $childSlot]) ?>
  <section class="block"><div class="wrap"><article class="prose post"><?= ($post['html'] ?? null) ?></article></div></section>
  <?= \Bnc\Site\View::component('CtaBand', []) ?>
<?php $childSlot = (string) ob_get_clean(); ?><?= \Bnc\Site\View::component('Base', ['title' => ((($post['seo'] ?? null)['title'] ?? null) ?: ($post['title'] ?? null)), 'description' => (($post['seo'] ?? null)['description'] ?? null), 'canonical' => ($post['url'] ?? null), 'ogImage' => (($post['image'] ?? null)['src'] ?? null), 'jsonLd' => $jsonLd, 'slot' => $childSlot]) ?>

