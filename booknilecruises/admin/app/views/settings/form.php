<?php declare(strict_types=1); ?>
<?= \Bnc\View::partial('partials/errors', ['errors' => $errors]) ?>
<p class="flash">تظهر التغييرات على الموقع بعد النشر القادم.</p>
<form method="post" action="<?= e(url('/settings')) ?>" class="stack card"><?= csrf_field() ?>
<fieldset><legend>التواصل</legend>
<?php foreach (['email' => ['البريد الإلكتروني', 254], 'whatsapp' => ['WhatsApp (أرقام فقط، بالصيغة الدولية بدون +)', 20], 'phone_display' => ['الهاتف المعروض', 30], 'phone_alt' => ['الهاتف الثاني', 30], 'address' => ['العنوان', 200]] as $key => [$label, $max]): ?><label><?= e($label) ?><input name="<?= e($key) ?>" maxlength="<?= $max ?>" value="<?= e($d[$key]) ?>"></label><?php endforeach; ?>
</fieldset>
<fieldset><legend>محركات البحث</legend>
<p>الصق الكود فقط أو وسم meta الكامل. يظهر الكود في &lt;head&gt; لكل صفحة.</p>
<label>Google site verification <input name="google_site_verification" value="<?= e($d['google_site_verification']) ?>"></label>
<label>Bing verification <input name="bing_site_verification" value="<?= e($d['bing_site_verification']) ?>"></label>
<div class="row"><a href="https://search.google.com/search-console" target="_blank" rel="noopener">Google Search Console</a><a href="https://www.bing.com/webmasters" target="_blank" rel="noopener">Bing Webmaster Tools</a></div>
</fieldset>
<fieldset><legend>Analytics</legend><label>GA4 measurement ID <input name="ga4_id" maxlength="22" placeholder="G-XXXXXXX" value="<?= e($d['ga4_id']) ?>"></label><p>اتركه فارغًا لتعطيل Google Analytics 4.</p></fieldset>
<button class="btn">حفظ الإعدادات</button>
</form>
