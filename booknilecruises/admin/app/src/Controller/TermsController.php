<?php
declare(strict_types=1);

namespace Bnc\Controller;

use Bnc\{Db, Redirect, Terms};

final class TermsController extends Controller
{
    public function index(): string
    {
        return $this->view('terms/index', ['title' => 'التصنيفات', 'terms' => Db::all('SELECT t.*, p.name AS parent_name, (SELECT COUNT(*) FROM trip_terms tt WHERE tt.term_id = t.id) AS trip_count FROM terms t LEFT JOIN terms p ON p.id = t.parent_id ORDER BY t.taxonomy, t.sort_order, t.name')]);
    }

    public function create(): string
    {
        return $this->form(['id' => null, 'taxonomy' => 'destination', 'name' => '', 'slug' => '', 'parent_id' => '', 'description' => '', 'seo_title' => '', 'seo_description' => '']);
    }
    public function edit(int $id): string { return $this->form(Terms::find($id)); }
    public function store(): string|Redirect { return $this->save(); }
    public function update(int $id): string|Redirect { return $this->save(Terms::find($id)); }

    private function save(?array $old = null): string|Redirect
    {
        $d = Terms::input($old);
        $errors = Terms::validate($d, $old);
        if (!$errors) {
            try { Terms::save($d, $old); }
            catch (\DomainException $e) { $errors = explode("\n", $e->getMessage()); }
            catch (\PDOException $e) { if ($e->getCode() !== '23000') throw $e; $errors = ['الرابط مستخدم بالفعل أو التصنيف الأب تغير.']; }
        }
        if ($errors) {
            http_response_code(422);
            return $this->form($d + ['id' => $old['id'] ?? null], $errors, $old);
        }
        return $this->redirect('/terms', 'تم حفظ التصنيف.');
    }

    public function delete(int $id): Redirect
    {
        try { Terms::delete($id); }
        catch (\DomainException $e) { return $this->redirect('/terms', $e->getMessage(), 'error'); }
        return $this->redirect('/terms', 'تم حذف التصنيف.');
    }

    private function form(array $d, array $errors = [], ?array $old = null): string
    {
        return $this->view('terms/form', ['title' => $d['id'] ? 'تعديل تصنيف' : 'إضافة تصنيف', 'd' => $d, 'errors' => $errors, 'protected' => Terms::protected($old ?? $d), 'parents' => Db::all('SELECT * FROM terms WHERE parent_id IS NULL ORDER BY taxonomy, name')]);
    }
}
