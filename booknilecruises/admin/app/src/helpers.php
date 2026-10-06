<?php
declare(strict_types=1);

/** HTML-escape a value for output. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL inside the admin panel, e.g. url('/users/3/edit'). */
function url(string $path = '/', array $query = []): string
{
    $base = rtrim((string) \Bnc\Config::get('admin_path', '/admin'), '/');
    $u = $base . '/' . ltrim($path, '/');
    return $query ? $u . '?' . http_build_query($query) : $u;
}

function can(string $permission): bool
{
    return \Bnc\Auth::can($permission);
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(\Bnc\Csrf::token()) . '">';
}
