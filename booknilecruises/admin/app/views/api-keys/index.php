<?php declare(strict_types=1); ?>
<?= \Bnc\View::partial('partials/errors', ['errors' => $errors]) ?>
<?php if ($secret): ?><div class="flash warn"><p>انسخ المفتاح الآن. لن يظهر مرة أخرى.</p><code dir="ltr"><?= e($secret) ?></code></div><?php endif; ?>
<form method="post" action="<?= e(url('/api-keys')) ?>" class="card stack"><?= csrf_field() ?>
<label>اسم المفتاح<input name="name" maxlength="100" required value="<?= e(\Bnc\Request::isPost() ? \Bnc\Request::str('name') : '') ?>"></label>
<fieldset><legend>الصلاحيات</legend><?php foreach (\Bnc\Api\Keys::grantable(can(...)) as $scope): ?><label><input type="checkbox" name="scopes[]" value="<?= e($scope) ?>"<?= in_array($scope, \Bnc\Request::list('scopes'), true) ? ' checked' : '' ?>> <span dir="ltr"><?= e($scope) ?></span></label><?php endforeach; ?></fieldset><button class="btn">إنشاء مفتاح</button></form>
<div class="table-wrap"><table><thead><tr><th>الاسم</th><th>البادئة</th><th>الصلاحيات</th><th>آخر استخدام</th><th>الحالة</th><th>إجراءات</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?= e($row['name']) ?></td><td><code><?= e($row['key_prefix']) ?>…</code></td><td><?= e(implode(', ', json_decode($row['scopes'], true))) ?></td><td><?= e($row['last_used_at']) ?></td><td><?= $row['is_active'] ? 'نشط' : 'ملغى' ?></td><td>
<?php if ($row['is_active']): ?><form class="inline" method="post" action="<?= e(url('/api-keys/' . $row['id'] . '/revoke')) ?>" data-confirm="إلغاء المفتاح؟"><?= csrf_field() ?><button class="btn small">إلغاء</button></form><?php endif; ?>
<form class="inline" method="post" action="<?= e(url('/api-keys/' . $row['id'] . '/delete')) ?>" data-confirm="حذف المفتاح نهائيًا؟"><?= csrf_field() ?><button class="btn small danger">حذف</button></form></td></tr><?php endforeach; ?></tbody></table></div>
