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
        $row = ['name' => Input::text($d, 'name', 190, true), 'channel' => Input::text($d, 'channel', 20) ?: 'form', 'message' => Input::text($d, 'message', 5000), 'page_url' => Input::text($d, 'page_url', 255), 'ip' => Request::ip()];
        if (!in_array($row['channel'], ['whatsapp', 'email', 'form'], true)) Input::invalid('channel');
        if ($row['page_url'] !== '' && (!preg_match('#^/(?!/)[A-Za-z0-9._~%/-]*(?:\?[A-Za-z0-9._~%=&/-]*)?$#D', $row['page_url']) || str_contains(rawurldecode($row['page_url']), '..'))) Input::invalid('page_url');
        foreach (['email' => 190, 'phone' => 60] as $field => $max) {
            $v = $d[$field] ?? '';
            if (!is_string($v)) Input::invalid($field);
            $v = trim($v);
            $valid = mb_strlen($v) <= $max && ($v === '' || ($field === 'email' ? filter_var($v, FILTER_VALIDATE_EMAIL) !== false : preg_match('/^[+0-9 ()-]+$/D', $v)));
            if (!$valid) {
                if ($row['channel'] === 'form') Input::invalid($field);
                $row['message'] .= "\n$field: " . $v;
                $v = '';
            }
            $row[$field] = $v === '' ? null : $v;
        }
        if ($row['channel'] === 'form' && !$row['email'] && !$row['phone']) Input::invalid('contact', 'Email or phone is required');
        $date = Input::text($d, 'travel_date', 10);
        if ($date !== '') {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date || $date < date('Y-m-d')) Input::invalid('travel_date');
        }
        $row['travel_date'] = $date ?: null;
        foreach (['adults', 'children'] as $field) {
            $v = $d[$field] ?? null;
            if ($v === '' || $v === null) { $row[$field] = null; continue; }
            if ((!is_int($v) && !(is_string($v) && ctype_digit($v))) || (int) $v < 0 || (int) $v > 99) Input::invalid($field);
            $row[$field] = (int) $v;
        }
        $slug = Input::text($d, 'trip', 190);
        if ($slug !== '' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) Input::invalid('trip');
        $trip = $slug !== '' ? Db::one('SELECT id, title FROM trips WHERE slug = ?', [$slug]) : null;
        $row['trip_id'] = $trip['id'] ?? null; $row['trip_title'] = $trip['title'] ?? null;
        // A malformed optional contact is preserved, even when it exceeds the normal message limit.
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
