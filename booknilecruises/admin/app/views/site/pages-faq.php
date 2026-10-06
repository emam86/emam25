<?php declare(strict_types=1); ?>
<?php ob_start(); ?>
  <section class="block">
    <div class="wrap faq">
      <?php foreach ($faqs as $i => [$q, $a]):  ?><details<?= bnc_attr('open', ($i === 0)) ?>><summary><?= e($q) ?></summary><p><?= $a ?></p></details><?php endforeach; ?>
    </div>
  </section>
  <?= \Bnc\Site\View::component('CtaBand', ['title' => 'Still have a question?']) ?>
<?php $childSlot = (string) ob_get_clean(); ?><?= \Bnc\Site\View::component('Page', ['title' => 'Faq - Book Nile cruises', 'heading' => 'Frequently asked questions', 'intro' => 'Booking, prices, what is included and how Nile cruises work.', 'description' => 'Answers about booking Nile cruises and Egypt tours with Book Nile Cruises: prices per person, what is included, cruise lengths, dahabiyas and transfers.', 'canonical' => '/faq/', 'jsonLd' => $jsonLd, 'slot' => $childSlot]) ?>

