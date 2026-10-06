<?php
declare(strict_types=1);

namespace Bnc\Enquiries;

use Bnc\{Audit, Config, Settings};
use Bnc\Webhooks\Delivery;

final class Notifier
{
    public static function header(string $value): string
    {
        return str_replace(["\r", "\n"], '', $value);
    }

    public static function send(array $enquiry): void
    {
        try {
            $to = self::header((string) Settings::get('enquiry_notify_email', (string) Config::get('mail.notify', '')));
            $from = self::header((string) Config::get('mail.from', ''));
            $subject = self::header('استفسار جديد: ' . $enquiry['name']);
            $body = '';
            foreach ($enquiry as $key => $value) $body .= "$key: $value\n";
            $body .= 'Panel: ' . rtrim((string) Config::get('site_url'), '/') . rtrim((string) Config::get('admin_path', '/admin'), '/') . '/enquiries/' . $enquiry['id'];
            $ok = filter_var($to, FILTER_VALIDATE_EMAIL) && filter_var($from, FILTER_VALIDATE_EMAIL) && @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, 'From: ' . $from . "\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8");
            if (!$ok) Audit::log('notification_failed', 'enquiry', $enquiry['id'], 'تعذر إرسال بريد الاستفسار', null, 'api:website');
        } catch (\Throwable) { error_log('[bnc] Enquiry email notification failed'); }
        Delivery::fire('enquiry.created', $enquiry, 'api:website');
    }
}
