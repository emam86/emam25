<?php
declare(strict_types=1);

namespace Bnc\Controller;

use Bnc\Config;
use Bnc\Content\Importer;
use Bnc\Redirect;

/** Owner-only, one-time import of the current site (bnc-app/seed/export.json) into an empty panel. */
final class ImportController extends Controller
{
    public function form(): string|Redirect
    {
        $this->ownerOnly();
        $seed = self::seed();
        try { $existing = Importer::blockers(); }
        catch (\PDOException) { return $this->redirect('/', 'تعذر قراءة بيانات الاستيراد. حاول مرة أخرى.', 'error'); }
        return $this->view('import', [
            'title' => 'استيراد محتوى الموقع الحالي',
            'seed' => $seed ? array_map('count', array_intersect_key($seed, array_flip(['trips', 'terms', 'media', 'posts']))) : null,
            'existing' => $existing,
        ]);
    }

    public function run(): Redirect
    {
        $this->ownerOnly();
        $seed = self::seed();
        if (!$seed) return $this->redirect('/import', 'ملف الاستيراد غير موجود.', 'error');
        try {
            $counts = Importer::run($seed);
        } catch (\PDOException $e) {
            return $this->redirect('/import', 'تعذر استيراد البيانات. تحقق من الملف وحاول مرة أخرى.', 'error');
        } catch (\RuntimeException $e) {
            return $this->redirect('/import', $e->getMessage(), 'error');
        }
        return $this->redirect('/trips', "تم الاستيراد: {$counts['trips']} رحلة، {$counts['terms']} تصنيف، {$counts['media']} صورة، {$counts['posts']} مقال.");
    }

    private function ownerOnly(): void
    {
        if (!$this->user()['is_owner']) $this->forbidden('الاستيراد متاح للمالك فقط.');
    }

    private static function seed(): ?array
    {
        $file = (string) Config::get('seed_file', BNC_APP . '/seed/export.json');
        if (!is_file($file)) return null;
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

}
