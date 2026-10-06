<?php declare(strict_types=1); ?>
<div class="listing" data-listing>
  <?php if ($showFilters): ?><div class="tools">
      <div class="chips" role="group" aria-label="Filter by destination">
        <button type="button" class="chip" aria-pressed="true" data-dest="">All <span><?= e(count($trips ?? [])) ?></span></button>
        <?php foreach ($destinations as $d):  ?><button type="button" class="chip" aria-pressed="false"<?= bnc_attr('data-dest', ($d['slug'] ?? null)) ?>><?= e(($d['name'] ?? null)) ?></button><?php endforeach; ?>
      </div>
      <label class="sort" for="sort-select">Sort
        <select id="sort-select" data-sort>
          <option value="">Recommended</option>
          <option value="price-asc">Price: low to high</option>
          <option value="price-desc">Price: high to low</option>
          <option value="days-asc">Shortest first</option>
        </select>
      </label>
    </div><?php endif; ?>
  <?php if ((count($trips ?? []) > 0)): ?><div class="grid3" data-grid>
      <?php foreach ($trips as $i => $t):  ?><div class="cell"<?= bnc_attr('data-price', (($t['price'] ?? null) ?? '')) ?><?= bnc_attr('data-days', ((($t['duration'] ?? null)['days'] ?? null) ?? '')) ?><?= bnc_attr('data-order', $i) ?>><?= \Bnc\Site\View::component('TripCard', ['trip' => $t, 'eager' => ($i < 3)]) ?></div><?php endforeach; ?>
    </div><?php else: ?><p class="empty"><?= e($emptyText) ?></p><?php endif; ?>
  <p class="count" aria-live="polite" data-count></p>
</div>




