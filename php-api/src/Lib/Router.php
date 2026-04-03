<?php

namespace Lib;

class Router {
    private array $routes = [];
    private array $middlewares = [];

    /**
     * Register a GET route
     */
    public function get(string $path, callable $handler, array $middlewares = []): void {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    /**
     * Register a POST route
     */
    public function post(string $path, callable $handler, array $middlewares = []): void {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }

    /**
     * Register a PUT route
     */
    public function put(string $path, callable $handler, array $middlewares = []): void {
        $this->addRoute('PUT', $path, $handler, $middlewares);
    }

    /**
     * Register a DELETE route
     */
    public function delete(string $path, callable $handler, array $middlewares = []): void {
        $this->addRoute('DELETE', $path, $handler, $middlewares);
    }

    /**
     * Add a global middleware
     */
    public function use(callable $middleware): void {
        $this->middlewares[] = $middleware;
    }

    /**
     * Add a route to the router
     */
    private function addRoute(string $method, string $path, callable $handler, array $middlewares): void {
        $pattern = preg_replace('/\/:([^\/]+)/', '/(?<$1>[^/]+)', $path);
        $pattern = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'pattern' => $pattern,
            'handler' => $handler,
            'middlewares' => $middlewares
        ];
    }

    /**
     * Dispatch the request to the appropriate handler
     */
    public function dispatch(string $method, string $uri): void {
        // Apply global middlewares
        foreach ($this->middlewares as $middleware) {
            $result = $middleware();
            if ($result === false) {
                return;
            }
        }

        // Find matching route
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $uri, $matches)) {
                // Apply route-specific middlewares
                foreach ($route['middlewares'] as $middleware) {
                    $result = $middleware();
                    if ($result === false) {
                        return;
                    }
                }

                // Extract route parameters
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Call handler with parameters
                call_user_func_array($route['handler'], [$params]);
                return;
            }
        }

        // No route found
        http_response_code(404);
        echo json_encode(['error' => 'Route not found']);
    }
}