<?php
declare(strict_types=1);

namespace Bnc;

final class Roles
{
    public static function find(int $id): ?array
    {
        $role = Db::one('SELECT * FROM roles WHERE id = ?', [$id]);
        return $role ? self::decode($role) : null;
    }

    public static function bySlug(string $slug): ?array
    {
        $role = Db::one('SELECT * FROM roles WHERE slug = ?', [$slug]);
        return $role ? self::decode($role) : null;
    }

    /** @return list<array> roles with a user count */
    public static function all(): array
    {
        $rows = Db::all('SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS user_count
                         FROM roles r ORDER BY r.is_system DESC, r.id');
        return array_map([self::class, 'decode'], $rows);
    }

    public static function isOwner(array $role): bool
    {
        return $role['slug'] === 'owner';
    }

    /** Permissions a role grants; the owner role grants all of them. */
    public static function permissionsOf(array $role): array
    {
        return self::isOwner($role) ? array_keys(Permissions::all()) : $role['permissions'];
    }

    public static function seedPresets(): void
    {
        foreach (Permissions::PRESETS as $slug => [$name, $perms]) {
            if (self::bySlug($slug)) continue;
            Db::insert('roles', [
                'slug' => $slug,
                'name' => $name,
                'permissions' => json_encode($perms),
                'is_system' => $slug === 'owner' ? 1 : 0,
            ]);
        }
    }

    private static function decode(array $role): array
    {
        $role['permissions'] = json_decode((string) $role['permissions'], true) ?: [];
        return $role;
    }
}
