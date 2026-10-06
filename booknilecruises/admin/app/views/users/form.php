<?= \Bnc\View::partial('partials/errors', ['errors' => $errors]) ?>
<form method="post" action="<?= e(url($u['id'] ? "/users/{$u['id']}/edit" : '/users/new')) ?>" class="stack card">
  <?= csrf_field() ?>
  <label>الاسم <input name="name" value="<?= e($u['name']) ?>" required maxlength="120"></label>
  <label>الإيميل <input type="email" name="email" value="<?= e($u['email']) ?>" required maxlength="190" dir="ltr" autocomplete="off"></label>
  <label><?= $u['id'] ? 'كلمة سر جديدة (اتركها فاضية لو مش هتتغير)' : 'كلمة السر' ?>
    <input type="password" name="password" <?= $u['id'] ? '' : 'required' ?> minlength="<?= \Bnc\Auth::MIN_PASSWORD ?>" dir="ltr" autocomplete="new-password"></label>
  <label>الدور
    <select name="role_id" required>
      <option value="">— اختر —</option>
      <?php foreach ($roles as $r): ?>
        <option value="<?= (int) $r['id'] ?>"<?= (int) $r['id'] === (int) $u['role_id'] ? ' selected' : '' ?>><?= e($r['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="check"><input type="checkbox" name="is_active" value="1"<?= $u['is_active'] ? ' checked' : '' ?>> الحساب نشط</label>
  <div class="row"><button class="btn">حفظ</button> <a href="<?= e(url('/users')) ?>">إلغاء</a></div>
</form>
