<?php
declare(strict_types=1);

namespace Bnc\Controller;

use Bnc\Audit;
use Bnc\Auth;
use Bnc\Db;
use Bnc\Redirect;
use Bnc\Request;

final class AccountController extends Controller
{
    public function form(array $errors = []): string
    {
        return $this->view('account', ['title' => 'حسابي', 'me' => $this->user(), 'errors' => $errors]);
    }

    public function save(): string|Redirect
    {
        $me = $this->user();
        $name = Request::str('name');
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $errors = [];
        if ($name === '') $errors[] = 'اكتب الاسم.';
        $hash = (string) Db::value('SELECT password_hash FROM users WHERE id = ?', [$me['id']]);
        if ($new !== '') {
            if (!password_verify($current, $hash)) $errors[] = 'كلمة السر الحالية غير صحيحة.';
            if ($p = Auth::passwordProblem($new)) $errors[] = $p;
        }
        if ($errors) {
            http_response_code(422);
            return $this->form($errors);
        }
        $data = ['name' => $name];
        if ($new !== '') $data['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
        Db::update('users', $data, 'id = ?', [$me['id']]);
        if ($new !== '') Auth::refreshSession($data['password_hash']);
        Audit::log('update', 'user', $me['id'], $new !== '' ? 'غيّر كلمة السر' : 'عدّل بياناته');
        return $this->redirect('/account', 'تم الحفظ.');
    }
}
