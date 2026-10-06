<?php declare(strict_types=1); ?>
<?= \Bnc\View::partial('partials/errors', ['errors' => $errors]) ?>
<p dir="ltr"><?= e($d['path']) ?></p>
<?php if ($editor): ?><p><a href="<?= e(url($editor['path'])) ?>"><?= e($editor['label']) ?></a></p>
<?php else: ?>
<form method="post" action="<?= e(url('/seo/edit')) ?>" class="stack card" data-seo-form data-site-url="<?= e(\Bnc\Config::get('site_url')) ?>">
<?= csrf_field() ?><input type="hidden" name="path" value="<?= e($d['path']) ?>">
<?= \Bnc\View::partial('partials/seo', ['d' => $d]) ?>
<p>ترك العنوان والوصف فارغين وإلغاء عدم الفهرسة يمسح التخصيص.</p>
<div class="row"><button class="btn">حفظ</button><a href="<?= e(url('/seo')) ?>">العودة</a></div>
</form>
<?php endif; ?>
