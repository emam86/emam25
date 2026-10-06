<?php
declare(strict_types=1);

namespace Bnc\Controller;

use Bnc\{Redirect, Settings};

final class SettingsController extends Controller
{
    public function form(): string { return $this->render(Settings::site()); }
    public function save(): string|Redirect
    {
        $d = Settings::input();
        try { Settings::saveSite($d); }
        catch (\DomainException $e) { http_response_code(422); return $this->render($d, explode("\n", $e->getMessage())); }
        return $this->redirect('/settings', 'تم حفظ الإعدادات. تظهر التغييرات بعد النشر القادم.');
    }
    private function render(array $d, array $errors = []): string
    {
        return $this->view('settings/form', ['title' => 'الإعدادات'] + compact('d', 'errors'));
    }
}
