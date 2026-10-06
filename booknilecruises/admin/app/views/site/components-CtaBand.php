<?php declare(strict_types=1); ?>
<section class="cta-band"<?= bnc_attr('style', ('background-image: linear-gradient(rgba(22, 45, 75, .86), rgba(22, 45, 75, .86)), url("' . bnc_asset('/images/2025/12/Felucca-from-Aswan.jpg') . '")')) ?>>
  <div class="wrap">
    <span class="eyebrow">Plan with our Luxor team</span>
    <h2><?= e($title) ?></h2>
    <p><?= e($text) ?></p>
    <div class="acts">
      <a class="btn btn-wa"<?= bnc_attr('href', bnc_whatsapp('Hello, I would like to plan a trip.')) ?>>WhatsApp <?= e(($SITE['phoneDisplay'] ?? null)) ?></a>
      <a class="btn btn-ghost"<?= bnc_attr('href', bnc_mailto('Trip enquiry')) ?>>Email <?= e(($SITE['email'] ?? null)) ?></a>
    </div>
  </div>
</section>

