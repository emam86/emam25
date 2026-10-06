<?php declare(strict_types=1); ?>
<?= \Bnc\View::partial('partials/errors', ['errors' => $errors]) ?>
<form method="post" action="<?= e(url($d['id'] ? "/terms/{$d['id']}/edit" : '/terms/new')) ?>" class="stack card" data-term-form>
  <?= csrf_field() ?>
  <label>نوع التصنيف <select name="taxonomy"<?= $d['id'] ? ' disabled' : '' ?>><?php foreach (\Bnc\Terms::TAXONOMIES as $key => $label): ?><option value="<?= e($key) ?>"<?= $d['taxonomy'] === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
  <label>الاسم <input name="name" required maxlength="190" value="<?= e($d['name']) ?>" data-slug-source></label>
  <label>الرابط المختصر <input name="slug" required maxlength="190" dir="ltr" value="<?= e($d['slug']) ?>"<?= $protected ? ' readonly' : ' data-slug-target' ?>></label>
  <?php if ($protected): ?><p class="muted">هذا الرابط لصفحة أساسية بالموقع؛ لا يمكن تغييره أو حذف التصنيف.</p><?php endif; ?>
  <label>التصنيف الأب (الوجهات بدون أب)<select name="parent_id"><option value="">بدون أب</option><?php foreach ($parents as $parent): if ((int) $parent['id'] === (int) $d['id']) continue; ?><option value="<?= (int) $parent['id'] ?>" data-taxonomy="<?= e($parent['taxonomy']) ?>"<?= (int) $d['parent_id'] === (int) $parent['id'] ? ' selected' : '' ?>><?= e(\Bnc\Terms::TAXONOMIES[$parent['taxonomy']] . ' — ' . $parent['name']) ?></option><?php endforeach; ?></select></label>
  <label>الوصف <textarea name="description" rows="5"><?= e($d['description']) ?></textarea></label>
  <label>عنوان SEO <input name="seo_title" maxlength="255" value="<?= e($d['seo_title']) ?>"></label>
  <label>وصف SEO <textarea name="seo_description" maxlength="500" rows="3"><?= e($d['seo_description']) ?></textarea></label>
  <div class="row"><button class="btn">حفظ</button><a href="<?= e(url('/terms')) ?>">إلغاء</a></div>
</form>
