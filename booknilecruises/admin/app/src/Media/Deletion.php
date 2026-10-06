<?php
declare(strict_types=1);

namespace Bnc\Media;

use Bnc\Audit;
use Bnc\Db;
use RuntimeException;

final class Deletion
{
    public static function delete(array $media): void
    {
        $staged = [];
        try {
            Db::tx(function () use ($media, &$staged): void {
                Db::one('SELECT id FROM media WHERE id = ? FOR UPDATE', [$media['id']]);
                $uses = Usage::of($media);
                if ($uses) throw new RuntimeException('لا يمكن حذف الصورة لأنها مستخدمة في: ' . implode('، ', array_column($uses, 'title')) . '.');
                // Keep recoverable files until the database and audit transaction commits.
                foreach (Files::paths($media) as $file) {
                    $backup = dirname($file) . '/.delete-' . bin2hex(random_bytes(16));
                    if (!@rename($file, $backup)) throw new RuntimeException('تعذر حذف ملفات الصورة. حاول مرة أخرى.');
                    $staged[$file] = $backup;
                }
                Db::run('DELETE FROM media WHERE id = ?', [$media['id']]);
                Audit::log('delete', 'media', $media['id'], 'حذف الصورة ' . $media['path']);
            });
        } catch (\Throwable $e) {
            foreach ($staged as $file => $backup) rename($backup, $file);
            throw $e;
        }
        foreach ($staged as $backup) {
            if (!unlink($backup)) throw new RuntimeException('تعذر إزالة أحد ملفات الصورة.');
        }
    }
}
