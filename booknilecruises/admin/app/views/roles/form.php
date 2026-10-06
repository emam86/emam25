<?= \Bnc\View::partial('partials/errors', ['errors' => $errors]) ?>
<form method="post" action="<?= e(url($role['id'] ? "/roles/{$role['id']}/edit" : '/roles/new')) ?>" class="stack card">
  <?= csrf_field() ?>
  <label>اسم الدور <input name="name" value="<?= e($role['name']) ?>" required maxlength="100"></label>
  <?php foreach ($groups as $group => $perms): ?>
    <fieldset class="perm-group">
      <legend><?= e($group) ?></legend>
      <?php foreach ($perms as $key => $label):
        $held = in_array($key, $mine, true); ?>
        <label class="check<?= $held ? '' : ' disabled' ?>">
          <input type="checkbox" name="permissions[]" value="<?= e($key) ?>"
            <?= in_array($key, $role['permissions'], true) ? 'checked' : '' ?> <?= $held ? '' : 'disabled' ?>>
          <?= e($label) ?> <code dir="ltr"><?= e($key) ?></code>
        </label>
      <?php endforeach; ?>
    </fieldset>
  <?php endforeach; ?>
  <p class="muted">الصلاحيات الرمادية مش عندك، فمش هتقدر تديها أو تشيلها.</p>
  <div class="row"><button class="btn">حفظ</button> <a href="<?= e(url('/roles')) ?>">إلغاء</a></div>
</form>
