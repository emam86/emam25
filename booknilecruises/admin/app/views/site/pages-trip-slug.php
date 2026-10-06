<?php declare(strict_types=1); ?>
<?php ob_start(); ?>
  <section class="trip-head">
    <div class="wrap">
      <nav class="crumbs" aria-label="Breadcrumb">
        <a href="/">Home</a>
        <?php foreach ($crumbs as $c):  ?><span aria-hidden="true">/</span><?php if (($c['href'] ?? null)): ?><a<?= bnc_attr('href', ($c['href'] ?? null)) ?>><?= e(($c['label'] ?? null)) ?></a><?php else: ?><span aria-current="page"><?= e(($c['label'] ?? null)) ?></span><?php endif; ?><?php endforeach; ?>
      </nav>
      <h1><?= e(($trip['title'] ?? null)) ?></h1>
      <ul class="facts">
        <?php if ($duration): ?><li><span>Duration</span><?= e($duration) ?></li><?php endif; ?>
        <?php if ((count(($trip['destinations'] ?? null) ?? []) > 0)): ?><li><span>Places</span><?= e(bnc_places($trip)) ?></li><?php endif; ?>
        <?php if ($primary): ?><li><span>Type</span><a<?= bnc_attr('href', ($primary['url'] ?? null)) ?>><?= e(($primary['name'] ?? null)) ?></a></li><?php endif; ?>
        <li><span>From</span><?php if ($price): ?><strong><?= e($price) ?></strong><?php else: ?><?= e('On request') ?><?php endif; ?><?php if ($price): ?><small> per person</small><?php endif; ?></li>
      </ul>
    </div>
  </section>

  <?php if ((count($gallery ?? []) > 0)): ?><section class="gallery" aria-label="Photos">
      <div class="track" id="gal" tabindex="0">
        <?php foreach ($gallery as $i => $img):  ?><figure>
            <img<?= bnc_attr('src', bnc_asset(($img['src'] ?? null))) ?><?= bnc_attr('alt', (($i === 0) ? ($trip['title'] ?? null) : ('' . ($trip['title'] ?? null) . ' photo ' . ($i + 1) . ''))) ?><?= bnc_attr('width', ($img['width'] ?? null)) ?><?= bnc_attr('height', ($img['height'] ?? null)) ?><?= bnc_attr('loading', (($i < 2) ? 'eager' : 'lazy')) ?><?= bnc_attr('fetchpriority', (($i === 0) ? 'high' : null)) ?> decoding="async" />
          </figure><?php endforeach; ?>
      </div>
      <?php if ((count($gallery ?? []) > 1)): ?><div class="gal-nav">
          <button type="button" data-dir="-1" aria-controls="gal" aria-label="Previous photo">‹</button>
          <button type="button" data-dir="1" aria-controls="gal" aria-label="Next photo">›</button>
        </div><?php endif; ?>
    </section><?php endif; ?>

  <div class="wrap trip-body">
    <article class="trip-main">
      <?php if ((count(($trip['highlights'] ?? null) ?? []) > 0)): ?><section class="sec" id="highlights">
          <h2>Trip highlights</h2>
          <ul class="ticks"><?php foreach (($trip['highlights'] ?? null) as $h):  ?><li><?= e($h) ?></li><?php endforeach; ?></ul>
        </section><?php endif; ?>

      <?php if (($trip['overviewHtml'] ?? null)): ?><section class="sec prose" id="overview">
          <h2>Overview</h2>
          <?= ($trip['overviewHtml'] ?? null) ?>
        </section><?php endif; ?>

      <?php if ((count(($trip['itinerary'] ?? null) ?? []) > 0)): ?><section class="sec" id="itinerary">
          <h2>Itinerary</h2>
          <div class="days">
            <?php foreach ($days as $i => $day):  ?><?php if (($day['n'] ?? null)): ?><details<?= bnc_attr('open', ($i < 2)) ?>>
                  <summary><span class="day-n">Day <?= e(str_pad(strval(($day['n'] ?? null)), 2, '0', STR_PAD_LEFT)) ?></span><?= e(($day['title'] ?? null)) ?></summary>
                  <div class="prose"><?= ($day['html'] ?? null) ?></div>
                </details><?php else: ?><h3 class="day-group"><?= e(($day['title'] ?? null)) ?></h3><?php endif; ?><?php endforeach; ?>
          </div>
        </section><?php endif; ?>

      <?php if (((count(($trip['includes'] ?? null) ?? []) > 0) ?: (count(($trip['excludes'] ?? null) ?? []) > 0))): ?><section class="sec cost" id="cost">
          <h2>What is included</h2>
          <div class="cols">
            <?php if ((count(($trip['includes'] ?? null) ?? []) > 0)): ?><div><h3>Included</h3><ul class="ticks"><?php foreach (($trip['includes'] ?? null) as $x):  ?><li><?= e($x) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <?php if ((count(($trip['excludes'] ?? null) ?? []) > 0)): ?><div><h3>Not included</h3><ul class="crosses"><?php foreach (($trip['excludes'] ?? null) as $x):  ?><li><?= e($x) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
          </div>
        </section><?php endif; ?>

      <?php if ((count(($trip['faqs'] ?? null) ?? []) > 0)): ?><section class="sec" id="faq">
          <h2>Questions about this trip</h2>
          <div class="faqs">
            <?php foreach (($trip['faqs'] ?? null) as $f):  ?><details><summary><?= e(($f['q'] ?? null)) ?></summary><div class="prose"><?= ($f['a'] ?? null) ?></div></details><?php endforeach; ?>
          </div>
        </section><?php endif; ?>
    </article>

    <aside class="trip-side">
      <div class="price-box">
        <span class="eyebrow">From</span>
        <div class="big"><?= e(($price ?? 'On request')) ?></div>
        <?php if ($price): ?><p>per person<?php if ((($trip['minPax'] ?? null) > 1)): ?><?= e((', minimum ' . ($trip['minPax'] ?? null) . ' travellers')) ?><?php else: ?><?= e('') ?><?php endif; ?></p><?php endif; ?>
        <a class="btn btn-wa"<?= bnc_attr('href', bnc_whatsapp(('Hello, I would like to book: ' . ($trip['title'] ?? null) . ''))) ?>>Book on WhatsApp</a>
      </div>
      <?= \Bnc\Site\View::component('Enquiry', ['trip' => $trip, 'id' => 'trip-enq']) ?>
    </aside>
  </div>

  <?php if ((count($related ?? []) > 0)): ?><section class="block band">
      <div class="wrap">
        <div class="head"><span class="eyebrow"><?= e(($primary['name'] ?? null)) ?></span><h2>You may also like</h2></div>
        <div class="grid3"><?php foreach ($related as $t):  ?><?= \Bnc\Site\View::component('TripCard', ['trip' => $t]) ?><?php endforeach; ?></div>
      </div>
    </section><?php endif; ?>
<?php $childSlot = (string) ob_get_clean(); ?><?= \Bnc\Site\View::component('Base', ['title' => ((($trip['seo'] ?? null)['title'] ?? null) ?: ('' . ($trip['title'] ?? null) . ' - Book Nile cruises')), 'description' => ((($trip['seo'] ?? null)['description'] ?? null) ?: ($trip['excerpt'] ?? null)), 'canonical' => ((($trip['seo'] ?? null)['canonical'] ?? null) ?? ($trip['url'] ?? null)), 'ogImage' => ((($trip['seo'] ?? null)['ogImage'] ?? null) ?? (($trip['image'] ?? null)['src'] ?? null)), 'jsonLd' => $jsonLd, 'slot' => $childSlot]) ?>




