<?php

declare(strict_types=1);

namespace BarePitch\Core;

use BarePitch\Core\Exceptions\NotFoundException;

class View
{
    public static function render(string $template, array $data = []): string
    {
        $path = self::basePath() . '/app/Views/' . $template . '.php';

        if (!file_exists($path)) {
            throw new NotFoundException("View template not found: {$template}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $path;

        return (string) ob_get_clean();
    }

    public static function layout(string $template, array $data = [], string $layout = 'app'): string
    {
        $content = self::render($template, $data);

        $data['content'] = $content;

        return self::render("layouts/{$layout}", $data);
    }

    public static function basePath(): string
    {
        return defined('BASE_PATH') ? BASE_PATH : dirname(__FILE__, 3);
    }
}
