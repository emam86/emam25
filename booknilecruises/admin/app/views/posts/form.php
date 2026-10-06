<?php
declare(strict_types=1);
use Bnc\{Config, View};
use Bnc\Media\Files;
?>
<?= View::partial('partials/errors', ['errors' => $errors]) ?>
<form method="post" action="<?= e(url($d['id'] ? "/posts/{$d['id']}/edit" : '/posts/new')) ?>" class="stack card" data-post-form data-site-url="<?= e(Config::get('site_url')) ?>" data-images-url="<?= e(Config::get('images_url', '/images')) ?>" data-picker-url="<?= e(url('/media/picker.json')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="updated_at" value="<?= e($d['updated_at']) ?>">
  <label>العنوان <input name="title" required maxlength="255" data-slug-source value="<?= e($d['title']) ?>"></label>
  <label>الرابط المختصر <input name="slug" required maxlength="190" data-slug-target value="<?= e($d['slug']) ?>"></label>
  <label>الحالة <select name="status"><?php foreach (['draft' => 'مسودة', 'published' => 'منشورة'] as $value => $label): ?><option value="<?= e($value) ?>"<?= $d['status'] === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
  <label>تاريخ ووقت النشر <input type="datetime-local" name="published_at" step="1" required value="<?= e($d['published_at']) ?>"></label>
  <label>المقتطف (نص فقط) <textarea name="excerpt" maxlength="500"><?= e($d['excerpt']) ?></textarea></label>
  <fieldset><legend>صورة الغلاف</legend><div data-cover>
    <label>رقم الصورة <input name="image_id" value="<?= e($d['image_id']) ?>"></label>
    <div data-cover-preview><?php if ($photo): ?><img class="trip-thumb" src="<?= e(Files::url(Files::thumb($photo))) ?>" alt="<?= e($photo['alt']) ?>"><?php endif; ?></div>
    <button type="button" data-pick="cover" class="btn small js-control" hidden>اختيار الغلاف</button>
    <button type="button" data-clear-cover class="js-control" hidden>إزالة الغلاف</button>
  </div></fieldset>
  <label>المحتوى <textarea name="content_html" data-rich rows="12"><?= e($d['content_html']) ?></textarea></label>
  <button type="button" data-pick="content" class="btn small js-control" hidden>إدراج صورة في المحتوى</button>
  <?= View::partial('partials/seo', ['d' => $d]) ?>
  <div class="row"><button class="btn">حفظ</button><a href="<?= e(url('/posts')) ?>">العودة للمقالات</a></div>
</form>
