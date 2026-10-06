<?php
declare(strict_types=1);

namespace Bnc\Controller;

use Bnc\Audit;
use Bnc\Db;
use Bnc\Permissions;
use Bnc\Redirect;
use Bnc\Request;
use Bnc\Roles;

final class RolesController extends Controller
{
    public function index(): string
    {
        return $this->view('roles/index', ['title' => 'الأدوار والصلاحيات', 'roles' => Roles::all()]);
    }

    public function create(): string
    {
        return $this->form(['id' => null, 'name' => '', 'slug' => '', 'permissions' => [], 'is_system' => 0], []);
    }

    public function store(): string|Redirect
    {
        $name = Request::str('name');
        $perms = $this->grantable([], Request::list('permissions'));
        $errors = $this->validate($name, null);
        if ($errors) return $this->form(['id' => null, 'name' => $name, 'slug' => '', 'permissions' => $perms, 'is_system' => 0], $errors);
        $id = Db::insert('roles', [
            'slug' => 'role-' . bin2hex(random_bytes(4)),
            'name' => $name,
            'permissions' => json_encode($perms),
        ]);
        Audit::log('create', 'role', $id, "أضاف الدور {$name}", ['permissions' => $perms]);
        return $this->redirect('/roles', 'تمت إضافة الدور.');
    }

    public function edit(int $id): string
    {
        return $this->form($this->findEditable($id), []);
    }

    public function update(int $id): string|Redirect
    {
        $role = $this->findEditable($id);
        $name = Request::str('name');
        $perms = $this->grantable($role['permissions'], Request::list('permissions'));
        $errors = $this->validate($name, $id);
        if ($errors) return $this->form(['name' => $name, 'permissions' => $perms] + $role, $errors);
        Db::update('roles', ['name' => $name, 'permissions' => json_encode($perms)], 'id = ?', [$id]);
        Audit::log('update', 'role', $id, "عدّل الدور {$name}", [
            'added' => array_values(array_diff($perms, $role['permissions'])),
            'removed' => array_values(array_diff($role['permissions'], $perms)),
        ]);
        return $this->redirect('/roles', 'تم حفظ الدور.');
    }

    public function delete(int $id): Redirect
    {
        $role = $this->findEditable($id);
        if ((int) Db::value('SELECT COUNT(*) FROM users WHERE role_id = ?', [$id]) > 0) {
            return $this->redirect('/roles', 'انقل مستخدمي هذا الدور لدور آخر قبل حذفه.', 'error');
        }
        Db::run('DELETE FROM roles WHERE id = ?', [$id]);
        Audit::log('delete', 'role', $id, "حذف الدور {$role['name']}");
        return $this->redirect('/roles', 'تم حذف الدور.');
    }

    /**
     * The new permission list: what the actor ticked, limited to what the actor holds,
     * plus anything the role already had that the actor can't see or change.
     */
    private function grantable(array $current, array $submitted): array
    {
        $mine = $this->user()['permissions'];
        $chosen = array_intersect(Permissions::clean($submitted), $mine);
        $untouchable = array_diff($current, $mine);
        return Permissions::clean([...$chosen, ...$untouchable]);
    }

    private function validate(string $name, ?int $id): array
    {
        $errors = [];
        if ($name === '' || mb_strlen($name) > 100) $errors[] = 'اكتب اسم الدور (حتى 100 حرف).';
        elseif (Db::value('SELECT id FROM roles WHERE name = ? AND id <> ?', [$name, $id ?? 0])) $errors[] = 'يوجد دور بنفس الاسم.';
        return $errors;
    }

    private function findEditable(int $id): array
    {
        $role = Roles::find($id) ?? $this->notFound();
        if (Roles::isOwner($role)) $this->forbidden('دور المالك ثابت وله كل الصلاحيات.');
        $mine = $this->user();
        // A non-owner can't edit a role that holds more than they do (it would let them strip it).
        if (!$mine['is_owner'] && array_diff($role['permissions'], $mine['permissions'])) {
            $this->forbidden('هذا الدور فيه صلاحيات أعلى من صلاحياتك.');
        }
        return $role;
    }

    private function form(array $role, array $errors): string
    {
        if ($errors) http_response_code(422);
        return $this->view('roles/form', [
            'title' => $role['id'] ? 'تعديل دور' : 'إضافة دور',
            'role' => $role,
            'groups' => Permissions::GROUPS,
            'mine' => $this->user()['permissions'],
            'errors' => $errors,
        ]);
    }
}
