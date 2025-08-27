<?php declare(strict_types=1);

/**
 * zenamanage Main Entry Point
 * 
 * File này là điểm vào chính của ứng dụng zenamanage
 * Xử lý tất cả các HTTP requests và routing
 */

// Bootstrap the application
require_once __DIR__ . '/bootstrap.php';

use zenamanage\Foundation\Foundation;
use zenamanage\RBAC\Middleware\RBACMiddleware;
use zenamanage\RBAC\Middleware\TenantIsolationMiddleware;
use zenamanage\RBAC\Services\RBACManager;

// Get request information
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestPath = parse_url($requestUri, PHP_URL_PATH);

// Remove base path if running in subdirectory
$basePath = '/zenamanage';
if (strpos($requestPath, $basePath) === 0) {
    $requestPath = substr($requestPath, strlen($basePath));
}

// Set CORS headers for API requests
if (strpos($requestPath, '/api/') === 0) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    // Handle preflight requests
    if ($requestMethod === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// Simple routing system
try {
    $response = handleRequest($requestPath, $requestMethod);
    echo $response;
} catch (Exception $e) {
    http_response_code(500);
    if (config('APP_DEBUG', false)) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Internal server error'
        ]);
    }
}

/**
 * Handle incoming HTTP request
 * 
 * @param string $path
 * @param string $method
 * @return string
 */
function handleRequest(string $path, string $method): string
{
    // API routes
    if (strpos($path, '/api/v1/') === 0) {
        return handleApiRequest($path, $method);
    }
    
    // Static file serving for development
    if (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|svg)$/', $path)) {
        return serveStaticFile($path);
    }
    
    // Default to API documentation or welcome page
    return json_encode([
        'status' => 'success',
        'message' => 'zenamanage API Server',
        'version' => '1.0.0',
        'endpoints' => [
            '/api/v1/rbac/roles' => 'Role management',
            '/api/v1/rbac/permissions' => 'Permission management',
            '/api/v1/rbac/assignments' => 'Role assignments',
            '/api/v1/projects' => 'Project management',
            '/api/v1/tasks' => 'Task management'
        ]
    ]);
}

/**
 * Handle API requests
 * 
 * @param string $path
 * @param string $method
 * @return string
 */
function handleApiRequest(string $path, string $method): string
{
    // Remove /api/v1 prefix
    $apiPath = substr($path, 7);
    
    // Parse path segments
    $segments = array_filter(explode('/', $apiPath));
    
    if (empty($segments)) {
        return json_encode([
            'status' => 'error',
            'message' => 'Invalid API endpoint'
        ]);
    }
    
    $module = $segments[0];
    
    // Route to appropriate controller
    switch ($module) {
        case 'rbac':
            return handleRBACRequest($segments, $method);
        case 'projects':
            return handleProjectRequest($segments, $method);
        case 'tasks':
            return handleTaskRequest($segments, $method);
        case 'users':
            return handleUserRequest($segments, $method);
        default:
            http_response_code(404);
            return json_encode([
                'status' => 'error',
                'message' => 'API endpoint not found'
            ]);
    }
}

/**
 * Handle RBAC API requests
 * 
 * @param array $segments
 * @param string $method
 * @return string
 */
function handleRBACRequest(array $segments, string $method): string
{
    if (count($segments) < 2) {
        http_response_code(400);
        return json_encode([
            'status' => 'error',
            'message' => 'Invalid RBAC endpoint'
        ]);
    }
    
    $action = $segments[1];
    
    switch ($action) {
        case 'roles':
            $controller = new \zenamanage\RBAC\Controllers\RoleController();
            return $controller->handle($method, array_slice($segments, 2));
        case 'permissions':
            $controller = new \zenamanage\RBAC\Controllers\PermissionController();
            return $controller->handle($method, array_slice($segments, 2));
        case 'assignments':
            $controller = new \zenamanage\RBAC\Controllers\AssignmentController();
            return $controller->handle($method, array_slice($segments, 2));
        default:
            http_response_code(404);
            return json_encode([
                'status' => 'error',
                'message' => 'RBAC endpoint not found'
            ]);
    }
}

/**
 * Handle other module requests (placeholder)
 * 
 * @param array $segments
 * @param string $method
 * @return string
 */
function handleProjectRequest(array $segments, string $method): string
{
    return json_encode([
        'status' => 'success',
        'message' => 'Project API endpoint - Coming soon'
    ]);
}

function handleTaskRequest(array $segments, string $method): string
{
    return json_encode([
        'status' => 'success',
        'message' => 'Task API endpoint - Coming soon'
    ]);
}

function handleUserRequest(array $segments, string $method): string
{
    return json_encode([
        'status' => 'success',
        'message' => 'User API endpoint - Coming soon'
    ]);
}

/**
 * Serve static files for development
 * 
 * @param string $path
 * @return string
 */
function serveStaticFile(string $path): string
{
    $filePath = __DIR__ . '/public' . $path;
    
    if (file_exists($filePath)) {
        $mimeType = mime_content_type($filePath);
        header('Content-Type: ' . $mimeType);
        return file_get_contents($filePath);
    }
    
    http_response_code(404);
    return 'File not found';
}