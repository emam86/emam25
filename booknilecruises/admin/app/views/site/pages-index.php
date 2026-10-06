<?php declare(strict_types=1); ?>
<?php ob_start(); ?>
  <section class="hero">
    <img class="bg"<?= bnc_attr('src', bnc_asset('/images/2025/12/Merit-Dahabiya-Nile-Cruise1.jpg')) ?> alt="" fetchpriority="high" />
    <div class="wrap">
      <span class="rule eyebrow">Luxor · Aswan · Lake Nasser</span>
      <h1>Explore Luxor & Aswan <em>by cruise</em></h1>
      <p>Nile cruises, dahabiyas, day tours and Egypt tour packages, booked directly with our team in Luxor.</p>
      <div class="ctas">
        <a class="btn btn-gold" href="/nile-cruise/">Find your cruise</a>
        <a class="btn btn-ghost"<?= bnc_attr('href', bnc_whatsapp('Hello, I would like help choosing a Nile cruise.')) ?>>WhatsApp an expert</a>
      </div>
    </div>
  </section>

  <div class="search">
    <div class="wrap">
      <form action="/trip/" method="get" id="finder">
        <label for="f-dest">Destination
          <select id="f-dest" name="destination">
            <option value="">Anywhere in Egypt</option>
            <?php foreach (array_values(array_filter((($site['terms'] ?? null)['destination'] ?? null), fn($d) => count(($d['trips'] ?? null) ?? []))) as $d):  ?><option<?= bnc_attr('value', ($d['slug'] ?? null)) ?>><?= e(($d['name'] ?? null)) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label for="f-type">Trip type
          <select id="f-type" name="type">
            <option value="/trip/">All trips</option>
            <option value="/nile-cruise/">Nile cruise</option>
            <option value="/luxury-dahabiya-nile-cruise-packages/">Dahabiya</option>
            <option value="/lake-nasser-nile-cruises/">Lake Nasser cruise</option>
            <option value="/day-tours/">Day tour</option>
            <option value="/egypt-tour-packages/">Tour package</option>
          </select>
        </label>
        <button type="submit">Search trips</button>
      </form>
    </div>
  </div>

  <section class="block">
    <div class="wrap">
      <div class="head">
        <span class="eyebrow">Choose your ship</span>
        <h2>Nile cruises by class</h2>
        <p>Every class sails the same river and visits the same temples. The difference is space, service and how many guests share the deck.</p>
      </div>
      <div class="classes">
        <?php foreach ($classes as $c):  ?><a class="cls"<?= bnc_attr('href', ($c['href'] ?? null)) ?>>
            <?php if (($c['img'] ?? null)): ?><img<?= bnc_attr('src', ($c['img'] ?? null)) ?><?= bnc_attr('srcset', ($c['srcset'] ?? null)) ?> sizes="(max-width: 980px) calc((100vw - 56px) / 2), (max-width: 1180px) calc((100vw - 88px) / 4), 273px" alt="" loading="lazy" decoding="async" /><?php endif; ?>
            <div><small><?= e(($c['count'] ?? null)) ?> <?= e(($c['noun'] ?? null)) ?><?php if ((($c['from'] ?? null) != null)): ?><?= e((' · from ' . bnc_money(($c['from'] ?? null)) . '')) ?><?php endif; ?></small><h3><?= e(($c['label'] ?? null)) ?></h3></div>
          </a><?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="block band">
    <div class="wrap">
      <div class="head"><span class="eyebrow">Most popular</span><h2>Tour packages our guests book most</h2></div>
      <div class="grid3"><?php foreach ($popular as $t):  ?><?= \Bnc\Site\View::component('TripCard', ['trip' => $t]) ?><?php endforeach; ?></div>
      <p class="more"><a class="btn btn-outline" href="/trip/">See all <?= e(count(($site['trips'] ?? null) ?? [])) ?> trips</a></p>
    </div>
  </section>

  <section class="block">
    <div class="wrap exp">
      <div class="pic"><img<?= bnc_attr('src', bnc_asset('/images/2025/12/Felucca-from-Aswan.jpg')) ?> alt="Feluccas sailing on the Nile at Aswan" loading="lazy" width="1200" height="800" /></div>
      <div>
        <span class="eyebrow">Signature experiences</span>
        <h2>Why travel Egypt on a Nile cruise</h2>
        <div class="feats">
          <div><h3>Convenience</h3><p>Sail between Luxor and Aswan without airports, long transfers or packing again during your trip.</p></div>
          <div><h3>Value for money</h3><p>Full-board stays, guided tours and travel between the sites in one price.</p></div>
          <div><h3>Perfect for all ages</h3><p>Families, seniors, couples and solo travellers all find the pace comfortable.</p></div>
          <div><h3>Scenic views</h3><p>Wake up to green banks, golden hills and temples passing by your window.</p></div>
          <div><h3>Authentic dining</h3><p>Fresh Egyptian dishes alongside international food, prepared on board every day.</p></div>
          <div><h3>Multiple destinations</h3><p>Luxor, Edfu, Kom Ombo and Aswan in one itinerary.</p></div>
        </div>
      </div>
    </div>
  </section>

  <section class="block band">
    <div class="wrap">
      <div class="head"><span class="eyebrow">Top destinations</span><h2>Popular Egypt destinations</h2></div>
      <div class="dests">
        <?php foreach ($destinations as $i => $d):  ?><a<?= bnc_attr('href', ($d['url'] ?? null)) ?>>
            <img<?= bnc_attr('src', bnc_asset(((($destImg($d)['card'] ?? null)['src'] ?? null) ?? ($destImg($d)['src'] ?? null)))) ?><?= bnc_attr('srcset', bnc_srcset($destImg($d))) ?><?= bnc_attr('sizes', (($i === 0) ? '(max-width: 620px) calc(100vw - 40px), (max-width: 1180px) calc((100vw - 72px) / 2), 554px' : '(max-width: 620px) calc((100vw - 56px) / 2), (max-width: 1180px) calc((100vw - 72px) / 4), 277px')) ?> alt="" loading="lazy" decoding="async" />
            <span><?= e(($d['name'] ?? null)) ?><small><?= e(count(($d['trips'] ?? null) ?? [])) ?> trips</small></span>
          </a><?php endforeach; ?>
      </div>
    </div>
  </section>

  <?php if ($post): ?><section class="block">
      <div class="wrap article">
        <?php if (($post['image'] ?? null)): ?><a<?= bnc_attr('href', ($post['url'] ?? null)) ?>><img<?= bnc_attr('src', bnc_asset((($post['image'] ?? null)['src'] ?? null))) ?> alt="" loading="lazy"<?= bnc_attr('width', (($post['image'] ?? null)['width'] ?? null)) ?><?= bnc_attr('height', (($post['image'] ?? null)['height'] ?? null)) ?> /></a><?php endif; ?>
        <div>
          <span class="eyebrow">From the blog</span>
          <h2><a<?= bnc_attr('href', ($post['url'] ?? null)) ?>><?= e(($post['title'] ?? null)) ?></a></h2>
          <p><?= e(preg_replace('/\\s+\\S*$/', '', mb_substr(($post['text'] ?? ''), 0, 220))) ?>…</p>
          <a class="btn btn-outline"<?= bnc_attr('href', ($post['url'] ?? null)) ?>>Read the article</a>
        </div>
      </div>
    </section><?php endif; ?>

  <?= \Bnc\Site\View::component('CtaBand', []) ?>
<?php $childSlot = (string) ob_get_clean(); ?><?= \Bnc\Site\View::component('Base', ['title' => 'Book Nile cruises - Book the Best Nile Cruises in Egypt', 'description' => ('Book Nile cruises between Luxor and Aswan, dahabiyas, Lake Nasser cruises, day tours and Egypt tour packages directly with our team in Luxor. ' . $totalCruises . ' cruises with itineraries and prices.'), 'canonical' => '/', 'ogImage' => '/images/2025/12/Merit-Dahabiya-Nile-Cruise1.jpg', 'jsonLd' => $jsonLd, 'slot' => $childSlot]) ?>




