<?php declare(strict_types=1); ?>
<article class="card"<?= bnc_attr('data-destinations', implode(' ', array_map(fn($d) => ($d['slug'] ?? null), ($trip['destinations'] ?? null)))) ?><?= bnc_attr('data-activities', implode(' ', array_map(fn($a) => ($a['slug'] ?? null), ($trip['activities'] ?? null)))) ?><?= bnc_attr('data-days', ((($trip['duration'] ?? null)['days'] ?? null) ?? '')) ?>>
  <div class="ph">
    <?php if ($img): ?><img<?= bnc_attr('src', bnc_asset(($img['src'] ?? null))) ?><?= bnc_attr('srcset', bnc_srcset(($trip['image'] ?? null))) ?> sizes="(max-width: 620px) calc(100vw - 40px), (max-width: 980px) calc((100vw - 66px) / 2), (max-width: 1180px) calc((100vw - 92px) / 3), 362.67px"<?= bnc_attr('alt', ((($trip['image'] ?? null)['alt'] ?? null) ?: ($trip['title'] ?? null))) ?><?= bnc_attr('width', ($img['width'] ?? null)) ?><?= bnc_attr('height', ($img['height'] ?? null)) ?><?= bnc_attr('loading', ($eager ? 'eager' : 'lazy')) ?> decoding="async" /><?php endif; ?>
    <?php if ($badge): ?><span class="badge"><?= e($badge) ?></span><?php endif; ?>
  </div>
  <div class="bd">
    <span class="eyebrow"><?= e(bnc_category($trip)) ?></span>
    <h3><a<?= bnc_attr('href', ($trip['url'] ?? null)) ?>><?= e(($trip['title'] ?? null)) ?></a></h3>
    <?php if ((count(($trip['destinations'] ?? null) ?? []) > 0)): ?><div class="meta"><?= e(bnc_places($trip)) ?></div><?php endif; ?>
    <div class="price-row">
      <?php if ($price): ?><span class="price"><small>From</small><?= e($price) ?></span><?php else: ?><span class="price"><small>Price</small>On request</span><?php endif; ?>
      <span class="go" aria-hidden="true">View trip →</span>
    </div>
  </div>
</article>
