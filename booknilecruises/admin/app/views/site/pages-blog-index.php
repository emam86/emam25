<?php declare(strict_types=1); ?>
<?php ob_start(); ?>
  <section class="block">
    <div class="wrap grid3">
      <?php foreach ($posts as $p):  ?><article class="card">
          <div class="ph"><?php if (($p['image'] ?? null)): ?><img<?= bnc_attr('src', bnc_asset((((($p['image'] ?? null)['card'] ?? null)['src'] ?? null) ?? (($p['image'] ?? null)['src'] ?? null)))) ?><?= bnc_attr('srcset', bnc_srcset(($p['image'] ?? null))) ?> sizes="(max-width: 620px) calc(100vw - 40px), (max-width: 980px) calc((100vw - 66px) / 2), (max-width: 1180px) calc((100vw - 92px) / 3), 362.67px" alt="" loading="lazy"<?= bnc_attr('width', (((($p['image'] ?? null)['card'] ?? null)['width'] ?? null) ?? (($p['image'] ?? null)['width'] ?? null))) ?><?= bnc_attr('height', (((($p['image'] ?? null)['card'] ?? null)['height'] ?? null) ?? (($p['image'] ?? null)['height'] ?? null))) ?> /><?php endif; ?></div>
          <div class="bd">
            <span class="eyebrow"><?= e(date('j F Y', strtotime(($p['date'] ?? null)))) ?></span>
            <h3><a<?= bnc_attr('href', ($p['url'] ?? null)) ?>><?= e(($p['title'] ?? null)) ?></a></h3>
            <p class="meta"><?= e(preg_replace('/\\s+\\S*$/', '', mb_substr(($p['text'] ?? ''), 0, 160))) ?>…</p>
          </div>
        </article><?php endforeach; ?>
    </div>
  </section>
<?php $childSlot = (string) ob_get_clean(); ?><?= \Bnc\Site\View::component('Page', ['title' => 'Blog - Book Nile cruises', 'heading' => 'Blog', 'intro' => 'Guides to the temples, cities and cruises of Egypt.', 'description' => 'Travel guides from Book Nile Cruises about Luxor, Aswan, the Nile and Egypt\'s temples.', 'canonical' => '/blog/', 'slot' => $childSlot]) ?>
