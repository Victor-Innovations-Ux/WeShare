<?php

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
require_once __DIR__ . '/src/Config/env.php';

// Autoloader for custom classes
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Import classes
use Lib\Router;
use Lib\Response;
use Api\Middleware\CorsMiddleware;
use Api\Middleware\AuthMiddleware;
use Api\Controllers\AuthController;
use Api\Controllers\GroupController;
use Api\Controllers\PhotoController;

// Initialize router
$router = new Router();

// Global middleware
$router->use([CorsMiddleware::class, 'handle']);

// Public routes
$router->get('/api', function() {
    Response::success([
        'name' => 'WeShare API',
        'version' => '1.0.0',
        'endpoints' => [
            'auth' => '/api/auth',
            'groups' => '/api/groups',
            'photos' => '/api/photos'
        ]
    ]);
});

// Authentication routes
$router->get('/api/auth/google/login', [AuthController::class, 'googleLogin']);
$router->get('/api/auth/google/callback', [AuthController::class, 'googleCallback']);
$router->post('/api/auth/join', [AuthController::class, 'joinGroup']);
$router->get('/api/auth/me', [AuthController::class, 'me'], [[AuthMiddleware::class, 'requireAuth']]);
$router->post('/api/auth/logout', [AuthController::class, 'logout'], [[AuthMiddleware::class, 'requireAuth']]);

// Group routes
$router->post('/api/groups', [GroupController::class, 'create'], [[AuthMiddleware::class, 'optionalAuth']]);
$router->get('/api/groups', [GroupController::class, 'list'], [[AuthMiddleware::class, 'requireUser']]);
$router->get('/api/groups/:code', [GroupController::class, 'getByCode'], [[AuthMiddleware::class, 'optionalAuth']]);
$router->get('/api/groups/:id/participants', [GroupController::class, 'getParticipants'], [[AuthMiddleware::class, 'requireAuth']]);
$router->put('/api/groups/:id', [GroupController::class, 'update'], [[AuthMiddleware::class, 'requireUser']]);
$router->delete('/api/groups/:id', [GroupController::class, 'delete'], [[AuthMiddleware::class, 'requireUser']]);

// Photo routes
$router->post('/api/photos', [PhotoController::class, 'upload'], [[AuthMiddleware::class, 'requireAuth']]);
$router->get('/api/groups/:groupId/photos', [PhotoController::class, 'getByGroup'], [[AuthMiddleware::class, 'requireAuth']]);
$router->delete('/api/photos/:id', [PhotoController::class, 'delete'], [[AuthMiddleware::class, 'requireAuth']]);
$router->get('/api/photos/download/:id', [PhotoController::class, 'download'], [[AuthMiddleware::class, 'requireAuth']]);

// Handle request
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = $_SERVER['REQUEST_URI'];

    // Remove query string
    $uri = strtok($uri, '?');

    $router->dispatch($method, $uri);
} catch (Exception $e) {
    error_log("Router error: " . $e->getMessage());
    Response::error('Internal server error', 500);
}