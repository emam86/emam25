<?php declare(strict_types=1); ?>
<form class="enquiry"<?= bnc_attr('id', $id) ?><?= bnc_attr('data-trip', (($trip['title'] ?? null) ?? '')) ?><?= bnc_attr('data-slug', (($trip['slug'] ?? null) ?? '')) ?><?= bnc_attr('data-email', ($SITE['email'] ?? null)) ?><?= bnc_attr('data-wa', ($SITE['whatsapp'] ?? null)) ?>>
  <h2><?php if ($trip): ?><?= e('Book or ask about this trip') ?><?php else: ?><?= e('Send us an enquiry') ?><?php endif; ?></h2>
  <p class="hint">Fill in what you know. We reply on WhatsApp or by email.</p>
  <div class="row">
    <label<?= bnc_attr('for', ('' . $id . '-name')) ?>>Name<input<?= bnc_attr('id', ('' . $id . '-name')) ?> name="name" autocomplete="name" required /></label>
    <label<?= bnc_attr('for', ('' . $id . '-date')) ?>>Travel date<input<?= bnc_attr('id', ('' . $id . '-date')) ?> name="date" type="date" /></label>
  </div>
  <div class="row">
    <label<?= bnc_attr('for', ('' . $id . '-adults')) ?>>Adults<input<?= bnc_attr('id', ('' . $id . '-adults')) ?> name="adults" type="number" min="1" value="2" inputmode="numeric" /></label>
    <label<?= bnc_attr('for', ('' . $id . '-children')) ?>>Children<input<?= bnc_attr('id', ('' . $id . '-children')) ?> name="children" type="number" min="0" value="0" inputmode="numeric" /></label>
  </div>
  <label<?= bnc_attr('for', ('' . $id . '-contact')) ?>><span>Email or phone <span class="opt">(optional)</span></span><input<?= bnc_attr('id', ('' . $id . '-contact')) ?> name="contact" autocomplete="email" maxlength="190" /></label>
  <label class="hp" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off" /></label>
  <label<?= bnc_attr('for', ('' . $id . '-msg')) ?>>Message<textarea<?= bnc_attr('id', ('' . $id . '-msg')) ?> name="message" rows="3"<?= bnc_attr('placeholder', ($trip ? 'Cabin type, questions, pick-up hotel…' : 'Where would you like to go, and for how long?')) ?>></textarea></label>
  <div class="actions">
    <button class="btn btn-wa" type="submit" name="via" value="whatsapp">Send on WhatsApp</button>
    <button class="btn btn-navy" type="submit" name="via" value="email">Send by email</button>
  </div>
  <p class="alt">Or contact us directly: <a<?= bnc_attr('href', bnc_whatsapp($subject)) ?>>WhatsApp <?= e(($SITE['phoneDisplay'] ?? null)) ?></a> · <a<?= bnc_attr('href', bnc_mailto($subject)) ?>><?= e(($SITE['email'] ?? null)) ?></a></p>
</form>




