<?php declare(strict_types=1); ?><!doctype html>

<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title><?= e($title) ?></title>
  <?php if ($description): ?><meta name="description"<?= bnc_attr('content', $description) ?> /><?php endif; ?>
  <meta name="robots"<?= bnc_attr('content', $robots) ?> />
  <link rel="canonical"<?= bnc_attr('href', $canonicalUrl) ?> />
  <meta property="og:locale" content="en_US" />
  <meta property="og:site_name" content="Book Nile cruises" />
  <meta property="og:type" content="website" />
  <meta property="og:title"<?= bnc_attr('content', $title) ?> />
  <?php if ($description): ?><meta property="og:description"<?= bnc_attr('content', $description) ?> /><?php endif; ?>
  <meta property="og:url"<?= bnc_attr('content', $canonicalUrl) ?> />
  <meta property="og:image"<?= bnc_attr('content', $ogUrl) ?> />
  <meta name="twitter:card" content="summary_large_image" />
  <?php if (($VERIFY['google'] ?? null)): ?><meta name="google-site-verification"<?= bnc_attr('content', ($VERIFY['google'] ?? null)) ?> /><?php endif; ?>
  <?php if (($VERIFY['bing'] ?? null)): ?><meta name="msvalidate.01"<?= bnc_attr('content', ($VERIFY['bing'] ?? null)) ?> /><?php endif; ?>
  <?php if (($VERIFY['ga4'] ?? null)): ?><script async<?= bnc_attr('src', ('https://www.googletagmanager.com/gtag/js?id=' . ($VERIFY['ga4'] ?? null) . '')) ?>></script><?php endif; ?>
  <?php if ($gtag): ?><script><?= $gtag ?></script><?php endif; ?>
  <link rel="icon"<?= bnc_attr('href', bnc_asset('/images/2026/07/cropped-ChatGPT-Image-Jul-16-2026-11_04_46-PM-1-32x32.png')) ?> sizes="32x32" />
  <link rel="icon"<?= bnc_attr('href', bnc_asset('/images/2026/07/cropped-ChatGPT-Image-Jul-16-2026-11_04_46-PM-1-192x192.png')) ?> sizes="192x192" />
  <link rel="apple-touch-icon"<?= bnc_attr('href', bnc_asset('/images/2026/07/cropped-ChatGPT-Image-Jul-16-2026-11_04_46-PM-1-180x180.png')) ?> />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Marcellus&family=Mulish:wght@400;600;700&display=swap" />
  <?php foreach ($ld as $obj):  ?><script type="application/ld+json"><?= bnc_json($obj) ?></script><?php endforeach; ?>
  <?= \Bnc\Site\View::resources() ?>
