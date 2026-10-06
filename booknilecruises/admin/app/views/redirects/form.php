<?php declare(strict_types=1); ?>
<?= \Bnc\View::partial('partials/errors', ['errors' => $errors]) ?>
<?php if ($live): ?><p class="flash">هذه الصفحة موجودة؛ التحويل سيخفيها.</p><?php endif; ?>
<form method="post" action="<?= e(url($d['id'] ? "/redirects/{$d['id']}/edit" : '/redirects/new')) ?>" class="stack card"><?= csrf_field() ?>
<label>من <input name="from_path" maxlength="255" required dir="ltr" value="<?= e($d['from_path']) ?>"></label>
<label>إلى (مسار الموقع أو https) <input name="to_path" maxlength="255" required dir="ltr" value="<?= e($d['to_path']) ?>"></label>
<p>إذا كانت الوجهة محوّلة بالفعل سنحفظ الوجهة النهائية.</p>
<div class="row"><button class="btn">حفظ</button><a href="<?= e(url('/redirects')) ?>">العودة</a></div>
</form>
