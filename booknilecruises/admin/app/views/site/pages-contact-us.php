<?php declare(strict_types=1); ?>
<?php ob_start(); ?>
  <section class="block">
    <div class="wrap contact">
      <div class="ways">
        <a class="way"<?= bnc_attr('href', bnc_whatsapp('Hello, I would like to ask about a trip.')) ?>>
          <span class="eyebrow">WhatsApp</span><strong><?= e(($SITE['phoneDisplay'] ?? null)) ?></strong><small>Fastest reply</small>
        </a>
        <a class="way"<?= bnc_attr('href', bnc_mailto('Trip enquiry')) ?>>
          <span class="eyebrow">Email</span><strong><?= e(($SITE['email'] ?? null)) ?></strong><small>For detailed requests</small>
        </a>
        <div class="way">
          <span class="eyebrow">Phone</span><strong><?= e(($SITE['phoneDisplay'] ?? null)) ?></strong><small>Also <?= e(($SITE['phoneAlt'] ?? null)) ?></small>
        </div>
        <div class="way">
          <span class="eyebrow">Office</span><strong><?= e(($SITE['address'] ?? null)) ?></strong>
        </div>
      </div>
      <?= \Bnc\Site\View::component('Enquiry', ['id' => 'contact-enq']) ?>
    </div>
  </section>
<?php $childSlot = (string) ob_get_clean(); ?><?= \Bnc\Site\View::component('Page', ['title' => 'Contact Us - Book Nile cruises', 'heading' => 'Contact us', 'intro' => 'Send us your travel dates and the trip you are interested in. We reply on WhatsApp or by email.', 'description' => 'Contact Book Nile Cruises in Luxor on WhatsApp +20 109 661 1124 or by email at info@booknilecruises.net to book a Nile cruise or Egypt tour.', 'canonical' => '/contact-us/', 'image' => '/images/2025/12/Movenpick-Prince-Abbas-Lake-Cruise1.jpg', 'slot' => $childSlot]) ?>

