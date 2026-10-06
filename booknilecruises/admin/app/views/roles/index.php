<p><a class="btn" href="<?= e(url('/roles/new')) ?>">+ إضافة دور</a></p>
<?php $labels = \Bnc\Permissions::all(); ?>
<div class="table-wrap">
<table>
  <thead><tr><th>الدور</th><th>الصلاحيات</th><th>المستخدمين</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($roles as $r): ?>
    <tr>
      <td><strong><?= e($r['name']) ?></strong></td>
      <td class="perms">
        <?php if (\Bnc\Roles::isOwner($r)): ?>
          <span class="tag ok">كل الصلاحيات</span>
        <?php else: foreach ($r['permissions'] as $p): ?>
          <span class="tag"><?= e($labels[$p] ?? $p) ?></span>
        <?php endforeach; endif; ?>
      </td>
      <td><?= (int) $r['user_count'] ?></td>
      <td class="actions">
        <?php if (!\Bnc\Roles::isOwner($r)): ?>
          <a href="<?= e(url("/roles/{$r['id']}/edit")) ?>">تعديل</a>
          <?php if ((int) $r['user_count'] === 0): ?>
            <form method="post" action="<?= e(url("/roles/{$r['id']}/delete")) ?>" data-confirm="حذف الدور <?= e($r['name']) ?>؟">
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
