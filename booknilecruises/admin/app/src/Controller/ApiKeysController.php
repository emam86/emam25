<?php
declare(strict_types=1);

namespace Bnc\Controller;

use Bnc\{Audit, Db, Redirect, Request};
use Bnc\Api\{ApiException, Keys};

final class ApiKeysController extends Controller
{
    public function index(array $errors = []): string
    {
        $secret = $_SESSION['new_api_key'] ?? null;
        unset($_SESSION['new_api_key']);
        return $this->view('api-keys/index', ['title' => 'مفاتيح API', 'rows' => Db::all('SELECT id, name, key_prefix, scopes, last_used_at, is_active FROM api_keys ORDER BY id DESC'), 'secret' => $secret, 'errors' => $errors]);
    }

    public function create(): string|Redirect
    {
        try { $token = Keys::create(Request::str('name'), Request::list('scopes'), (int) $this->user()['id']); }
        catch (ApiException) { http_response_code(422); return $this->index(['اكتب اسمًا حتى ١٠٠ حرف واختر صلاحية واحدة على الأقل.']); }
        $_SESSION['new_api_key'] = $token;
        return $this->redirect('/api-keys', 'تم إنشاء المفتاح. انسخه الآن؛ سيظهر مرة واحدة فقط.');
    }

    public function revoke(int $id): Redirect { return $this->remove($id, false); }
    public function delete(int $id): Redirect { return $this->remove($id, true); }

    private function remove(int $id, bool $delete): Redirect
    {
        if (!Db::value('SELECT id FROM api_keys WHERE id = ?', [$id])) $this->notFound();
        Db::tx(function () use ($id, $delete): void {
            if ($delete) Db::run('DELETE FROM api_keys WHERE id = ?', [$id]);
            else Db::update('api_keys', ['is_active' => 0], 'id = ?', [$id]);
            Audit::log($delete ? 'delete' : 'revoke', 'api_key', $id, $delete ? 'حذف مفتاح API' : 'إلغاء مفتاح API');
        });
        return $this->redirect('/api-keys', 'تم تحديث المفتاح.');
    }
}
