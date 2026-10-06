<?php
declare(strict_types=1);

namespace Bnc\Controller;

use Bnc\{Audit, Auth, Db, Json, Redirect, Request, Session};
use Bnc\Media\{Deletion, Files, Uploader, Usage};

final class MediaController extends Controller
{
    private const PER_PAGE = 48;
    private const BROWSE_PERMISSIONS = ['media.upload', 'media.delete', 'trips.edit', 'trips.create', 'posts.edit', 'posts.create'];

    public function index(): string
    {
        $this->allowBrowse();
        return $this->view('media/index', ['title' => 'الصور'] + $this->listing());
    }

    public function picker(): Json
    {
        $this->allowBrowse();
        $data = $this->listing();
        $items = array_map(fn ($row) => [
            'id' => (int) $row['id'], 'path' => $row['path'], 'thumb' => Files::thumb($row),
            'width' => (int) $row['width'], 'height' => (int) $row['height'], 'alt' => $row['alt'],
        ], $data['rows']);
        return new Json(['items' => $items, 'page' => $data['page'], 'pages' => $data['pages']]);
    }

    public function show(int $id): string
    {
        $this->allowBrowse();
        return $this->details($this->find($id));
    }

    public function upload(): Redirect
    {
        $photos = $_FILES['photos'] ?? [];
        $names = $photos['name'] ?? [];
        if (!is_array($names) || !$names) return $this->redirect('/media', 'اختر صورًا للرفع. قد يكون الطلب أكبر من الحد المسموح.', 'error');
        foreach ($names as $i => $name) {
            $file = [];
            foreach (['name', 'tmp_name', 'error', 'size'] as $key) $file[$key] = $photos[$key][$i] ?? null;
            if (!is_string($name)) { Session::flash('error', 'ملف الرفع غير صالح.'); continue; }
            try {
                (new Uploader())->save($file, (int) $this->user()['id']);
                Session::flash('ok', "تم رفع الصورة: $name.");
            } catch (\RuntimeException $e) {
                Session::flash('error', "$name: " . $e->getMessage());
            }
        }
        return $this->redirect('/media');
    }

    public function edit(int $id): string|Redirect
    {
        $media = $this->find($id);
        $alt = Request::str('alt');
        if (mb_strlen($alt) > 255) {
            http_response_code(422);
            $media['alt'] = $alt;
            return $this->details($media, ['النص البديل يجب ألا يتجاوز 255 حرفًا.']);
        }
        Db::tx(function () use ($id, $alt): void {
            Db::update('media', ['alt' => $alt], 'id = ?', [$id]);
            Audit::log('update', 'media', $id, 'عدّل النص البديل للصورة', ['alt' => $alt]);
        });
        return $this->redirect("/media/$id", 'تم حفظ النص البديل.');
    }

    public function delete(int $id): Redirect
    {
        $media = $this->find($id);
        try {
            Deletion::delete($media);
        } catch (\RuntimeException $e) {
            return $this->redirect("/media/$id", $e instanceof \PDOException ? 'تعذر حذف الصورة. حاول مرة أخرى.' : $e->getMessage(), 'error');
        }
        return $this->redirect('/media', 'تم حذف الصورة.');
    }

    private function allowBrowse(): void
    {
        foreach (self::BROWSE_PERMISSIONS as $permission) if (Auth::can($permission)) return;
        $this->forbidden();
    }

    private function find(int $id): array
    {
        return Db::one('SELECT * FROM media WHERE id = ?', [$id]) ?? $this->notFound();
    }

    private function details(array $media, array $errors = []): string
    {
        return $this->view('media/show', ['title' => 'تفاصيل الصورة', 'media' => $media, 'sizes' => Files::sizes($media), 'uses' => Usage::of($media), 'errors' => $errors]);
    }

    private function listing(): array
    {
        $q = Request::str('q', '', true);
        $where = $q === '' ? '' : 'WHERE path LIKE ? OR alt LIKE ?';
        $search = '%' . addcslashes($q, '%_\\') . '%';
        $params = $q === '' ? [] : [$search, $search];
        $total = (int) Db::value("SELECT COUNT(*) FROM media $where", $params);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($pages, max(1, Request::int('page', 1, true)));
        $rows = Db::all("SELECT * FROM media $where ORDER BY id DESC LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE), $params);
        return compact('rows', 'q', 'page', 'pages', 'total');
    }
}
