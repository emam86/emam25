<?php
declare(strict_types=1);

namespace Bnc;

/** Call inside the content transaction. Keep automatic redirects flat. */
final class Redirects
{
    /** Match REDIRECT_TARGET in the public site's export validator. */
    public static function validTarget(string $target): bool
    {
        return strlen($target) <= 255 && (bool) preg_match('#^(?:/(?:[A-Za-z0-9._~%-]+/)*[A-Za-z0-9._~%-]*|https://[A-Za-z0-9.-]+(?:/[A-Za-z0-9._~%/-]*)?)(?:[?\#][A-Za-z0-9._~%=&/-]*)?$#D', $target);
    }

    public static function resolve(string $from, string $to, int $id = 0): string
    {
        if (!SitePages::validPath($from) || $from === '/' || preg_match('#^/(?:admin|api|images)(?:/|$)#', $from)) throw new \DomainException('مسار المصدر غير صالح أو محجوز.');
        if (!self::validTarget($to) || $from === $to) throw new \DomainException('وجهة التحويل غير صالحة.');
        if (Db::value('SELECT id FROM redirects WHERE from_path = ? AND id <> ?', [$from, $id])) throw new \DomainException('مسار المصدر مستخدم بالفعل.');
        $seen = [$from => true];
        while (true) {
            // Query strings and fragments do not change the path Apache redirects.
            $path = str_starts_with($to, '/') ? preg_split('/[?#]/', $to, 2)[0] : $to;
            if (isset($seen[$path])) throw new \DomainException('التحويل يؤدي إلى حلقة.');
            $seen[$path] = true;
            $next = Db::value('SELECT to_path FROM redirects WHERE from_path = ? AND id <> ?', [$path, $id]);
            if (!$next) return $to;
            $to = $next;
        }
    }

    public static function save(string $from, string $to, int $id = 0): string
    {
        return Db::tx(function () use ($from, $to, $id): string {
            Db::all('SELECT id FROM redirects ORDER BY id FOR UPDATE');
            $old = $id ? Db::one('SELECT * FROM redirects WHERE id = ?', [$id]) : null;
            if ($id && !$old) throw new HttpException(404);
            $resolved = self::resolve($from, $to, $id);
            $row = ['from_path' => $from, 'to_path' => $resolved];
            if ($old) Db::update('redirects', $row, 'id = ?', [$id]);
            else $id = Db::insert('redirects', $row + ['source' => 'manual']);
            Audit::log($old ? 'update' : 'create', 'redirect', $id, 'حفظ التحويل', ['old' => $old, 'new' => $row]);
            return $resolved;
        });
    }

    public static function moved(string $oldPath, string $newPath): void
    {
        if ($oldPath === $newPath) return;
        Db::run('DELETE FROM redirects WHERE from_path = ?', [$newPath]);
        Db::run('UPDATE redirects SET to_path = ? WHERE to_path = ?', [$newPath, $oldPath]);
        Db::run("INSERT INTO redirects (from_path, to_path, source) VALUES (?, ?, 'auto') ON DUPLICATE KEY UPDATE to_path = VALUES(to_path), source = 'auto'", [$oldPath, $newPath]);
        Db::run('DELETE FROM redirects WHERE from_path = to_path');
    }
}
