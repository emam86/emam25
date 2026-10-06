<?php
declare(strict_types=1);

namespace Bnc\Enquiries;

use Bnc\{Audit, Config, Db, Request};
use Bnc\Api\{ApiException, Input, RateLimit};

final class EnquiryService
{
    public const STATUSES = ['new' => 'جديد', 'contacted' => 'تم التواصل', 'booked' => 'تم الحجز', 'closed' => 'مغلق', 'spam' => 'مزعج'];

    public static function origin(?string $origin): void
    {
        if ($origin !== null && $origin !== rtrim((string) Config::get('site_url'), '/')) throw new ApiException(403, 'Origin not allowed');
    }

    /** Returns null for a honeypot. Notifications run after the response in ApiApp. */
    public static function create(array $d): ?array
    {
        if (($d['website'] ?? '') !== '') return null;
        RateLimit::take('enquiries', [['enquiry:ip:' . Request::ip(), 600, 5], ['enquiry:all', 86400, 200]]);
        $raw = static fn (mixed $v): string => is_string($v) ? $v : (json_encode($v, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '');
        $notes = [];
        $repair = static function (string $field) use (&$notes, $d, $raw): void { $notes[] = '[' . $field . ': ' . $raw($d[$field] ?? '') . ']'; };
        $text = static fn (string $field): string => is_string($d[$field] ?? null) ? trim($d[$field]) : '';
        $row = ['name' => $text('name'), 'channel' => $text('channel') ?: 'form', 'message' => mb_substr($text('message'), 0, 5000), 'page_url' => $text('page_url'), 'ip' => Request::ip()];
        foreach (['message', 'page_url'] as $field) if (isset($d[$field]) && !is_string($d[$field])) $repair($field);
        if ($row['name'] === '' || mb_strlen($row['name']) > 190) { $repair('name'); $row['name'] = $row['name'] === '' ? '(no name)' : mb_substr($row['name'], 0, 190); }
        if ((array_key_exists('channel', $d) && !is_string($d['channel'])) || !in_array($row['channel'], ['whatsapp', 'email', 'form'], true)) { $repair('channel'); $row['channel'] = 'form'; }
        if (mb_strlen($row['page_url']) > 255 || ($row['page_url'] !== '' && (!preg_match('#^/(?!/)[A-Za-z0-9._~%/-]*(?:\?[A-Za-z0-9._~%=&/-]*)?$#D', $row['page_url']) || str_contains(rawurldecode($row['page_url']), '..')))) { $repair('page_url'); $row['page_url'] = ''; }
        foreach (['email' => 190, 'phone' => 60] as $field => $max) {
            $v = $text($field);
            $valid = is_string($d[$field] ?? '') && mb_strlen($v) <= $max && ($v === '' || ($field === 'email' ? filter_var($v, FILTER_VALIDATE_EMAIL) !== false : preg_match('/^[+0-9 ()-]+$/D', $v)));
            if (!$valid) { $repair($field); $v = ''; }
            $row[$field] = $v === '' ? null : $v;
        }
        if ($row['channel'] === 'form' && !$row['email'] && !$row['phone']) Input::invalid('contact', 'Email or phone is required');
        $date = $text('travel_date');
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $earliest = (new \DateTimeImmutable('today', new \DateTimeZone('Africa/Cairo')))->modify('-1 day')->format('Y-m-d');
        if (($d['travel_date'] ?? '') !== '' && (!$parsed || $parsed->format('Y-m-d') !== $date || $date < $earliest)) { $repair('travel_date'); $date = ''; }
        $row['travel_date'] = $date ?: null;
        foreach (['adults', 'children'] as $field) {
            $v = $d[$field] ?? null;
            $row[$field] = null;
            if ($v === '' || $v === null) continue;
            if ((!is_int($v) && !(is_string($v) && ctype_digit($v))) || (int) $v < 0 || (int) $v > 99) { $repair($field); continue; }
            $row[$field] = (int) $v;
        }
        $slug = $text('trip');
        $trip = $slug !== '' ? Db::one('SELECT id, title FROM trips WHERE slug = ?', [$slug]) : null;
        if (($d['trip'] ?? '') !== '' && !$trip) $repair('trip');
        $row['trip_id'] = $trip['id'] ?? null; $row['trip_title'] = $trip['title'] ?? null;
        if ($notes) $row['message'] .= "\n" . implode("\n", $notes);
        $row['id'] = Db::tx(function () use ($row): int {
            $id = Db::insert('enquiries', $row);
            Audit::log('create', 'enquiry', $id, 'استفسار جديد من الموقع', null, 'api:website');
            return $id;
        });
        return self::find($row['id']);
    }

    public static function find(int $id): array
    {
        return Db::one('SELECT * FROM enquiries WHERE id = ?', [$id]) ?? throw new \Bnc\HttpException(404);
    }

    public static function filter(string $status, string $q): array
    {
        $where = ['1=1']; $params = [];
        if (isset(self::STATUSES[$status])) { $where[] = 'status = ?'; $params[] = $status; }
        if ($q !== '') {
            $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ? OR message LIKE ?)';
            $search = '%' . addcslashes($q, '%_\\') . '%';
            $params = [...$params, $search, $search, $search, $search];
        }
        return [implode(' AND ', $where), $params];
    }

    public static function csvCell(mixed $value): string
    {
        $value = (string) $value;
        return preg_match('/^[\x00-\x20]*[=+@-]/', $value) ? "'" . $value : $value;
    }
}
