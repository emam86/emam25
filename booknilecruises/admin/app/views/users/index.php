<p><a class="btn" href="<?= e(url('/users/new')) ?>">+ إضافة مستخدم</a></p>
<div class="table-wrap">
<table>
  <thead><tr><th>الاسم</th><th>الإيميل</th><th>الدور</th><th>الحالة</th><th>آخر دخول</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): ?>
    <tr>
      <td><?= e($u['name']) ?></td>
      <td dir="ltr"><?= e($u['email']) ?></td>
      <td><?= e($u['role_name']) ?></td>
      <td><?= $u['is_active'] ? '<span class="tag ok">نشط</span>' : '<span class="tag off">موقوف</span>' ?></td>
      <td><?= e($u['last_login_at'] ?? '—') ?></td>
      <td class="actions">
        <?php if ($u['manageable']): ?>
          <a href="<?= e(url("/users/{$u['id']}/edit")) ?>">تعديل</a>
          <?php if ((int) $u['id'] !== (int) \Bnc\Auth::user()['id']): ?>
            <form method="post" action="<?= e(url("/users/{$u['id']}/delete")) ?>" data-confirm="حذف المستخدم <?= e($u['email']) ?>؟">
              <?= csrf_field() ?><button class="link danger">حذف</button>
            </form>
          <?php endif; ?>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
