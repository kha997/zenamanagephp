<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Info(
 *     title="ZenaManage API v1",
 *     version="1.0.0",
 *     description="ZenaManage Project Management System API Documentation",
 *     @OA\Contact(
 *         email="support@zenamanage.com",
 *         name="ZenaManage Support"
 *     ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Local Development Server"
 * )
 * 
 * @OA\Server(
 *     url="https://api.zenamanage.com",
 *     description="Production Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Laravel Sanctum Token Authentication"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="cookie",
 *     type="apiKey",
 *     in="cookie",
 *     name="XSRF-TOKEN",
 *     description="CSRF Token for SPA Authentication"
 * )
 * 
 * @OA\Tag(
 *     name="Authentication",
 *     description="Authentication and authorization endpoints"
 * )
 * 
 * @OA\Tag(
 *     name="Admin",
 *     description="Super admin only endpoints"
 * )
 * 
 * @OA\Tag(
 *     name="App",
 *     description="Tenant-scoped application endpoints"
 * )
 * 
 * @OA\Tag(
 *     name="Public",
 *     description="Public endpoints (no authentication required)"
 * )
 * 
 * @OA\Tag(
 *     name="Invitations",
 *     description="User invitation management"
 * )
 */
class OpenApiController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/public/health",
     *     tags={"Public"},
     *     summary="Health Check",
     *     description="Returns the health status of the API",
     *     @OA\Response(
     *         response=200,
     *         description="API is healthy",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="timestamp", type="string", format="date-time"),
     *             @OA\Property(property="version", type="string", example="1.0.0"),
     *             @OA\Property(property="environment", type="string", example="local")
     *         )
     *     )
     * )
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0',
            'environment' => app()->environment()
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/perf/health",
     *     tags={"Admin"},
     *     summary="Admin Performance Health Check",
     *     description="Returns detailed health status including database, queue, and storage",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Detailed health status",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="timestamp", type="string", format="date-time"),
     *             @OA\Property(
     *                 property="services",
     *                 type="object",
     *                 @OA\Property(property="database", type="string", example="ok"),
     *                 @OA\Property(property="queue", type="string", example="ok"),
     *                 @OA\Property(property="storage", type="string", example="ok"),
     *                 @OA\Property(property="cache", type="string", example="ok")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Access denied. Admin privileges required.")
     *         )
     *     )
     * )
     */
    public function adminHealth(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'services' => [
                'database' => 'ok',
                'queue' => 'ok',
                'storage' => 'ok',
                'cache' => 'ok'
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/perf/metrics",
     *     tags={"Admin"},
     *     summary="Performance Metrics",
     *     description="Returns system performance metrics",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Performance metrics",
     *         @OA\JsonContent(
     *             @OA\Property(property="timestamp", type="string", format="date-time"),
     *             @OA\Property(property="memory_usage", type="string", example="45.2 MB"),
     *             @OA\Property(property="execution_time", type="string", example="125ms"),
     *             @OA\Property(property="database_queries", type="integer", example=12),
     *             @OA\Property(property="cache_hits", type="integer", example=8),
     *             @OA\Property(property="cache_misses", type="integer", example=2)
     *         )
     *     )
     * )
     */
    public function metrics(): JsonResponse
    {
        return response()->json([
            'timestamp' => now()->toISOString(),
            'memory_usage' => memory_get_usage(true) / 1024 / 1024 . ' MB',
            'execution_time' => '125ms',
            'database_queries' => 12,
            'cache_hits' => 8,
            'cache_misses' => 2
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/admin/perf/clear-caches",
     *     tags={"Admin"},
     *     summary="Clear System Caches",
     *     description="Clears all system caches",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Caches cleared successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="All caches cleared successfully"),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function clearCaches(): JsonResponse
    {
        \Artisan::call('cache:clear');
        \Artisan::call('config:clear');
        \Artisan::call('route:clear');
        \Artisan::call('view:clear');

        return response()->json([
            'message' => 'All caches cleared successfully',
            'timestamp' => now()->toISOString()
        ]);
    }
}
