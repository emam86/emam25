<?php declare(strict_types=1); ?>
<div class="stack repeat-row" data-ordered-item>
  <label><?= $field === 'itinerary' ? 'العنوان' : 'السؤال' ?><input name="<?= e($field) ?>[<?= e($i) ?>][<?= e($text) ?>]" maxlength="500" value="<?= e($item[$text]) ?>"></label>
  <label><?= $field === 'itinerary' ? 'المحتوى (فارغ لعنوان المجموعة)' : 'الإجابة' ?><textarea name="<?= e($field) ?>[<?= e($i) ?>][<?= e($html) ?>]" data-rich rows="4"><?= e($item[$html]) ?></textarea></label>
  <div class="row js-control" hidden><button type="button" data-move="up">↑</button><button type="button" data-move="down">↓</button><button type="button" data-remove>إزالة الصف</button></div>
</div>
