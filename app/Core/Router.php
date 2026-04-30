<?php

declare(strict_types=1);

namespace BarePitch\Core;

class Router
{
    /** @var array<int, array{0: string, 1: string, 2: callable}> */
    private static array $routes = [];

    public static function get(string $pattern, callable $handler): void
    {
        self::$routes[] = ['GET', $pattern, $handler];
    }

    public static function post(string $pattern, callable $handler): void
    {
        self::$routes[] = ['POST', $pattern, $handler];
    }

    public static function dispatch(): void
    {
        $request = new Request();
        $method  = $request->method();
        $path    = $request->path();

        foreach (self::$routes as [$routeMethod, $pattern, $handler]) {
            if ($routeMethod !== $method) {
                continue;
            }

            $regex = self::patternToRegex($pattern);

            if (preg_match($regex, $path, $matches) === 1) {
                // Extract only named string captures (filter out numeric keys)
                $params = array_filter(
                    $matches,
                    static fn($key) => is_string($key),
                    ARRAY_FILTER_USE_KEY
                );

                $handler($request, $params);
                return;
            }
        }

        Response::abort(404);
    }

    private static function patternToRegex(string $pattern): string
    {
        $segments = explode('/', $pattern);

        $regexSegments = array_map(static function (string $segment): string {
            if (str_starts_with($segment, '{') && str_ends_with($segment, '}')) {
                $name = substr($segment, 1, -1);
                return '(?P<' . $name . '>[^/]+)';
            }

            return preg_quote($segment, '#');
        }, $segments);

        return '#^' . implode('/', $regexSegments) . '$#';
    }
}
