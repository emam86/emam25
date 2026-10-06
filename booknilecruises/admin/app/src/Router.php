<?php
declare(strict_types=1);

namespace Bnc;

/**
 * Tiny router. Patterns use {id} for a numeric segment and {slug} for [a-z0-9-]+.
 * Each route names the permission it needs (null = signed-in user, 'guest' = public).
 */
final class Router
{
    private array $routes = [];

    public function get(string $pattern, callable|array $handler, ?string $permission = null): void
    {
        $this->add('GET', $pattern, $handler, $permission);
    }

    public function post(string $pattern, callable|array $handler, ?string $permission = null): void
    {
        $this->add('POST', $pattern, $handler, $permission);
    }

    public function add(string $method, string $pattern, callable|array $handler, ?string $permission): void
    {
        $regex = preg_replace(['#\{id\}#', '#\{slug\}#'], ['(\d+)', '([a-z0-9-]+)'], rtrim($pattern, '/') ?: '/');
        $this->routes[] = [$method, '#^' . $regex . '$#', $handler, $permission];
    }

    /** @return array{0:callable|array,1:?string,2:list<string>}|null handler, permission, params */
    public function match(string $method, string $path): ?array
    {
        $path = rtrim($path, '/') ?: '/';
        $allowed = false;
        foreach ($this->routes as [$m, $regex, $handler, $perm]) {
            if (!preg_match($regex, $path, $mm)) continue;
            if ($m !== $method) { $allowed = true; continue; }
            return [$handler, $perm, array_slice($mm, 1)];
        }
        if ($allowed) throw new HttpException(405, 'Method not allowed');
        return null;
    }
}
