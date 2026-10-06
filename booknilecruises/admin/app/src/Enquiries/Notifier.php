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

    /** Shared plain-text UTF-8 mail transport. */
    public static function mail(string $to, string $subject, string $body): bool
    {
        $to = self::header($to);
        $from = self::header((string) Config::get('mail.from', ''));
        try {
            if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) return false;
            $fake = Config::get('mail.fake');
            if (is_string($fake) && $fake !== '') {
                file_put_contents($fake, json_encode(['to' => $to, 'from' => $from, 'subject' => self::header($subject), 'body' => $body], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                return true;
            }
            return (bool) (filter_var($to, FILTER_VALIDATE_EMAIL) && filter_var($from, FILTER_VALIDATE_EMAIL) && @mail($to, '=?UTF-8?B?' . base64_encode(self::header($subject)) . '?=', $body, 'From: ' . $from . "\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8"));
        } catch (\Throwable) { return false; }
    }

    public static function send(array $enquiry): void
    {
        try {
            $to = self::header(trim((string) Settings::get('enquiry_notify_email', '')) ?: (string) Config::get('mail.notify', ''));
            $subject = self::header('استفسار جديد: ' . $enquiry['name']);
            $body = '';
            foreach ($enquiry as $key => $value) $body .= "$key: $value\n";
            $body .= 'Panel: ' . rtrim((string) Config::get('site_url'), '/') . rtrim((string) Config::get('admin_path', '/admin'), '/') . '/enquiries/' . $enquiry['id'];
            $ok = $to === '' || self::mail($to, $subject, $body);
            if (!$ok) Audit::log('notification_failed', 'enquiry', $enquiry['id'], 'تعذر إرسال بريد الاستفسار', null, 'api:website');
        } catch (\Throwable) { error_log('[bnc] Enquiry email notification failed'); }
        Delivery::fire('enquiry.created', $enquiry, 'api:website');
    }
}
