<?php $title = 'تسجيل الدخول'; ?>
<h1>تسجيل الدخول</h1>
<?php if ($error): ?><p class="flash error" role="alert"><?= e($error) ?></p><?php endif; ?>
<form method="post" action="<?= e(url('/login')) ?>" class="stack">
  <?= csrf_field() ?>
  <input type="hidden" name="next" value="<?= e($next) ?>">
  <label>الإيميل <input type="email" name="email" value="<?= e($email) ?>" required autocomplete="username" dir="ltr"></label>
  <label>كلمة السر <input type="password" name="password" required autocomplete="current-password" dir="ltr"></label>
  <button class="btn">دخول</button>
</form>
