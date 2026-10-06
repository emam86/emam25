<?php
declare(strict_types=1);

namespace Bnc\Media;

use Bnc\Audit;
use Bnc\Config;
use Bnc\Db;
use GdImage;
use RuntimeException;

final class Uploader
{
    private const TYPES = [IMAGETYPE_JPEG => ['jpg', 'image/jpeg'], IMAGETYPE_PNG => ['png', 'image/png'], IMAGETYPE_WEBP => ['webp', 'image/webp'], IMAGETYPE_GIF => ['gif', 'image/gif']];
    private const SIZES = ['medium' => [300, 300], 'medium_large' => [768, 0], 'large' => [1024, 1024], '1536x1536' => [1536, 1536]];

    public function save(array $file, int $userId): int
    {
        [$tmp, $type] = $this->validate($file);
        // A large photo is decoded into several full-size canvases (about 4 bytes per pixel each).
        self::raiseMemoryLimit(768 * 1024 * 1024);
        $image = $this->decode($tmp, $type);
        $written = [];
        try {
            if ($type === IMAGETYPE_JPEG) $image = $this->orient($image, $tmp);
            $main = $this->resize($image, 2560, 2560);
            if ($main !== $image) { imagedestroy($image); $image = $main; }
            [$ext, $mime] = self::TYPES[$type];
            $dir = $this->directory();
            $stem = $this->slug((string) ($file['name'] ?? 'image'));
            $dimensions = [];
            foreach (self::SIZES as $key => [$w, $h]) {
                [$w, $h] = $this->dimensions(imagesx($image), imagesy($image), $w, $h);
                if ($w !== imagesx($image) || $h !== imagesy($image)) $dimensions[$key] = [$w, $h];
            }
            $name = $this->reserve($dir, $stem, $ext, $dimensions, $written);
            $mainPath = "$dir/$name.$ext";
            $this->encode($image, $mainPath, $type);
            $sizes = [];
            foreach ($dimensions as $key => [$w, $h]) {
                $copy = $this->resize($image, $w, $h);
                try {
                    $filename = "$name-{$w}x{$h}.$ext";
                    $this->encode($copy, "$dir/$filename", $type);
                    $sizes[$key] = ['file' => $filename, 'width' => $w, 'height' => $h, 'mime_type' => $mime];
                } finally {
                    imagedestroy($copy);
                }
            }
            $path = '/images/' . date('Y/m') . "/$name.$ext";
            return Db::tx(function () use ($path, $image, $mime, $mainPath, $sizes, $userId): int {
                $id = Db::insert('media', [
                    'path' => $path, 'width' => imagesx($image), 'height' => imagesy($image),
                    'mime' => $mime, 'filesize' => filesize($mainPath), 'alt' => '',
                    'sizes' => json_encode((object) $sizes, JSON_THROW_ON_ERROR), 'uploaded_by' => $userId,
                ]);
                Audit::log('create', 'media', $id, 'رفع الصورة ' . $path);
                return $id;
            });
        } catch (\Throwable $e) {
            foreach ($written as $path) if (is_file($path)) unlink($path);
            if ($e instanceof RuntimeException && !$e instanceof \PDOException) throw $e;
            throw new RuntimeException('تعذر حفظ الصورة. حاول مرة أخرى.', 0, $e);
        } finally {
            imagedestroy($image);
        }
    }

    private static function raiseMemoryLimit(int $bytes): void
    {
        $limit = (string) ini_get('memory_limit');
        $current = $limit === '-1' ? PHP_INT_MAX : (int) $limit * match (strtoupper(substr($limit, -1))) { 'G' => 1 << 30, 'M' => 1 << 20, 'K' => 1 << 10, default => 1 };
        if ($current < $bytes) @ini_set('memory_limit', (string) $bytes);
    }

