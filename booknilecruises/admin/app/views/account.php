<?= \Bnc\View::partial('partials/errors', ['errors' => $errors]) ?>
<form method="post" action="<?= e(url('/account')) ?>" class="stack card">
  <?= csrf_field() ?>
  <label>الاسم <input name="name" value="<?= e($me['name']) ?>" required maxlength="120"></label>
  <p class="muted">الإيميل: <span dir="ltr"><?= e($me['email']) ?></span> · الدور: <?= e($me['role']['name'] ?? '') ?></p>
  <fieldset>
    <legend>تغيير كلمة السر (اتركها فاضية لو مش عايز تغيّرها)</legend>
    <label>كلمة السر الحالية <input type="password" name="current_password" dir="ltr" autocomplete="current-password"></label>
    <label>كلمة السر الجديدة <input type="password" name="new_password" dir="ltr" minlength="<?= \Bnc\Auth::MIN_PASSWORD ?>" autocomplete="new-password"></label>
  </fieldset>
  <button class="btn">حفظ</button>
</form>
