<?php declare(strict_types=1); ?>
  <fieldset><legend>SEO</legend>
    <label>عنوان SEO <input name="seo_title" maxlength="255" data-counter="60" value="<?= e($d['seo_title']) ?>"><span data-count aria-live="polite"></span></label>
    <label>وصف SEO <textarea name="seo_description" maxlength="500" data-counter="160" rows="3"><?= e($d['seo_description']) ?></textarea><span data-count aria-live="polite"></span></label>
    <label class="check"><input type="checkbox" name="noindex" value="1"<?= $d['noindex'] ? ' checked' : '' ?>> عدم الفهرسة</label>
    <div class="seo-preview" dir="ltr" aria-label="معاينة بحث Google"><strong data-seo-title></strong><div data-seo-url></div><p data-seo-description></p></div>
  </fieldset>
