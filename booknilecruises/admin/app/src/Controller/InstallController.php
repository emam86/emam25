<?php
declare(strict_types=1);

namespace Bnc\Controller;

use Bnc\App;
use Bnc\Audit;
use Bnc\Auth;
use Bnc\Config;
use Bnc\Db;
use Bnc\Migrator;
use Bnc\Redirect;
use Bnc\Request;
use Bnc\Roles;
use Bnc\Session;
use Bnc\View;

/**
 * One-time web installer: creates the tables and the first owner account.
 * Works only while no user exists and a long install_token is set in bnc-config.php.
 */
final class InstallController extends Controller
{
    public function form(): string|Redirect
    {
        if (App::installed()) return new Redirect('/login');
        return $this->page([]);
    }

    public function install(): string|Redirect
    {
        if (App::installed()) return new Redirect('/login');
        $configured = (string) Config::get('install_token', '');
        $errors = [];
        if (strlen($configured) < 24) {
            $errors[] = 'ضع install_token طويل (24 حرف على الأقل) في bnc-config.php أولًا.';
        } elseif (!hash_equals($configured, (string) ($_POST['token'] ?? ''))) {
            $errors[] = 'رمز التثبيت غير صحيح.';
        }
        $name = Request::str('name');
        $email = mb_strtolower(Request::str('email'));
        $password = (string) ($_POST['password'] ?? '');
        if ($name === '') $errors[] = 'اكتب الاسم.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'الإيميل غير صحيح.';
        if ($p = Auth::passwordProblem($password)) $errors[] = $p;
        if ($errors) {
            http_response_code(422);
            return $this->page($errors, $name, $email);
        }

        Migrator::run();
        if (App::installed()) return new Redirect('/login'); // someone finished first
        $owner = Roles::bySlug('owner');
        $id = Db::insert('users', [
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role_id' => $owner['id'],
        ]);
        Audit::log('install', 'user', $id, 'تثبيت اللوحة وإنشاء حساب المالك', null, $email);
        Session::flash('ok', 'تم التثبيت. سجّل دخولك، وبعدها امسح install_token من bnc-config.php.');
        return new Redirect('/login');
    }

    private function page(array $errors, string $name = '', string $email = ''): string
    {
        return View::render('auth/install', [
            'errors' => $errors,
            'name' => $name,
            'email' => $email,
            'tokenSet' => strlen((string) Config::get('install_token', '')) >= 24,
        ], 'partials/bare');
    }
}
