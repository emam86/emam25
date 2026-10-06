<?php
declare(strict_types=1);

namespace Bnc\Controller;

use Bnc\{Db, Redirect, Request, Seo, SitePages};

final class SeoController extends Controller
{
    public function index(): string
    {
        $overrides = array_column(Db::all('SELECT * FROM seo_overrides ORDER BY path'), null, 'path');
        $paths = array_values(array_unique([...SitePages::FIXED, ...array_keys($overrides)]));
        return $this->view('seo/index', ['title' => 'SEO الصفحات'] + compact('paths', 'overrides'));
    }
    public function edit(): string
    {
        $path = Request::str('path', '/', true);
        return $this->form(Db::one('SELECT * FROM seo_overrides WHERE path = ?', [$path]) ?? ['path' => $path, 'title' => '', 'description' => '', 'noindex' => 0], SitePages::validPath($path) ? [] : ['مسار الصفحة غير صالح.'], SitePages::validPath($path) ? 200 : 422);
    }
    public function save(): string|Redirect
    {
        $d = ['path' => Request::str('path'), 'title' => Request::str('seo_title'), 'description' => Request::str('seo_description'), 'noindex' => Request::bool('noindex') ? 1 : 0];
        try { Seo::save($d); }
        catch (\DomainException $e) { return $this->form($d, explode("\n", $e->getMessage()), 422); }
        return $this->redirect('/seo', 'تم حفظ SEO الصفحة.');
    }
    private function form(array $d, array $errors = [], int $status = 200): string
    {
        http_response_code($status);
        $editor = SitePages::editor($d['path']);
        $d['seo_title'] = $d['title'] ?? ''; $d['seo_description'] = $d['description'] ?? '';
        return $this->view('seo/form', ['title' => 'تعديل SEO الصفحة'] + compact('d', 'errors', 'editor'));
    }
}
