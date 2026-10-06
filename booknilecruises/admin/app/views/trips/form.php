<?php
declare(strict_types=1);
use Bnc\{Terms, View};
use Bnc\Media\Files;
?>
<?= View::partial('partials/errors', ['errors' => $errors]) ?>
<form method="post" action="<?= e(url($d['id'] ? "/trips/{$d['id']}/edit" : '/trips/new')) ?>" class="stack card trip-form" data-trip-form data-site-url="<?= e(\Bnc\Config::get('site_url')) ?>" data-images-url="<?= e(\Bnc\Config::get('images_url', '/images')) ?>" data-picker-url="<?= e(url('/media/picker.json')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="updated_at" value="<?= e($d['updated_at']) ?>">
  <fieldset><legend>البيانات الأساسية</legend>
    <label>العنوان <input name="title" required maxlength="255" value="<?= e($d['title']) ?>" data-slug-source></label>
    <label>الرابط المختصر <input name="slug" required maxlength="190" value="<?= e($d['slug']) ?>" dir="ltr" data-slug-target></label>
    <label>الحالة <select name="status"><?php foreach (['draft' => 'مسودة', 'published' => 'منشورة'] as $value => $label): ?><option value="<?= e($value) ?>"<?= $d['status'] === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <label class="check"><input type="checkbox" name="featured" value="1"<?= $d['featured'] ? ' checked' : '' ?>> رحلة مميزة</label>
    <label>كود الرحلة <input name="code" maxlength="40" value="<?= e($d['code']) ?>"></label>
    <label>ملخص <textarea name="excerpt" maxlength="500" rows="3"><?= e($d['excerpt']) ?></textarea></label>
  </fieldset>
  <fieldset><legend>السعر والمدة</legend>
    <?php foreach (['price' => 'السعر (فارغ = السعر عند الطلب)', 'sale_price' => 'سعر العرض (اختياري)'] as $field => $label): ?><label><?= e($label) ?><input type="number" name="<?= e($field) ?>" min="0" max="99999999.99" step="0.01" value="<?= e($d[$field]) ?>"></label><?php endforeach; ?>
    <label>العملة <select name="currency"><?php foreach (['USD', 'EUR', 'GBP', 'EGP'] as $currency): ?><option<?= $d['currency'] === $currency ? ' selected' : '' ?>><?= e($currency) ?></option><?php endforeach; ?></select></label>
    <?php foreach (['duration_days' => ['الأيام', 0, 365], 'duration_nights' => ['الليالي', 0, 365], 'min_pax' => ['أقل عدد مسافرين', 1, 999], 'max_pax' => ['أكبر عدد مسافرين', 1, 999]] as $field => [$label, $min, $max]): ?><label><?= e($label) ?><input name="<?= e($field) ?>" type="number" min="<?= $min ?>" max="<?= $max ?>" value="<?= e($d[$field]) ?>"></label><?php endforeach; ?>
  </fieldset>
  <fieldset><legend>التصنيفات</legend>
    <p class="muted">ترتيب الاختيار محفوظ؛ أول نشاط هو التصنيف الرئيسي. يمكنك تغيير الترتيب بالأسهم.</p>
    <?php foreach (Terms::TAXONOMIES as $taxonomy => $label): ?>
      <h3><?= e($label) ?></h3>
      <?php foreach ($terms as $parent): if ($parent['taxonomy'] !== $taxonomy || $parent['parent_id'] !== null) continue; ?>
        <label class="check"><input type="checkbox" name="term_ids[]" data-term-checkbox value="<?= (int) $parent['id'] ?>"<?= in_array($parent['id'], $d['term_ids']) ? ' checked' : '' ?>><?= e($parent['name']) ?></label>
        <?php foreach ($terms as $child): if ((int) $child['parent_id'] !== (int) $parent['id']) continue; ?>
          <label class="check term-child"><input type="checkbox" name="term_ids[]" data-term-checkbox value="<?= (int) $child['id'] ?>"<?= in_array($child['id'], $d['term_ids']) ? ' checked' : '' ?>><?= e($child['name']) ?></label>
        <?php endforeach; ?>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <div data-term-order class="stack">
      <?php foreach ($d['term_ids'] as $id): $term = null; foreach ($terms as $t) if ((string) $t['id'] === (string) $id) $term = $t; ?>
        <div class="row" data-ordered-item><input type="hidden" name="term_order[]" value="<?= e($id) ?>"><span><?= e($term['name'] ?? "#$id") ?></span><button type="button" data-move="up" class="js-control" hidden>↑</button><button type="button" data-move="down" class="js-control" hidden>↓</button></div>
      <?php endforeach; ?>
    </div>
  </fieldset>
  <fieldset><legend>الصور</legend>
    <div class="stack" data-cover>
      <label>رقم صورة الغلاف <input name="image_id" value="<?= e($d['image_id']) ?>" inputmode="numeric"></label>
      <div data-cover-preview><?php if (isset($photos[(int) $d['image_id']])): $photo = $photos[(int) $d['image_id']]; ?><img class="trip-thumb" src="<?= e(Files::url(Files::thumb($photo))) ?>" alt="<?= e($photo['alt']) ?>"><?php endif; ?></div>
      <div><button type="button" data-pick="cover" class="btn small js-control" hidden>اختيار الغلاف</button> <button type="button" data-clear-cover class="js-control" hidden>إزالة الغلاف</button></div>
    </div>
    <h3>معرض الصور</h3>
    <div data-gallery class="stack">
      <?php foreach ($d['gallery'] as $id): ?>
        <div class="row" data-ordered-item>
          <?php if (isset($photos[(int) $id])): $photo = $photos[(int) $id]; ?><img class="trip-thumb" src="<?= e(Files::url(Files::thumb($photo))) ?>" alt="<?= e($photo['alt']) ?>"><?php endif; ?>
          <label>رقم الصورة <input name="gallery[]" value="<?= e($id) ?>" inputmode="numeric"></label>
          <button type="button" data-move="up" class="js-control" hidden>↑</button><button type="button" data-move="down" class="js-control" hidden>↓</button><button type="button" data-remove class="js-control" hidden>إزالة</button>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" data-pick="gallery" class="btn small js-control" hidden>إضافة صور للمعرض</button>
    <noscript><label>إضافة رقم صورة <input name="gallery[]" inputmode="numeric"></label></noscript>
  </fieldset>
  <fieldset><legend>نظرة عامة</legend><label>المحتوى <textarea name="overview_html" data-rich rows="8"><?= e($d['overview_html']) ?></textarea></label></fieldset>
  <fieldset><legend>مميزات الرحلة والمشمول وغير المشمول</legend>
    <?php foreach (['highlights' => 'المميزات', 'includes' => 'المشمول', 'excludes' => 'غير المشمول'] as $field => $label): ?><label><?= e($label) ?> (بند في كل سطر)<textarea name="<?= e($field) ?>" rows="5"><?= e(implode("\n", $d[$field])) ?></textarea></label><?php endforeach; ?>
  </fieldset>
  <?php foreach (['itinerary' => ['برنامج الرحلة', 'title', 'html', 60], 'faqs' => ['الأسئلة الشائعة', 'q', 'a', 40]] as $field => [$label, $text, $html, $max]): ?>
    <fieldset data-repeat="<?= e($field) ?>" data-limit="<?= $max ?>"><legend><?= e($label) ?></legend>
      <div data-rows class="stack">
        <?php foreach ($d[$field] as $i => $item): ?><?= View::partial('trips/repeat-row', compact('field', 'text', 'html', 'i', 'item')) ?><?php endforeach; ?>
      </div>
      <template><?= View::partial('trips/repeat-row', ['field' => $field, 'text' => $text, 'html' => $html, 'i' => '__INDEX__', 'item' => [$text => '', $html => '']]) ?></template>
      <div class="row"><button type="button" data-add-row class="btn small js-control" hidden>إضافة صف</button><?php if ($field === 'itinerary'): ?><button type="button" data-add-row="heading" class="btn small js-control" hidden>إضافة عنوان مجموعة</button><?php endif; ?></div>
      <noscript><p>لإضافة صف بدون JavaScript استخدم الصف الفارغ. اترك HTML فارغًا لعنوان مجموعة.</p><?= View::partial('trips/repeat-row', ['field' => $field, 'text' => $text, 'html' => $html, 'i' => count($d[$field]), 'item' => [$text => '', $html => '']]) ?></noscript>
    </fieldset>
  <?php endforeach; ?>
  <?= View::partial('partials/seo', ['d' => $d]) ?>
  <div class="row"><button class="btn">حفظ</button><a href="<?= e(url('/trips')) ?>">العودة للرحلات</a></div>
</form>
