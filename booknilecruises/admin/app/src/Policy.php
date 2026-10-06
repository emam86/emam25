<?php
declare(strict_types=1);

namespace Bnc;

/**
 * Who may hand out which access. Nobody can give (or take over) more than they have:
 * only owners touch the owner role and owner accounts, and a non-owner can assign a role
 * only if every permission in it is one they hold themselves.
 */
final class Policy
{
    public static function canAssignRole(array $actor, array $role): bool
    {
        if ($actor['is_owner']) return true;
        if (Roles::isOwner($role)) return false;
        return !array_diff($role['permissions'], $actor['permissions']);
    }

    public static function canManageUser(array $actor, array $target): bool
    {
        if ($actor['is_owner']) return true;
        $role = Roles::find((int) $target['role_id']);
        return $role !== null && self::canAssignRole($actor, $role);
    }

    /** Roles the actor may assign, for drop-downs. */
    public static function assignableRoles(array $actor): array
    {
        return array_values(array_filter(Roles::all(), fn ($r) => self::canAssignRole($actor, $r)));
    }

    public static function activeOwnerCount(): int
    {
        return (int) Db::value("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'owner' AND u.is_active = 1");
    }
}
