<?php declare(strict_types=1); ?>
<?php ob_start(); ?>
  <section class="block"><div class="wrap">
    <p><a class="btn btn-navy" href="/nile-cruise/">Nile cruises</a> <a class="btn btn-outline" href="/trip/">All trips</a> <a class="btn btn-outline" href="/">Home</a></p>
  </div></section>
<?php $childSlot = (string) ob_get_clean(); ?><?= \Bnc\Site\View::component('Page', ['title' => 'Page not found - Book Nile cruises', 'heading' => 'Page not found', 'intro' => 'The page you were looking for has moved or no longer exists.', 'noindex' => true, 'slot' => $childSlot]) ?>
