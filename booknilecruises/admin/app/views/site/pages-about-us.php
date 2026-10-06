<?php declare(strict_types=1); ?>
<?php ob_start(); ?>
  <section class="block">
    <div class="wrap about">
      <div class="prose">
        <h2>Who we are</h2>
        <p>Book Nile Cruises is run from our office on Khaled Ibn El Waleed Street in Luxor. We arrange Nile cruises between Luxor and Aswan, dahabiya sailings, Lake Nasser cruises, day tours and multi-day tour packages around Egypt.</p>
        <p>Every trip on this site lists its full itinerary, what is included and what is not, and a per-person price in US dollars, so you can compare before you write to us.</p>
        <h2>How booking works</h2>
        <p>Choose a trip, then send us your travel dates and the number of travellers on WhatsApp or by email. We confirm availability for your dates and reply with the details. There is no online checkout: you talk to a person from the first message.</p>
        <p><a class="btn btn-wa"<?= bnc_attr('href', bnc_whatsapp('Hello, I would like to plan a trip.')) ?>>WhatsApp <?= e(($SITE['phoneDisplay'] ?? null)) ?></a></p>
      </div>
      <aside class="facts">
        <dl>
          <div><dt>Nile cruises</dt><dd><?= e(count(($nile['trips'] ?? null) ?? [])) ?></dd></div>
          <div><dt>Day tours</dt><dd><?= e(count(($day['trips'] ?? null) ?? [])) ?></dd></div>
          <div><dt>Tour packages</dt><dd><?= e(count(($pkg['trips'] ?? null) ?? [])) ?></dd></div>
          <div><dt>Office</dt><dd class="small"><?= e(($SITE['address'] ?? null)) ?></dd></div>
        </dl>
      </aside>
    </div>
  </section>
  <section class="block band"><div class="wrap"><?= \Bnc\Site\View::component('TermGrid', ['terms' => $cats]) ?></div></section>
  <?= \Bnc\Site\View::component('CtaBand', []) ?>
<?php $childSlot = (string) ob_get_clean(); ?><?= \Bnc\Site\View::component('Page', ['title' => 'About Us - Book Nile cruises', 'heading' => 'About Book Nile Cruises', 'intro' => 'A travel team based in Luxor, on the Nile, arranging cruises and tours across Egypt.', 'description' => 'Book Nile Cruises is a Luxor-based team arranging Nile cruises, dahabiyas, day tours and Egypt tour packages, booked directly on WhatsApp or by email.', 'canonical' => '/about-us/', 'image' => '/images/2025/12/Felucca-from-Aswan.jpg', 'slot' => $childSlot]) ?>

