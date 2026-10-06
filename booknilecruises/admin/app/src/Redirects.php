<?php
declare(strict_types=1);

namespace Bnc;

/** Call inside the content transaction. Keep automatic redirects flat. */
final class Redirects
{
    public static function moved(string $oldPath, string $newPath): void
    {
        if ($oldPath === $newPath) return;
        Db::run('DELETE FROM redirects WHERE from_path = ?', [$newPath]);
        Db::run('UPDATE redirects SET to_path = ? WHERE to_path = ?', [$newPath, $oldPath]);
        Db::run("INSERT INTO redirects (from_path, to_path, source) VALUES (?, ?, 'auto') ON DUPLICATE KEY UPDATE to_path = VALUES(to_path), source = 'auto'", [$oldPath, $newPath]);
        Db::run('DELETE FROM redirects WHERE from_path = to_path');
    }
}
