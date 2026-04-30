<?php

declare(strict_types=1);

namespace BarePitch\Core;

use BarePitch\Core\Session;

class Response
{
    public static function redirect(string $url, int $code = 302): never
    {
        header("Location: {$url}", true, $code);
        exit();
    }

    public static function json(array $data, int $status = 200): never
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
        echo json_encode($data);
        exit();
    }

    public static function abort(int $code, string $message = ''): never
    {
        http_response_code($code);

        $templatePath = View::basePath() . '/app/Views/errors/' . $code . '.php';

        if (file_exists($templatePath)) {
            echo View::render("errors/{$code}", ['message' => $message]);
        }

        exit();
    }

    public static function setFlash(string $key, string $message): void
    {
        Session::flash($key, $message);
    }

    public static function getFlash(string $key): ?string
    {
        return Session::getFlash($key);
    }
}
