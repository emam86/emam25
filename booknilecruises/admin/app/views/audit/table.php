<div class="table-wrap">
<table>
  <thead><tr><th>الوقت</th><th>المستخدم</th><th>العملية</th><th>العنصر</th><th>التفاصيل</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $row): ?>
    <tr>
      <td class="nowrap"><?= e($row['created_at']) ?></td>
      <td dir="ltr"><?= e($row['actor']) ?></td>
      <td><code><?= e($row['action']) ?></code></td>
      <td><?= e($row['entity']) ?><?= $row['entity_id'] !== null ? ' #' . e($row['entity_id']) : '' ?></td>
      <td><?= e($row['summary']) ?><?php if ($row['details']): ?><details><summary>المزيد</summary><pre dir="ltr"><?= e($row['details']) ?></pre></details><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="5" class="muted">لا يوجد شيء.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
