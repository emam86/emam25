<?php declare(strict_types=1); ?>
<?= \Bnc\View::partial('partials/errors', ['errors' => $errors]) ?>
<form method="post" action="<?= e(url("/posts/{$post['id']}/delete")) ?>" class="stack card" data-confirm="حذف المقال نهائيًا؟">
<?= csrf_field() ?>
<p>حذف <?= e($post['title']) ?>؟</p>
<?php if ($post['status'] === 'published'): ?>
<label>تحويل الرابط القديم إلى <input name="redirect_to" required value="<?= e($target) ?>" dir="ltr"></label>
<?php endif; ?>
<div class="row"><button class="btn danger">حذف</button><a href="<?= e(url('/posts')) ?>">إلغاء</a></div>
</form>
