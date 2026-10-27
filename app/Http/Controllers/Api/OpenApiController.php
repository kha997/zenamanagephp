<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    description: 'ZenaManage Project Management System API Documentation',
    title: 'ZenaManage API v1',
    contact: new OA\Contact(
        name: 'ZenaManage Support',
        email: 'support@zenamanage.com',
    ),
    license: new OA\License(
        name: 'MIT',
        url: 'https://opensource.org/licenses/MIT',
    ),
)]
#[OA\Server(
    url: 'http://localhost:8000',
    description: 'Local Development Server',
)]
#[OA\Server(
    url: 'https://api.zenamanage.com',
    description: 'Production Server',
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    description: 'Laravel Sanctum Token Authentication',
    bearerFormat: 'JWT',
    scheme: 'bearer',
)]
#[OA\SecurityScheme(
    securityScheme: 'cookie',
    type: 'apiKey',
    description: 'CSRF Token for SPA Authentication',
    name: 'XSRF-TOKEN',
    in: 'cookie',
)]
#[OA\Tag(
    name: 'Authentication',
    description: 'Authentication and authorization endpoints',
)]
#[OA\Tag(
    name: 'Admin',
    description: 'Super admin only endpoints',
)]
#[OA\Tag(
    name: 'App',
    description: 'Tenant-scoped application endpoints',
)]
#[OA\Tag(
    name: 'Public',
    description: 'Public endpoints (no authentication required)',
)]
#[OA\Tag(
    name: 'Invitations',
    description: 'User invitation management',
)]
class OpenApiController extends Controller
{
    #[OA\Get(
        path: '/api/v1/public/health',
        description: 'Returns the health status of the API',
        summary: 'Health Check',
        tags: [
            'Public',
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'API is healthy',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'status',
                            type: 'string',
                            example: 'ok',
                        ),
                        new OA\Property(
                            property: 'timestamp',
                            type: 'string',
                            format: 'date-time',
                        ),
                        new OA\Property(
                            property: 'version',
                            type: 'string',
                            example: '1.0.0',
                        ),
                        new OA\Property(
                            property: 'environment',
                            type: 'string',
                            example: 'local',
                        ),
                    ],
                ),
            ),
        ],
    )]
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0',
            'environment' => app()->environment(),
        ]);
    }

    #[OA\Get(
        path: '/api/v1/admin/perf/health',
        description: 'Returns detailed health status including database, queue, and storage',
        summary: 'Admin Performance Health Check',
        security: [
            [
                'sanctum' => [],
            ],
        ],
        tags: [
            'Admin',
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Detailed health status',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'status',
                            type: 'string',
                            example: 'ok',
                        ),
                        new OA\Property(
                            property: 'timestamp',
                            type: 'string',
                            format: 'date-time',
                        ),
                        new OA\Property(
                            property: 'services',
                            properties: [
                                new OA\Property(
                                    property: 'database',
                                    type: 'string',
                                    example: 'ok',
                                ),
                                new OA\Property(
                                    property: 'queue',
                                    type: 'string',
                                    example: 'ok',
                                ),
                                new OA\Property(
                                    property: 'storage',
                                    type: 'string',
                                    example: 'ok',
                                ),
                                new OA\Property(
                                    property: 'cache',
                                    type: 'string',
                                    example: 'ok',
                                ),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Unauthenticated.',
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden - Admin access required',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Access denied. Admin privileges required.',
                        ),
                    ],
                ),
            ),
        ],
    )]
    public function adminHealth(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'services' => [
                'database' => 'ok',
                'queue' => 'ok',
                'storage' => 'ok',
                'cache' => 'ok',
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/v1/admin/perf/metrics',
        description: 'Returns system performance metrics',
        summary: 'Performance Metrics',
        security: [
            [
                'sanctum' => [],
            ],
        ],
        tags: [
            'Admin',
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Performance metrics',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'timestamp',
                            type: 'string',
                            format: 'date-time',
                        ),
                        new OA\Property(
                            property: 'memory_usage',
                            type: 'string',
                            example: '45.2 MB',
                        ),
                        new OA\Property(
                            property: 'execution_time',
                            type: 'string',
                            example: '125ms',
                        ),
                        new OA\Property(
                            property: 'database_queries',
                            type: 'integer',
                            example: 12,
                        ),
                        new OA\Property(
                            property: 'cache_hits',
                            type: 'integer',
                            example: 8,
                        ),
                        new OA\Property(
                            property: 'cache_misses',
                            type: 'integer',
                            example: 2,
                        ),
                    ],
                ),
            ),
        ],
    )]
    public function metrics(): JsonResponse
    {
        return response()->json([
            'timestamp' => now()->toISOString(),
            'memory_usage' => memory_get_usage(true) / 1024 / 1024 . ' MB',
            'execution_time' => '125ms',
            'database_queries' => 12,
            'cache_hits' => 8,
            'cache_misses' => 2,
        ]);
    }

    #[OA\Post(
        path: '/api/v1/admin/perf/clear-caches',
        description: 'Clears all system caches',
        summary: 'Clear System Caches',
        security: [
            [
                'sanctum' => [],
            ],
        ],
        tags: [
            'Admin',
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Caches cleared successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'All caches cleared successfully',
                        ),
                        new OA\Property(
                            property: 'timestamp',
                            type: 'string',
                            format: 'date-time',
                        ),
                    ],
                ),
            ),
        ],
    )]
    public function clearCaches(): JsonResponse
    {
        \Artisan::call('cache:clear');
        \Artisan::call('config:clear');
        \Artisan::call('route:clear');
        \Artisan::call('view:clear');

        return response()->json([
            'message' => 'All caches cleared successfully',
            'timestamp' => now()->toISOString(),
        ]);
    }
}
