<?php

declare(strict_types=1);

namespace Ancc\Http;

final class Router
{
    /** @var list<array{method: string, regex: string, handler: callable(Request): Response}> */
    private array $routes = [];

    /** @param callable(Request): Response $handler */
    public function add(string $method, string $pattern, callable $handler): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        $this->routes[] = ['method' => $method, 'regex' => $regex, 'handler' => $handler];
    }

    public function dispatch(Request $request): Response
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $m)) {
                continue;
            }
            if ($route['method'] !== $request->method) {
                $allowed[] = $route['method'];
                continue;
            }
            $request->params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            return ($route['handler'])($request);
        }
        if ($allowed) {
            throw new HttpError(405, 'Method Not Allowed');
        }
        throw new HttpError(404, 'Not Found');
    }
}
