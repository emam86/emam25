<?php
declare(strict_types=1);

namespace Bnc\Controller;

use Bnc\Auth;
use Bnc\HttpException;
use Bnc\Redirect;
use Bnc\Session;
use Bnc\View;

abstract class Controller
{
    protected function view(string $name, array $vars = []): string
    {
        return View::render($name, $vars);
    }

    protected function redirect(string $path, string $flash = '', string $type = 'ok'): Redirect
    {
        if ($flash !== '') Session::flash($type, $flash);
        return new Redirect($path);
    }

    protected function user(): array
    {
        return Auth::user() ?? throw new HttpException(403);
    }

    protected function notFound(): never
    {
        throw new HttpException(404);
    }

    protected function forbidden(string $message = ''): never
    {
        throw new HttpException(403, $message);
    }
}
