<?php
declare(strict_types=1);

namespace Bnc\Webhooks;

use Bnc\{Audit, Config, Db};
use Bnc\Api\SafeHttp;

final class Delivery
{
    public const EVENTS = ['enquiry.created', 'post.published', 'site.published'];

    public static function signature(string $body, string $secret): string
    {
        return 'sha256=' . hash_hmac('sha256', $body, $secret);
    }

    public static function fire(string $event, array $data, ?string $actor = null): void
    {
        try {
            foreach (Db::all('SELECT * FROM webhooks WHERE is_active = 1') as $hook) {
                try {
                    if (in_array($event, json_decode($hook['events'], true), true)) self::send($hook, $event, $data, $actor);
                } catch (\Throwable) { error_log('[bnc] Webhook notification failed'); }
            }
        } catch (\Throwable) { error_log('[bnc] Webhook notification failed'); }
    }

    public static function send(array $hook, string $event, array $data, ?string $actor = null): void
    {
        $body = json_encode(['event' => $event, 'sent_at' => gmdate('Y-m-d\TH:i:s\Z'), 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        try {
            $ch = SafeHttp::handle($hook['url'], Config::get('webhooks_allow_private', false) === true);
            $bytes = 0;
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-BNC-Event: ' . $event, 'X-BNC-Signature: ' . self::signature($body, $hook['secret'])], CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$bytes): int {
                $bytes += strlen($chunk); return $bytes <= 65536 ? strlen($chunk) : 0;
            }]);
            $ok = curl_exec($ch);
            $status = $ok === false ? (curl_errno($ch) === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'connection failed') : (string) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
        } catch (\RuntimeException $e) { $status = $e->getMessage(); }
        Db::tx(function () use ($hook, $event, $status, $actor): void {
            Db::update('webhooks', ['last_status' => substr($status, 0, 60), 'last_called_at' => date('Y-m-d H:i:s')], 'id = ?', [$hook['id']]);
            Audit::log('deliver', 'webhook', $hook['id'], 'إرسال إشعار Webhook', ['event' => $event, 'status' => $status], $actor);
        });
    }
}
