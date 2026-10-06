<?php
declare(strict_types=1);

namespace Bnc\Media;

use Bnc\Config;

final class Files
{
    public static function sizes(array $media): array
    {
        $sizes = json_decode((string) ($media['sizes'] ?? '{}'), true);
        return is_array($sizes) ? $sizes : [];
    }

    public static function url(string $path): string
    {
        return rtrim((string) Config::get('images_url', '/images'), '/') . substr($path, strlen('/images'));
    }

    public static function thumb(array $media): string
    {
        $size = self::sizes($media)['medium'] ?? null;
        return is_array($size) && isset($size['file']) && basename($size['file']) === $size['file']
            ? dirname($media['path']) . '/' . $size['file']
            : $media['path'];
    }

    /** Resolve only existing files contained in the configured images folder. */
    public static function paths(array $media): array
    {
        $root = realpath((string) Config::get('images_dir'));
        if ($root === false || !str_starts_with($media['path'], '/images/')) return [];
        $paths = [$media['path']];
        foreach (self::sizes($media) as $size) {
            if (is_array($size) && isset($size['file']) && is_string($size['file']) && basename($size['file']) === $size['file']) {
                $paths[] = dirname($media['path']) . '/' . $size['file'];
            }
        }
        $files = [];
        foreach ($paths as $path) {
            $file = realpath($root . substr($path, strlen('/images')));
            if ($file !== false && str_starts_with($file, $root . DIRECTORY_SEPARATOR) && is_file($file)) $files[] = $file;
        }
        return array_values(array_unique($files));
    }
}
