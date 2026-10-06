<?php if (!empty($errors)): ?>
  <div class="flash error" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
