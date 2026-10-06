<?php
declare(strict_types=1);

namespace Bnc\Controller;

use Bnc\{Auth, SitePages};

final class SitemapController extends Controller
{
    public function index(): string
    {
        if (!Auth::can('sitemap.edit') && !Auth::can('seo.edit')) $this->forbidden();
        return $this->view('sitemap/index', ['title' => 'السايت ماب'] + SitePages::sitemap());
    }
}