</head>
<body<?= bnc_attr('data-page', \Bnc\Site\View::$pageTemplate) ?>>
  <a class="skip" href="#main">Skip to content</a>
  <div class="topbar">
    <div class="wrap">
      <span><a<?= bnc_attr('href', bnc_whatsapp()) ?>><?= e(($SITE['phoneDisplay'] ?? null)) ?></a> · <a<?= bnc_attr('href', ('mailto:' . ($SITE['email'] ?? null) . '')) ?>><?= e(($SITE['email'] ?? null)) ?></a></span>
      <span class="addr"><?= e(($SITE['address'] ?? null)) ?></span>
    </div>
  </div>
  <header class="site">
    <div class="wrap">
      <a class="logo" href="/" aria-label="Book Nile Cruises home">
        <img src="/img/logo.webp" alt="Book Nile Cruises" width="640" height="189" />
      </a>
      <nav class="main" aria-label="Main">
        <ul>
          <?php foreach ($NAV as $item):  ?><li<?= bnc_attr('class', (($item['children'] ?? null) ? 'has-sub' : '')) ?>>
              <a<?= bnc_attr('href', ($item['href'] ?? null)) ?><?= bnc_attr('aria-current', ($isActive(($item['href'] ?? null), ($item['children'] ?? null)) ? 'page' : null)) ?>><?= e(($item['label'] ?? null)) ?></a>
              <?php if (($item['children'] ?? null)): ?><ul class="sub">
                  <?php foreach (($item['children'] ?? null) as $c):  ?><li><a<?= bnc_attr('href', ($c['href'] ?? null)) ?>><?= e(($c['label'] ?? null)) ?></a></li><?php endforeach; ?>
                </ul><?php endif; ?>
            </li><?php endforeach; ?>
        </ul>
      </nav>
      <details class="mnav">
        <summary aria-label="Open menu">Menu</summary>
        <nav aria-label="Mobile">
          <ul>
            <?php foreach ($NAV as $item):  ?><li>
                <a<?= bnc_attr('href', ($item['href'] ?? null)) ?>><?= e(($item['label'] ?? null)) ?></a>
                <?php if (($item['children'] ?? null)): ?><ul><?php foreach (($item['children'] ?? null) as $c):  ?><li><a<?= bnc_attr('href', ($c['href'] ?? null)) ?>><?= e(($c['label'] ?? null)) ?></a></li><?php endforeach; ?></ul><?php endif; ?>
              </li><?php endforeach; ?>
          </ul>
        </nav>
      </details>
    </div>
  </header>

  <main id="main"><?= $slot ?? "" ?></main>

  <footer class="site">
    <div class="wrap">
      <div class="cols">
        <div>
          <span class="logo-f"><img src="/img/logo.webp" alt="Book Nile Cruises" width="640" height="189" loading="lazy" /></span>
          <p>Nile cruises, dahabiyas, day tours and Egypt tour packages, arranged by our team in Luxor.</p>
        </div>
        <div>
          <h2>Nile cruises</h2>
          <ul><?php foreach ((($NAV[0] ?? null)['children'] ?? null) as $c):  ?><li><a<?= bnc_attr('href', ($c['href'] ?? null)) ?>><?= e(($c['label'] ?? null)) ?></a></li><?php endforeach; ?></ul>
        </div>
        <div>
          <h2>Tours</h2>
          <ul>
            <?php foreach ((($NAV[1] ?? null)['children'] ?? null) as $c):  ?><li><a<?= bnc_attr('href', ($c['href'] ?? null)) ?>><?= e(($c['label'] ?? null)) ?></a></li><?php endforeach; ?>
            <li><a href="/egypt-tour-packages/">Egypt Tour Packages</a></li>
            <li><a href="/transfers/">Transfers</a></li>
          </ul>
        </div>
        <div>
          <h2>Get in touch</h2>
          <ul>
            <li><a<?= bnc_attr('href', bnc_whatsapp()) ?>>WhatsApp <?= e(($SITE['phoneDisplay'] ?? null)) ?></a></li>
            <li><a<?= bnc_attr('href', ('mailto:' . ($SITE['email'] ?? null) . '')) ?>><?= e(($SITE['email'] ?? null)) ?></a></li>
            <li><?= e(($SITE['address'] ?? null)) ?></li>
            <li><a href="/about-us/">About us</a> · <a href="/faq/">FAQ</a> · <a href="/blog/">Blog</a></li>
            <li><a href="/terms-and-conditions/">Terms and Conditions</a></li>
          </ul>
        </div>
      </div>
      <div class="copy">
        <span>© <?= e(date('Y')) ?> Book Nile Cruises</span>
        <span>Prices in USD per person unless stated</span>
        <span>Powered by <a class="credit" href="https://www.luxorandaswantours.net/" target="_blank" rel="noopener">Emam</a></span>
      </div>
    </div>
  </footer>
  <a class="wa-float"<?= bnc_attr('href', bnc_whatsapp('Hello, I would like to ask about a trip.')) ?> aria-label="Chat with us on WhatsApp">
    <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2c-1.5 0-3-.4-4.3-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.1-1.2l-.4-.2Z"></path></svg>
    <span>WhatsApp</span>
  </a>
</body>
</html>