    private function validate(array $file): array
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        $max = (float) Config::get('max_upload_mb', 15);
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('حجم الصورة أكبر من الحد المسموح للرفع.');
        if ($error === UPLOAD_ERR_NO_FILE) throw new RuntimeException('اختر صورة للرفع.');
        if ($error !== UPLOAD_ERR_OK) throw new RuntimeException(match ($error) {
            UPLOAD_ERR_PARTIAL => 'لم يكتمل رفع الصورة. حاول مرة أخرى.',
            UPLOAD_ERR_NO_TMP_DIR => 'مجلد الرفع المؤقت غير متاح.',
            UPLOAD_ERR_CANT_WRITE => 'تعذر كتابة الملف المؤقت للصورة.',
            UPLOAD_ERR_EXTENSION => 'أوقف الخادم رفع الصورة.',
            default => 'فشل رفع الصورة. حاول مرة أخرى.',
        });
        $tmp = $file['tmp_name'] ?? '';
        if (!is_string($tmp) || !is_uploaded_file($tmp)) throw new RuntimeException('ملف الرفع غير صالح.');
        if (filesize($tmp) > $max * 1024 * 1024) throw new RuntimeException("حجم الصورة يتجاوز $max ميجابايت.");
        $info = @getimagesize($tmp);
        if (!$info || !isset(self::TYPES[$info[2]]) || (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) !== self::TYPES[$info[2]][1]) {
            throw new RuntimeException('اختر صورة JPEG أو PNG أو WebP أو GIF فقط.');
        }
        if ($info[0] * $info[1] > 40_000_000) throw new RuntimeException('الصورة أكبر من 40 ميجابكسل.');
        return [$tmp, $info[2]];
    }

    private function decode(string $path, int $type): GdImage
    {
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
        };
        if (!$image) throw new RuntimeException('تعذر قراءة الصورة. الملف تالف.');
        // A true-colour canvas preserves alpha from palette PNGs and GIFs too.
        $canvas = $this->canvas(imagesx($image), imagesy($image));
        imagecopy($canvas, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        imagedestroy($image);
        return $canvas;
    }

    private function orient(GdImage $image, string $path): GdImage
    {
        if (!function_exists('exif_read_data')) return $image;
        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        if (in_array($orientation, [2, 4, 5, 7], true)) imageflip($image, $orientation === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
        $angle = match ($orientation) { 3 => 180, 6, 7 => -90, 5, 8 => 90, default => 0 };
        if ($angle === 0) return $image;
        $rotated = imagerotate($image, $angle, 0);
        if (!$rotated) throw new RuntimeException('تعذر تدوير الصورة.');
        imagedestroy($image);
        return $rotated;
    }

    private function dimensions(int $width, int $height, int $maxWidth, int $maxHeight): array
    {
        $ratio = min(1, $maxWidth / $width, $maxHeight ? $maxHeight / $height : 1);
        return [max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio))];
    }

    private function resize(GdImage $image, int $maxWidth, int $maxHeight): GdImage
    {
        [$w, $h] = $this->dimensions(imagesx($image), imagesy($image), $maxWidth, $maxHeight);
        if ($w === imagesx($image) && $h === imagesy($image)) return $image;
        $copy = $this->canvas($w, $h);
        if (!imagecopyresampled($copy, $image, 0, 0, 0, 0, $w, $h, imagesx($image), imagesy($image))) {
            imagedestroy($copy);
            throw new RuntimeException('تعذر تغيير حجم الصورة.');
        }
        return $copy;
    }

    private function canvas(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        return $image;
    }

    private function encode(GdImage $image, string $path, int $type): void
    {
        if ($type === IMAGETYPE_GIF) {
            $copy = $this->canvas(imagesx($image), imagesy($image));
            imagecopy($copy, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            $image = $copy;
            // GIF supports one transparent palette colour, rather than full alpha.
            $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
            imagecolortransparent($image, $transparent);
        }
        try {
            $ok = match ($type) {
                IMAGETYPE_JPEG => imagejpeg($image, $path, 82),
                IMAGETYPE_PNG => imagepng($image, $path, 6),
                IMAGETYPE_WEBP => imagewebp($image, $path, 80),
                IMAGETYPE_GIF => imagegif($image, $path),
            };
            if (!$ok || !filesize($path)) throw new RuntimeException('تعذر كتابة ملف الصورة.');
        } finally {
            if ($type === IMAGETYPE_GIF) imagedestroy($image);
        }
    }

    private function directory(): string
    {
        $root = (string) Config::get('images_dir');
        if ($root === '' || $root[0] !== '/') throw new RuntimeException('مجلد الصور غير صالح.');
        if (!is_dir($root) && !@mkdir($root, 0755, true)) throw new RuntimeException('تعذر إنشاء مجلد الصور.');
        $root = realpath($root);
        $dir = $root . '/' . date('Y/m');
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) throw new RuntimeException('تعذر إنشاء مجلد الصور.');
        $real = realpath($dir);
        if ($real === false || !str_starts_with($real, $root . '/')) throw new RuntimeException('مجلد الصور غير صالح.');
        return $real;
    }

    private function slug(string $filename): string
    {
        $stem = pathinfo(basename(str_replace('\\', '/', $filename)), PATHINFO_FILENAME);
        $stem = mb_strtolower($stem);
        if (function_exists('transliterator_transliterate')) $stem = transliterator_transliterate('Any-Latin; Latin-ASCII', $stem) ?: $stem;
        else $stem = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $stem) ?: $stem;
        $stem = preg_replace('/[^a-z0-9-]+/', '-', strtolower($stem));
        $stem = preg_replace('/-+/', '-', $stem);
        return trim(substr(trim($stem, '-'), 0, 80), '-') ?: 'image';
    }

    private function reserve(string $dir, string $stem, string $ext, array $dimensions, array &$written): string
    {
        for ($i = 0; ; $i++) {
            $suffix = $i ? "-$i" : '';
            $name = rtrim(substr($stem, 0, 80 - strlen($suffix)), '-') . $suffix;
            $paths = ["$dir/$name.$ext"];
            foreach ($dimensions as [$w, $h]) $paths[] = "$dir/$name-{$w}x{$h}.$ext";
            $paths = array_unique($paths);
            $reserved = [];
            foreach ($paths as $path) {
                $handle = @fopen($path, 'x');
                if ($handle === false) {
                    foreach ($reserved as $created) unlink($created);
                    if (file_exists($path) || is_link($path)) continue 2;
                    throw new RuntimeException('تعذر كتابة ملف الصورة. تحقق من صلاحيات مجلد الصور.');
                }
                fclose($handle);
                $reserved[] = $path;
            }
            $written = $reserved;
            return $name;
        }
    }
}
