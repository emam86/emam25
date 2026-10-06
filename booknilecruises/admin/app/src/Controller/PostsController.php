<?php
declare(strict_types=1);

namespace Bnc\Controller;

use Bnc\{Db, Redirect, Request};
use Bnc\Posts\{PostForm, PostRepository};

final class PostsController extends Controller
{
    public function index(): string
    {
        $q = Request::str('q', '', true);
        $where = $q === '' ? '1=1' : '(title LIKE ? OR slug LIKE ?)';
        $search = '%' . addcslashes($q, '%_\\') . '%';
        $params = $q === '' ? [] : [$search, $search];
        $total = (int) Db::value("SELECT COUNT(*) FROM posts WHERE $where", $params);
        $pages = max(1, (int) ceil($total / 50));
        $page = min($pages, max(1, Request::int('page', 1, true)));
        $rows = Db::all("SELECT * FROM posts WHERE $where ORDER BY published_at DESC, id DESC LIMIT 50 OFFSET " . (($page - 1) * 50), $params);
        return $this->view('posts/index', ['title' => 'المقالات'] + compact('rows', 'q', 'total', 'pages', 'page'));
    }
    public function create(): string { return $this->form(PostForm::defaults()); }
    public function edit(int $id): string { return $this->form(PostRepository::find($id)); }
    public function store(): string|Redirect { return $this->save(); }
    public function update(int $id): string|Redirect { PostRepository::find($id); return $this->save($id); }

    private function save(int $id = 0): string|Redirect
    {
        $d = PostForm::input(); $d['id'] = $id ?: null;
        try { $saved = PostRepository::save($d, $id); }
        catch (\DomainException $e) { return $this->form($d, explode("\n", $e->getMessage()), 422); }
        catch (\PDOException $e) {
            if ($e->getCode() !== '23000') throw $e;
            return $this->form($d, ['تعارض الرابط أو الصورة مع تعديل آخر.'], 422);
        }
        if ($saved === null) return $this->form($d, ['تم تعديل المقال بواسطة مستخدم آخر. أعد فتح الصفحة.'], 409);
        return $this->redirect("/posts/$saved/edit", 'تم حفظ المقال.');
    }

    public function confirmDelete(int $id): string
    {
        return $this->view('posts/delete', ['title' => 'حذف المقال', 'post' => PostRepository::find($id), 'errors' => [], 'target' => '/blog/']);
    }
    public function delete(int $id): string|Redirect
    {
        $target = Request::str('redirect_to');
        try { PostRepository::delete($id, $target); }
        catch (\DomainException $e) {
            http_response_code(422);
            return $this->view('posts/delete', ['title' => 'حذف المقال', 'post' => PostRepository::find($id), 'target' => $target, 'errors' => [$e->getMessage()]]);
        }
        return $this->redirect('/posts', 'تم حذف المقال.');
    }
    private function form(array $d, array $errors = [], int $status = 200): string
    {
        http_response_code($status);
        $d['image_id'] ??= '';
        $d['published_at'] = str_replace(' ', 'T', $d['published_at'] ?? $d['created_at'] ?? date('Y-m-d H:i:s'));
        $photo = $d['image_id'] ? Db::one('SELECT * FROM media WHERE id = ?', [$d['image_id']]) : null;
        return $this->view('posts/form', ['title' => $d['id'] ? 'تعديل مقال' : 'إضافة مقال'] + compact('d', 'errors', 'photo'));
    }
}
