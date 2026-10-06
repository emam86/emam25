<?php
declare(strict_types=1);

namespace Bnc\Controller;

use Bnc\Audit;
use Bnc\Auth;
use Bnc\Db;
use Bnc\Policy;
use Bnc\Redirect;
use Bnc\Request;
use Bnc\Roles;

final class UsersController extends Controller
{
    public function index(): string
    {
        $users = Db::all('SELECT u.id, u.name, u.email, u.is_active, u.last_login_at, u.role_id, r.name AS role_name, r.slug AS role_slug
                          FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.id');
        $me = $this->user();
        foreach ($users as &$u) $u['manageable'] = Policy::canManageUser($me, $u);
        return $this->view('users/index', ['title' => 'المستخدمين', 'users' => $users]);
    }

    public function create(): string
    {
        return $this->form(['id' => null, 'name' => '', 'email' => '', 'role_id' => 0, 'is_active' => 1], []);
    }

    public function store(): string|Redirect
    {
        $data = $this->input();
        $errors = $this->validate($data, null);
        if ($errors) return $this->form($data, $errors);
        $id = Db::insert('users', [
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role_id' => $data['role_id'],
            'is_active' => $data['is_active'],
        ]);
        Audit::log('create', 'user', $id, "أضاف المستخدم {$data['email']}", ['role_id' => $data['role_id']]);
        return $this->redirect('/users', 'تمت إضافة المستخدم.');
    }

    public function edit(int $id): string
    {
        $user = $this->findManageable($id);
        return $this->form($user, []);
    }

    public function update(int $id): string|Redirect
    {
        $user = $this->findManageable($id);
        $data = $this->input() + ['id' => $id];
        $errors = $this->validate($data, $user);
        if ($errors) return $this->form($data, $errors);
        $row = ['name' => $data['name'], 'email' => $data['email'], 'role_id' => $data['role_id'], 'is_active' => $data['is_active']];
        if ($data['password'] !== '') $row['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        Db::update('users', $row, 'id = ?', [$id]);
        $changes = array_keys(array_diff_assoc(
            ['name' => $row['name'], 'email' => $row['email'], 'role_id' => (string) $row['role_id'], 'is_active' => (string) $row['is_active']],
            ['name' => $user['name'], 'email' => $user['email'], 'role_id' => (string) $user['role_id'], 'is_active' => (string) $user['is_active']]
        ));
        if ($data['password'] !== '') $changes[] = 'password';
        Audit::log('update', 'user', $id, "عدّل المستخدم {$data['email']}", ['changed' => $changes]);
        return $this->redirect('/users', 'تم حفظ المستخدم.');
    }

    public function delete(int $id): Redirect
    {
        $user = $this->findManageable($id);
        if ($id === (int) $this->user()['id']) return $this->redirect('/users', 'لا يمكنك حذف حسابك.', 'error');
        if ($this->isLastActiveOwner($user)) return $this->redirect('/users', 'لا يمكن حذف آخر مالك.', 'error');
        Db::run('DELETE FROM users WHERE id = ?', [$id]);
        Audit::log('delete', 'user', $id, "حذف المستخدم {$user['email']}");
        return $this->redirect('/users', 'تم حذف المستخدم.');
    }

    private function input(): array
    {
        return [
            'name' => Request::str('name'),
            'email' => mb_strtolower(Request::str('email')),
            'password' => (string) ($_POST['password'] ?? ''),
            'role_id' => Request::int('role_id'),
            'is_active' => Request::bool('is_active') ? 1 : 0,
        ];
    }

    /** @return list<string> */
    private function validate(array $d, ?array $existing): array
    {
        $me = $this->user();
        $errors = [];
        if ($d['name'] === '' || mb_strlen($d['name']) > 120) $errors[] = 'اكتب الاسم (حتى 120 حرف).';
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($d['email']) > 190) {
            $errors[] = 'الإيميل غير صحيح.';
        } elseif (Db::value('SELECT id FROM users WHERE email = ? AND id <> ?', [$d['email'], $existing['id'] ?? 0])) {
            $errors[] = 'الإيميل مستخدم بالفعل.';
        }
        if ($existing === null || $d['password'] !== '') {
            if ($p = Auth::passwordProblem($d['password'])) $errors[] = $p;
        }
        $role = Roles::find($d['role_id']);
        if (!$role) {
            $errors[] = 'اختر دورًا.';
        } elseif (!Policy::canAssignRole($me, $role)) {
            $errors[] = 'لا يمكنك إعطاء دور فيه صلاحيات أكثر من صلاحياتك.';
        }
        if ($existing !== null && (int) $existing['id'] === (int) $me['id']) {
            if ((int) $d['role_id'] !== (int) $existing['role_id']) $errors[] = 'لا يمكنك تغيير دورك بنفسك.';
            if (!$d['is_active']) $errors[] = 'لا يمكنك إيقاف حسابك.';
        }
        if ($existing !== null && $this->isLastActiveOwner($existing) && $role && (!Roles::isOwner($role) || !$d['is_active'])) {
            $errors[] = 'لازم يفضل مالك واحد نشط على الأقل.';
        }
        return $errors;
    }

    private function isLastActiveOwner(array $user): bool
    {
        $role = Roles::find((int) $user['role_id']);
        return $role && Roles::isOwner($role) && (int) $user['is_active'] === 1 && Policy::activeOwnerCount() <= 1;
    }

    private function findManageable(int $id): array
    {
        $user = Db::one('SELECT id, name, email, role_id, is_active FROM users WHERE id = ?', [$id]) ?? $this->notFound();
        if (!Policy::canManageUser($this->user(), $user)) $this->forbidden('هذا المستخدم عنده صلاحيات أعلى من صلاحياتك.');
        return $user;
    }

    private function form(array $user, array $errors): string
    {
        if ($errors) http_response_code(422);
        return $this->view('users/form', [
            'title' => $user['id'] ? 'تعديل مستخدم' : 'إضافة مستخدم',
            'u' => $user,
            'roles' => Policy::assignableRoles($this->user()),
            'errors' => $errors,
        ]);
    }
}
