<?php
declare(strict_types=1);
// Copy to bnc-config.php NEXT TO the bnc-app folder (outside public_html) and fill in.
// Never commit the real file: it holds passwords and tokens.
return [
    'db' => [
        // On Hostinger use 127.0.0.1 (not the srvNNNN.hstgr.io host).
        'dsn'  => 'mysql:host=127.0.0.1;port=3306;dbname=u000000000_bnc;charset=utf8mb4',
        'user' => 'u000000000_bnc',
        'pass' => 'CHANGE-ME',
    ],
    'site_url'   => 'https://booknilecruises.net',
    'admin_path' => '/admin',
    'api_path' => '/api',
    // Optional absolute cache folder outside public_html (default: bnc-app/cache/site).
    'site_cache_dir' => null,
    'site_noindex' => false,
    // Test-only; keep false in production.
    'webhooks_allow_private' => false,
    // Absolute folder the uploaded photos go to, and its public URL path.
    'images_dir' => '/home/u000000000/domains/booknilecruises.net/public_html/images',
    'images_url' => '/images',
    'max_upload_mb' => 15,
    // One-time token for the web installer (/admin/install). Make it long and random,
    // and remove it after the first owner account exists.
    'install_token' => '',
    // Token for the retained content export and SEO-check API.
    'export_token' => '',
    'mail' => ['from' => 'info@booknilecruises.net', 'notify' => 'info@booknilecruises.net'],
    'timezone' => 'Africa/Cairo',
    // Cookies are Secure on HTTPS automatically; true forces it (behind a proxy).
    'force_secure_cookies' => false,
    'debug' => false,
];
