<?php $title = 'تثبيت اللوحة'; ?>
<h1>تثبيت لوحة التحكم</h1>
<p>الخطوة دي بتعمل جداول قاعدة البيانات وحساب المالك. بتشتغل مرة واحدة بس.</p>
<?php if (!$tokenSet): ?>
  <p class="flash error">ضع <code>install_token</code> طويل (24 حرف على الأقل) في <code>bnc-config.php</code> قبل التثبيت.</p>
<?php endif; ?>
<?= \Bnc\View::partial('partials/errors', ['errors' => $errors]) ?>
<form method="post" action="<?= e(url('/install')) ?>" class="stack">
  <?= csrf_field() ?>
  <label>رمز التثبيت (install_token) <input type="password" name="token" required dir="ltr" autocomplete="off"></label>
  <label>اسمك <input name="name" value="<?= e($name) ?>" required maxlength="120"></label>
  <label>الإيميل <input type="email" name="email" value="<?= e($email) ?>" required dir="ltr" autocomplete="username"></label>
  <label>كلمة السر (<?= \Bnc\Auth::MIN_PASSWORD ?> حروف على الأقل) <input type="password" name="password" required minlength="<?= \Bnc\Auth::MIN_PASSWORD ?>" dir="ltr" autocomplete="new-password"></label>
  <button class="btn">تثبيت</button>
</form>
