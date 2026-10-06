<?php
declare(strict_types=1);

namespace Bnc\Controller;

use Bnc\{Config, Db, Redirect};
use Bnc\Api\ApiException;
use Bnc\Publish\Publisher;

final class PublishController extends Controller
{
    public function index(array $errors = []): string
    {
        $config = [];
        foreach (['token', 'repo', 'workflow'] as $key) $config[$key] = (string) Config::get('github.' . $key, '') !== '';
        return $this->view('publish/index', ['title' => 'النشر', 'rows' => Db::all('SELECT * FROM publish_jobs ORDER BY id DESC LIMIT 20'), 'config' => $config, 'errors' => $errors]);
    }

    public function publish(): string|Redirect
    {
        try { Publisher::start($this->user()['email']); }
        catch (ApiException $e) {
            http_response_code($e->status);
            return $this->index([$e->status === 409 ? 'يوجد طلب نشر قيد التنفيذ منذ أقل من ٢٠ دقيقة.' : 'تعذر بدء النشر. راجع الإعدادات وسجل النشر.']);
        }
        return $this->redirect('/publish', 'تم إرسال طلب النشر.');
    }
}
