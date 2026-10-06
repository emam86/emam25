<?php declare(strict_types=1); ?>
<div class="terms">
  <?php foreach ($terms as $t): $img = (bnc_find(($t['trips'] ?? null), fn($x) => ($x['image'] ?? null))['image'] ?? null);
$src = ((($img['card'] ?? null)['src'] ?? null) ?? ($img['src'] ?? null)); ?><a class="term"<?= bnc_attr('href', (($t['href'] ?? null) ?? ($t['url'] ?? null))) ?>>
        <?php if ($src): ?><img<?= bnc_attr('src', bnc_asset($src)) ?><?= bnc_attr('srcset', bnc_srcset($img)) ?> sizes="(max-width: 535px) calc(100vw - 40px), (max-width: 791px) calc((100vw - 56px) / 2), (max-width: 1047px) calc((100vw - 72px) / 3), (max-width: 1180px) calc((100vw - 88px) / 4), 273px" alt="" loading="lazy" decoding="async" /><?php endif; ?>
        <span class="label"><strong><?= e(($t['name'] ?? null)) ?></strong><small><?= e(count(($t['trips'] ?? null) ?? [])) ?> <?php if ((count(($t['trips'] ?? null) ?? []) === 1)): ?><?= e('trip') ?><?php else: ?><?= e('trips') ?><?php endif; ?></small></span>
      </a><?php endforeach; ?>
</div>

