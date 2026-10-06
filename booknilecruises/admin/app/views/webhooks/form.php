<?php declare(strict_types=1); ?>
<?= \Bnc\View::partial('partials/errors', ['errors' => $errors]) ?>
<?php if ($secret): ?><div class="flash warn"><p>سر توقيع Webhook — انسخه الآن؛ يظهر مرة واحدة بعد الإنشاء أو التجديد.</p><code dir="ltr"><?= e($secret) ?></code></div><?php endif; ?>
<form method="post" action="<?= e(url($d['id'] ? '/webhooks/' . $d['id'] . '/edit' : '/webhooks/new')) ?>" class="card stack"><?= csrf_field() ?>
<label>الاسم<input name="name" value="<?= e($d['name']) ?>" maxlength="100" required></label>
<label>رابط HTTPS<input type="url" name="url" value="<?= e($d['url']) ?>" maxlength="500" required dir="ltr"></label>
<fieldset><legend>الأحداث</legend><?php foreach (\Bnc\Webhooks\Delivery::EVENTS as $event): ?><label><input type="checkbox" name="events[]" value="<?= e($event) ?>"<?= in_array($event, $d['events'], true) ? ' checked' : '' ?>> <span dir="ltr"><?= e($event) ?></span></label><?php endforeach; ?></fieldset>
<label><input type="checkbox" name="is_active" value="1"<?= $d['is_active'] ? ' checked' : '' ?>> نشط</label><button class="btn">حفظ</button></form>
<?php if ($d['id']): ?><p>آخر إرسال: <?= e($d['last_called_at'] ?? '') ?> · النتيجة: <?= e($d['last_status'] ?? '') ?></p>
<div class="row"><form method="post" action="<?= e(url('/webhooks/' . $d['id'] . '/regenerate')) ?>" data-confirm="تجديد السر؟ يجب تحديثه في النظام المستقبِل."><?= csrf_field() ?><button class="btn">تجديد السر</button></form>
<form method="post" action="<?= e(url('/webhooks/' . $d['id'] . '/test')) ?>"><?= csrf_field() ?><button class="btn">إرسال اختبار</button></form>
<form method="post" action="<?= e(url('/webhooks/' . $d['id'] . '/delete')) ?>" data-confirm="حذف Webhook؟"><?= csrf_field() ?><button class="btn danger">حذف</button></form></div><?php endif; ?>
