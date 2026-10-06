<?php
declare(strict_types=1);

namespace Bnc\Seo;

use Bnc\{Audit, Config, Settings};
use Bnc\Enquiries\Notifier;

final class ReportMailer
{
    public static function send(array $report): void
    {
        $to = trim((string) Settings::get('seo_report_email', '')) ?: (trim((string) Settings::get('enquiry_notify_email', '')) ?: (string) Config::get('mail.notify', ''));
        if (trim($to) === '') return;
        $base = rtrim((string) Config::get('site_url'), '/') . rtrim((string) Config::get('admin_path', '/admin'), '/');
        $body = 'أخطاء: ' . $report['error_count'] . '، تحذيرات: ' . $report['warning_count'] . "\n" . $base . "/seo/report\n\n";
        $issues = json_decode($report['issues'], true, 512, JSON_THROW_ON_ERROR);
        $rank = ['error' => 0, 'warning' => 1, 'info' => 2];
        usort($issues, fn ($a, $b) => $rank[$a['severity']] <=> $rank[$b['severity']]);
        foreach (array_slice($issues, 0, 20) as $issue) $body .= $issue['severity'] . ': ' . $issue['title'] . ' — ' . $issue['message'] . "\n" . $base . $issue['edit_url'] . "\n\n";
        if (!Notifier::mail($to, 'تقرير فحص SEO الأسبوعي', $body)) Audit::log('notification_failed', 'seo_report', $report['id'], 'تعذر إرسال بريد تقرير SEO', null, 'seo-check');
    }
}
