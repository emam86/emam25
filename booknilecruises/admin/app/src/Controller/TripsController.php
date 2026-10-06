<?php
declare(strict_types=1);

namespace Bnc\Controller;

use Bnc\{Audit, Db, Redirect, Redirects, Request};
use Bnc\Trips\{TripForm, TripRepository};

final class TripsController extends Controller
{
    public function index(): string
    {
        $q = Request::str('q', '', true);
        $status = Request::str('status', '', true);
        $category = Request::int('category', 0, true);
        $where = ['1=1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(t.title LIKE ? OR t.slug LIKE ? OR t.code LIKE ?)';
            $search = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $search, $search, $search);
        }
        if (in_array($status, ['draft', 'published'], true)) { $where[] = 't.status = ?'; $params[] = $status; }
        if ($category) { $where[] = 'EXISTS (SELECT 1 FROM trip_terms tt WHERE tt.trip_id = t.id AND tt.term_id = ?)'; $params[] = $category; }
        $where = implode(' AND ', $where);
        $total = (int) Db::value("SELECT COUNT(*) FROM trips t WHERE $where", $params);
        $pages = max(1, (int) ceil($total / 50));
        $page = min($pages, max(1, Request::int('page', 1, true)));
        $rows = Db::all("SELECT t.*, m.path, m.sizes, m.alt FROM trips t LEFT JOIN media m ON m.id = t.image_id WHERE $where ORDER BY t.updated_at DESC, t.id DESC LIMIT 50 OFFSET " . (($page - 1) * 50), $params);
        $terms = Db::all('SELECT * FROM terms ORDER BY taxonomy, sort_order, name');
        return $this->view('trips/index', ['title' => 'الرحلات'] + compact('rows', 'terms', 'q', 'status', 'category', 'total', 'pages', 'page'));
    }

    public function create(): string
    {
        return $this->form(array_replace(TripForm::defaults(), ['status' => 'draft', 'currency' => 'USD', 'duration_days' => '0', 'duration_nights' => '0', 'min_pax' => '1', 'max_pax' => '1']));
    }

    public function edit(int $id): string
    {
        return $this->form(TripForm::load(TripRepository::find($id)));
    }

    public function store(): string|Redirect { return $this->save(); }
    public function update(int $id): string|Redirect { TripRepository::find($id); return $this->save($id); }

    private function save(int $id = 0): string|Redirect
    {
        $d = TripForm::input();
        $d['id'] = $id ?: null;
        try {
            $saved = TripRepository::save($d, $id);
        } catch (\DomainException $e) {
            return $this->form($d, explode("\n", $e->getMessage()), 422);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23000') throw $e;
            return $this->form($d, ['تعارضت البيانات مع تعديل آخر. تحقق من الرابط والصور والتصنيفات.'], 422);
        }
        if ($saved === null) return $this->form($d, ['تم تعديل الرحلة بواسطة مستخدم آخر. أعد فتح الصفحة وراجع التغييرات قبل الحفظ.'], 409);
        return $this->redirect("/trips/$saved/edit", 'تم حفظ الرحلة.');
    }

    public function duplicate(int $id): Redirect
    {
        $new = TripRepository::duplicate($id);
        return $this->redirect("/trips/$new/edit", 'تم نسخ الرحلة كمسودة.');
    }

    private function targets(array $trip): array
    {
        $targets = ['/trip/' => 'قائمة الرحلات'];
        foreach (Db::all('SELECT t.url, t.name FROM terms t JOIN trip_terms tt ON tt.term_id = t.id WHERE tt.trip_id = ? ORDER BY tt.position', [$trip['id']]) as $term) $targets[$term['url']] = $term['name'];
        foreach (Db::all("SELECT slug, title FROM trips WHERE status = 'published' AND id <> ? ORDER BY title", [$trip['id']]) as $other) $targets['/trip/' . $other['slug'] . '/'] = $other['title'];
        return $targets;
    }

    public function confirmDelete(int $id): string
    {
        $trip = TripRepository::find($id);
        return $this->view('trips/delete', ['title' => 'حذف الرحلة', 'trip' => $trip, 'targets' => $trip['status'] === 'published' ? $this->targets($trip) : [], 'errors' => [], 'target' => '/trip/']);
    }

    public function delete(int $id): string|Redirect
    {
        $target = Request::str('redirect_to');
        $result = Db::tx(function () use ($id, $target): ?array {
            $trip = Db::one('SELECT * FROM trips WHERE id = ? FOR UPDATE', [$id]) ?? $this->notFound();
            $details = null;
            if ($trip['status'] === 'published') {
                $targets = $this->targets($trip);
                if (!isset($targets[$target])) return compact('trip', 'targets');
                Redirects::moved('/trip/' . $trip['slug'] . '/', $target);
                $details = ['redirect_to' => $target, 'old_path' => '/trip/' . $trip['slug'] . '/'];
            }
            Db::run('DELETE FROM trips WHERE id = ?', [$id]);
            Audit::log('delete', 'trip', $id, 'حذف الرحلة', $details);
            return null;
        });
        if ($result) {
            http_response_code(422);
            return $this->view('trips/delete', $result + ['title' => 'حذف الرحلة', 'errors' => ['اختر وجهة التحويل.'], 'target' => $target]);
        }
        return $this->redirect('/trips', 'تم حذف الرحلة.');
    }

    private function form(array $d, array $errors = [], int $status = 200): string
    {
        http_response_code($status);
        $photos = [];
        foreach (array_unique([...$d['gallery'], $d['image_id']]) as $id) {
            if ($id && ($photo = Db::one('SELECT * FROM media WHERE id = ?', [$id]))) $photos[(int) $id] = $photo;
        }
        return $this->view('trips/form', ['title' => $d['id'] ? 'تعديل رحلة' : 'إضافة رحلة', 'd' => $d, 'errors' => $errors, 'photos' => $photos, 'terms' => Db::all('SELECT * FROM terms ORDER BY taxonomy, sort_order, name')]);
    }
}
