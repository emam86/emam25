<?php
declare(strict_types=1);

namespace Bnc;

final class View
{
    /** Render views/<name>.php inside the panel layout (or bare when $layout is null). */
    public static function render(string $name, array $vars = [], ?string $layout = 'partials/layout'): string
    {
        $content = self::partial($name, $vars);
        if ($layout === null) return $content;
        return self::partial($layout, $vars + ['content' => $content]);
    }

    public static function partial(string $name, array $vars = []): string
    {
        $file = BNC_APP . '/views/' . $name . '.php';
        if (!preg_match('#^[a-z0-9_/-]+$#', $name) || !is_file($file)) {
            throw new \RuntimeException("View not found: $name");
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        try {
            require $file;
            return (string) ob_get_clean();
        } catch (\Throwable $t) {
            ob_end_clean();
            throw $t;
        }
    }
}
