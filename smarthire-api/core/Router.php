<?php

class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler, array $middlewares = []): void
    {
        $this->routes[] = [
            'method'      => strtoupper($method),
            'pattern'     => trim($pattern, '/'),
            'handler'     => $handler,
            'middlewares' => $middlewares,
        ];
    }

    public function get(string $pattern, callable $handler, array $middlewares = []): void
    {
        $this->add('GET', $pattern, $handler, $middlewares);
    }

    public function post(string $pattern, callable $handler, array $middlewares = []): void
    {
        $this->add('POST', $pattern, $handler, $middlewares);
    }

    public function put(string $pattern, callable $handler, array $middlewares = []): void
    {
        $this->add('PUT', $pattern, $handler, $middlewares);
    }

    public function delete(string $pattern, callable $handler, array $middlewares = []): void
    {
        $this->add('DELETE', $pattern, $handler, $middlewares);
    }

    public function dispatch(string $method, string $uri, Request $request): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?? '';

        // Retire automatiquement le dossier où vit index.php (ex: /smarthire-api
        // sous XAMPP) pour que le routing fonctionne quel que soit le nom du
        // sous-dossier de déploiement, sans avoir à le configurer en dur.
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir));
        }

        $path = trim($path, '/');
        $path = preg_replace('#^index\.php/?#', '', $path);

        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }

            $regex = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $route['pattern']);
            if (preg_match('#^' . $regex . '$#', $path, $matches)) {
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $request->params[$key] = $value;
                    }
                }

                foreach ($route['middlewares'] as $middleware) {
                    $middleware($request); // arrête l'exécution via Response::error si échec
                }

                call_user_func($route['handler'], $request);
                return;
            }
        }

        Response::error('Route not found', 404);
    }
}
