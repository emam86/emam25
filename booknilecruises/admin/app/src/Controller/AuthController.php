<?php
declare(strict_types=1);

namespace Bnc\Controller;

use Bnc\Auth;
use Bnc\Redirect;
use Bnc\Request;
use Bnc\View;

final class AuthController extends Controller
{
    public function form(): string|Redirect
    {
        if (Auth::user()) return new Redirect('/');
        return View::render('auth/login', ['error' => null, 'email' => '', 'next' => self::safeNext(Request::str('next', '', true))], 'partials/bare');
    }

    public function login(): string|Redirect
    {
        $email = Request::str('email');
        $next = self::safeNext(Request::str('next'));
        $error = Auth::attempt($email, (string) ($_POST['password'] ?? ''), Request::ip());
        if ($error === null) return new Redirect($next ?: '/');
        http_response_code(422);
        return View::render('auth/login', ['error' => $error, 'email' => $email, 'next' => $next], 'partials/bare');
    }

    public function logout(): Redirect
    {
        Auth::logout();
        return new Redirect('/login');
    }

    /** Only paths inside the panel; never another host. */
    private static function safeNext(string $next): string
    {
        return preg_match('#^/[A-Za-z0-9/_-]*$#', $next) ? $next : '';
    }
}
