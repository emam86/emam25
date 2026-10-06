<?php
declare(strict_types=1);

namespace Bnc\Controller;

use Bnc\{Audit, Db, Redirect, Redirects, Request, SitePages};

final class RedirectsController extends Controller
{
    public function index(): string
    {
        $q = Request::str('q', '', true);
        $search = '%' . addcslashes($q, '%_\\') . '%';
        $params = [$search, $search];
        $total = (int) Db::value('SELECT COUNT(*) FROM redirects WHERE from_path LIKE ? OR to_path LIKE ?', $params);
        $pages = max(1, (int) ceil($total / 50));
        $page = min($pages, max(1, Request::int('page', 1, true)));
        $rows = Db::all('SELECT * FROM redirects WHERE from_path LIKE ? OR to_path LIKE ? ORDER BY created_at DESC, id DESC LIMIT 50 OFFSET ' . (($page - 1) * 50), $params);
        $live = SitePages::livePaths(array_column($rows, 'from_path'));
        return $this->view('redirects/index', ['title' => 'التحويلات'] + compact('rows', 'q', 'total', 'pages', 'page', 'live'));
    }
    public function create(): string { return $this->form(['id' => null, 'from_path' => '', 'to_path' => '']); }
    public function edit(int $id): string { return $this->form($this->find($id)); }
    public function store(): string|Redirect { return $this->save(); }
    public function update(int $id): string|Redirect { $this->find($id); return $this->save($id); }
    private function save(int $id = 0): string|Redirect
    {
        $d = ['id' => $id ?: null, 'from_path' => Request::str('from_path'), 'to_path' => Request::str('to_path')];
        try { $resolved = Redirects::save($d['from_path'], $d['to_path'], $id); }
        catch (\DomainException $e) { return $this->form($d, [$e->getMessage()], 422); }
        catch (\PDOException $e) {
            if ($e->getCode() !== '23000') throw $e;
            return $this->form($d, ['مسار المصدر مستخدم بالفعل.'], 422);
        }
        $message = 'تم حفظ التحويل.';
        if ($resolved !== $d['to_path']) $message .= ' تم اختصار سلسلة التحويل إلى ' . $resolved;
        if (SitePages::live($d['from_path'])) $message .= ' هذه الصفحة موجودة؛ التحويل سيخفيها.';
        return $this->redirect('/redirects', $message);
    }
    public function delete(int $id): Redirect
    {
        Db::tx(function () use ($id): void {
            $old = $this->find($id);
            Db::run('DELETE FROM redirects WHERE id = ?', [$id]);
            Audit::log('delete', 'redirect', $id, 'حذف التحويل', ['old' => $old]);
        });
        return $this->redirect('/redirects', 'تم حذف التحويل.');
    }
    private function find(int $id): array { return Db::one('SELECT * FROM redirects WHERE id = ?', [$id]) ?? $this->notFound(); }
    private function form(array $d, array $errors = [], int $status = 200): string
    {
        http_response_code($status);
        return $this->view('redirects/form', ['title' => 'حفظ تحويل', 'live' => SitePages::live($d['from_path'])] + compact('d', 'errors'));
    }
}
