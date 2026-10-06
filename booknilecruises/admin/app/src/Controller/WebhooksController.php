<?php
declare(strict_types=1);

namespace Bnc\Controller;

use Bnc\{Audit, Db, Redirect, Request};
use Bnc\Webhooks\Delivery;

final class WebhooksController extends Controller
{
    public function index(): string
    {
        return $this->view('webhooks/index', ['title' => 'Webhooks', 'rows' => Db::all('SELECT id, name, url, events, is_active, last_status, last_called_at FROM webhooks ORDER BY id DESC')]);
    }
    public function create(): string { return $this->form(['id' => 0, 'name' => '', 'url' => '', 'events' => [], 'is_active' => 1]); }
    public function edit(int $id): string { return $this->form($this->find($id)); }
    public function store(): string|Redirect { return $this->save(); }
    public function update(int $id): string|Redirect { $this->find($id); return $this->save($id); }

    private function save(int $id = 0): string|Redirect
    {
        $d = ['id' => $id, 'name' => Request::str('name'), 'url' => Request::str('url'), 'events' => Request::list('events'), 'is_active' => Request::bool('is_active') ? 1 : 0];
        $p = parse_url($d['url']);
        if ($d['name'] === '' || mb_strlen($d['name']) > 100 || strlen($d['url']) > 500 || ($p['scheme'] ?? '') !== 'https' || !isset($p['host']) || isset($p['user']) || isset($p['pass']) || preg_match('/[\x00-\x20\\\\]/', $d['url']) || !$d['events'] || array_diff($d['events'], Delivery::EVENTS)) {
            http_response_code(422); return $this->form($d, ['اكتب اسمًا ورابط HTTPS صحيحين واختر الأحداث.']);
        }
        $secret = !$id ? bin2hex(random_bytes(32)) : null;
        $id = Db::tx(function () use ($id, $d, $secret): int {
            $row = ['name' => $d['name'], 'url' => $d['url'], 'events' => json_encode(array_values(array_unique($d['events']))), 'is_active' => $d['is_active']];
            if ($id) Db::update('webhooks', $row, 'id = ?', [$id]);
            else $id = Db::insert('webhooks', $row + ['secret' => $secret]);
            Audit::log($secret ? 'create' : 'update', 'webhook', $id, 'حفظ Webhook', ['events' => $d['events']]);
            return $id;
        });
        if ($secret) $_SESSION['webhook_secret'][$id] = $secret;
        return $this->redirect("/webhooks/$id/edit", 'تم حفظ Webhook.');
    }

    public function regenerate(int $id): Redirect
    {
        $this->find($id); $secret = bin2hex(random_bytes(32));
        Db::tx(function () use ($id, $secret): void {
            Db::update('webhooks', ['secret' => $secret], 'id = ?', [$id]);
            Audit::log('regenerate', 'webhook', $id, 'تجديد سر توقيع Webhook');
        });
        $_SESSION['webhook_secret'][$id] = $secret;
        return $this->redirect("/webhooks/$id/edit", 'تم تجديد السر. انسخه الآن.');
    }

    public function test(int $id): Redirect
    {
        Delivery::send($this->find($id), 'ping', ['message' => 'BNC webhook test']);
        Audit::log('test', 'webhook', $id, 'اختبار Webhook');
        return $this->redirect("/webhooks/$id/edit", 'تم تنفيذ الاختبار. راجع نتيجة الإرسال.');
    }

    public function delete(int $id): Redirect
    {
        $this->find($id);
        Db::tx(function () use ($id): void {
            Db::run('DELETE FROM webhooks WHERE id = ?', [$id]);
            Audit::log('delete', 'webhook', $id, 'حذف Webhook');
        });
        unset($_SESSION['webhook_secret'][$id]);
        return $this->redirect('/webhooks', 'تم حذف Webhook.');
    }

    private function find(int $id): array
    {
        $row = Db::one('SELECT * FROM webhooks WHERE id = ?', [$id]) ?? $this->notFound();
        $row['events'] = json_decode($row['events'], true);
        return $row;
    }

    private function form(array $d, array $errors = []): string
    {
        // One-time creation/regeneration display respects the no-secret redisplay policy.
        $secret = $_SESSION['webhook_secret'][$d['id']] ?? null;
        unset($_SESSION['webhook_secret'][$d['id']]);
        unset($d['secret']);
        return $this->view('webhooks/form', ['title' => $d['id'] ? 'تعديل Webhook' : 'إضافة Webhook'] + compact('d', 'errors', 'secret'));
    }
}
