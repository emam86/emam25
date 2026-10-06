<?php
declare(strict_types=1);

namespace Bnc\Controller;

use Bnc\{Audit, Db, Redirect, Request, Settings};
use Bnc\Seo\Checker;

final class SeoReportController extends Controller
{
    public function index(array $errors = []): string
    {
        $report = Db::one('SELECT * FROM seo_reports ORDER BY id DESC LIMIT 1');
        $severity = Request::str('severity', '', true);
        $groups = [];
        foreach (['error', 'warning', 'info'] as $level) {
            if ($severity !== '' && $severity !== $level) continue;
            foreach ($report ? json_decode($report['issues'], true, 512, JSON_THROW_ON_ERROR) : [] as $issue) {
                if ($issue['severity'] === $level) $groups[$level][$issue['entity']][] = $issue;
            }
        }
        return $this->view('seo/report', ['title' => 'فحص SEO', 'report' => $report, 'groups' => $groups, 'severity' => $severity, 'errors' => $errors, 'history' => Db::all('SELECT id, created_at, error_count, warning_count FROM seo_reports ORDER BY id DESC LIMIT 10')]);
    }
    public function save(): string|Redirect
    {
        $action = Request::str('action', 'run');
        if ($action === 'email') {
            $email = Request::str('seo_report_email');
            if ($email !== '' && (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
                http_response_code(422);
                return $this->index(['اكتب بريداً إلكترونياً صالحاً.']);
            }
            Settings::set('seo_report_email', $email);
            Audit::log('update', 'settings', null, 'حفظ بريد تقرير SEO');
        } elseif ($action === 'generate') {
            Settings::set('indexnow_key', bin2hex(random_bytes(16)));
            Audit::log('update', 'settings', null, 'توليد مفتاح IndexNow');
        } else {
            $report = (new Checker())->run();
            Audit::log('create', 'seo_report', $report['id'], 'فحص SEO');
        }
        return $this->redirect('/seo/report', 'تم حفظ التغييرات.');
    }
}
