<?php declare(strict_types=1); ?>
<?php if (can('trips.create')): ?><p><a class="btn" href="<?= e(url('/trips/new')) ?>">+ إضافة رحلة</a></p><?php endif; ?>
<form method="get" action="<?= e(url('/trips')) ?>" class="filters">
  <label>بحث <input name="q" value="<?= e($q) ?>"></label>
  <label>الحالة <select name="status"><option value="">الكل</option><?php foreach (['draft' => 'مسودة', 'published' => 'منشورة'] as $key => $label): ?><option value="<?= e($key) ?>"<?= $status === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
  <label>التصنيف <select name="category"><option value="">الكل</option><?php foreach ($terms as $term): ?><option value="<?= (int) $term['id'] ?>"<?= $category === (int) $term['id'] ? ' selected' : '' ?>><?= e($term['name']) ?></option><?php endforeach; ?></select></label>
  <button class="btn small">بحث</button><span class="muted"><?= (int) $total ?> رحلة</span>
</form>
<div class="table-wrap"><table>
  <thead><tr><th>الغلاف</th><th>العنوان</th><th>السعر</th><th>المدة</th><th>الحالة</th><th>مميزة</th><th>آخر تحديث</th><th></th></tr></thead>
  <tbody><?php foreach ($rows as $trip): ?>
    <tr><td><?php if ($trip['path']): ?><img class="trip-thumb" src="<?= e(\Bnc\Media\Files::url(\Bnc\Media\Files::thumb($trip))) ?>" alt="<?= e($trip['alt']) ?>" loading="lazy"><?php endif; ?></td>
      <td><?= e($trip['title']) ?></td><td><?= e($trip['price']) ?> <?= e($trip['currency']) ?><?php if ($trip['sale_price'] !== null): ?><br>عرض: <?= e($trip['sale_price']) ?><?php endif; ?></td>
      <td><?= (int) $trip['duration_days'] ?> أيام / <?= (int) $trip['duration_nights'] ?> ليالٍ</td><td><span class="tag <?= $trip['status'] === 'published' ? 'ok' : 'off' ?>"><?= $trip['status'] === 'published' ? 'منشورة' : 'مسودة' ?></span></td><td><?= $trip['featured'] ? '★' : '—' ?></td><td><?= e($trip['updated_at']) ?></td>
      <td class="actions">
        <?php if (can('trips.edit')): ?><a href="<?= e(url("/trips/{$trip['id']}/edit")) ?>">تعديل</a><?php endif; ?>
        <?php if (can('trips.create')): ?><form method="post" action="<?= e(url("/trips/{$trip['id']}/duplicate")) ?>"><?= csrf_field() ?><button class="link">نسخ</button></form><?php endif; ?>
        <?php if (can('trips.delete')): ?><a class="danger" href="<?= e(url("/trips/{$trip['id']}/delete")) ?>">حذف</a><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?></tbody>
</table></div>
<?php if ($pages > 1): ?><nav class="pager"><?php for ($i = 1; $i <= $pages; $i++): ?><?php if ($i === $page): ?><span aria-current="page"><?= $i ?></span><?php else: ?><a href="<?= e(url('/trips', ['q' => $q, 'status' => $status, 'category' => $category, 'page' => $i])) ?>"><?= $i ?></a><?php endif; ?><?php endfor; ?></nav><?php endif; ?>
