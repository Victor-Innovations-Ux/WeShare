<?php

namespace Api\Middleware;

class CorsMiddleware {
    /**
     * Handle CORS headers
     */
    public static function handle(): bool {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';

        // Set CORS headers
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Max-Age: 86400");

        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        return true;
    }
}