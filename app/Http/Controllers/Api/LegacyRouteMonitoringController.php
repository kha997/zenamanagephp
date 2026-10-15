<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LegacyRouteMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Legacy Route Monitoring',
    description: 'Legacy route usage monitoring and migration management',
)]
class LegacyRouteMonitoringController extends Controller
{
    protected $monitoringService;

    public function __construct(LegacyRouteMonitoringService $monitoringService)
    {
        $this->monitoringService = $monitoringService;
    }

    #[OA\Get(
        path: '/api/v1/legacy-routes/usage',
        description: 'Retrieve comprehensive usage statistics for all legacy routes',
        summary: 'Get legacy route usage statistics',
        security: [
            [
                'sanctum' => [],
            ],
        ],
        tags: [
            'Legacy Route Monitoring',
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Usage statistics retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'success',
                            type: 'boolean',
                            example: true,
                        ),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'routes',
                                    properties: [
                                        new OA\Property(
                                            property: '/dashboard',
                                            properties: [
                                                new OA\Property(
                                                    property: 'legacy_path',
                                                    type: 'string',
                                                    example: '/dashboard',
                                                ),
                                                new OA\Property(
                                                    property: 'total_usage',
                                                    type: 'integer',
                                                    example: 150,
                                                ),
                                                new OA\Property(
                                                    property: 'last_7_days_total',
                                                    type: 'integer',
                                                    example: 25,
                                                ),
                                                new OA\Property(
                                                    property: 'average_daily',
                                                    type: 'number',
                                                    format: 'float',
                                                    example: 3.57,
                                                ),
                                                new OA\Property(
                                                    property: 'trend',
                                                    type: 'string',
                                                    example: 'decreasing',
                                                ),
                                            ],
                                            type: 'object',
                                        ),
                                    ],
                                    type: 'object',
                                ),
                            ],
                            type: 'object',
                        ),
                        new OA\Property(
                            property: 'summary',
                            properties: [
                                new OA\Property(
                                    property: 'total_legacy_usage',
                                    type: 'integer',
                                    example: 450,
                                ),
                                new OA\Property(
                                    property: 'last_7_days_total',
                                    type: 'integer',
                                    example: 75,
                                ),
                                new OA\Property(
                                    property: 'average_daily_total',
                                    type: 'number',
                                    format: 'float',
                                    example: 10.71,
                                ),
                                new OA\Property(
                                    property: 'highest_usage_route',
                                    type: 'string',
                                    example: '/dashboard',
                                ),
                                new OA\Property(
                                    property: 'highest_usage_count',
                                    type: 'integer',
                                    example: 150,
                                ),
                                new OA\Property(
                                    property: 'active_routes',
                                    type: 'integer',
                                    example: 3,
                                ),
                            ],
                            type: 'object',
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(
                response: 401,
                description: 'Authentication required',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorEnvelope',
                ),
            ),
            new OA\Response(
                response: 403,
                description: 'Insufficient permissions - Admin role required',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorEnvelope',
                ),
            ),
        ],
    )]
    public function getUsageStats(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !$user->hasRole('admin')) {
                return response()->json([
                    'error' => [
                        'id' => 'req_' . substr(md5(uniqid()), 0, 8),
                        'code' => 'E403.AUTHORIZATION',
                        'message' => 'Admin role required to access legacy route monitoring',
                        'details' => [],
                    ],
                ], 403);
            }

            $stats = $this->monitoringService->getAllUsageStats();

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Legacy route usage statistics retrieved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'id' => 'req_' . substr(md5(uniqid()), 0, 8),
                    'code' => 'E500.SERVER_ERROR',
                    'message' => 'Failed to retrieve legacy route usage statistics',
                    'details' => [
                        'exception' => $e->getMessage(),
                    ],
                ],
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/v1/legacy-routes/migration-phase',
        description: 'Retrieve current migration phase statistics for all legacy routes',
        summary: 'Get migration phase statistics',
        security: [
            [
                'sanctum' => [],
            ],
        ],
        tags: [
            'Legacy Route Monitoring',
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Migration phase statistics retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'success',
                            type: 'boolean',
                            example: true,
                        ),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'current_date',
                                    type: 'string',
                                    example: '2024-12-19',
                                ),
                                new OA\Property(
                                    property: 'phase_distribution',
                                    properties: [
                                        new OA\Property(
                                            property: 'announce',
                                            type: 'integer',
                                            example: 0,
                                        ),
                                        new OA\Property(
                                            property: 'redirect',
                                            type: 'integer',
                                            example: 3,
                                        ),
                                        new OA\Property(
                                            property: 'remove',
                                            type: 'integer',
                                            example: 0,
                                        ),
                                    ],
                                    type: 'object',
                                ),
                                new OA\Property(
                                    property: 'total_routes',
                                    type: 'integer',
                                    example: 3,
                                ),
                                new OA\Property(
                                    property: 'migration_progress',
                                    properties: [
                                        new OA\Property(
                                            property: 'completed_announce',
                                            type: 'integer',
                                            example: 3,
                                        ),
                                        new OA\Property(
                                            property: 'completed_redirect',
                                            type: 'integer',
                                            example: 0,
                                        ),
                                        new OA\Property(
                                            property: 'completion_percentage',
                                            type: 'number',
                                            format: 'float',
                                            example: 0.0,
                                        ),
                                    ],
                                    type: 'object',
                                ),
                            ],
                            type: 'object',
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(
                response: 401,
                description: 'Authentication required',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorEnvelope',
                ),
            ),
            new OA\Response(
                response: 403,
                description: 'Insufficient permissions - Admin role required',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorEnvelope',
                ),
            ),
        ],
    )]
    public function getMigrationPhaseStats(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !$user->hasRole('admin')) {
                return response()->json([
                    'error' => [
                        'id' => 'req_' . substr(md5(uniqid()), 0, 8),
                        'code' => 'E403.AUTHORIZATION',
                        'message' => 'Admin role required to access migration phase statistics',
                        'details' => [],
                    ],
                ], 403);
            }

            $stats = $this->monitoringService->getMigrationPhaseStats();

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Migration phase statistics retrieved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'id' => 'req_' . substr(md5(uniqid()), 0, 8),
                    'code' => 'E500.SERVER_ERROR',
                    'message' => 'Failed to retrieve migration phase statistics',
                    'details' => [
                        'exception' => $e->getMessage(),
                    ],
                ],
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/v1/legacy-routes/report',
        description: 'Generate a comprehensive report including usage statistics, migration progress, and recommendations',
        summary: 'Generate comprehensive usage report',
        security: [
            [
                'sanctum' => [],
            ],
        ],
        tags: [
            'Legacy Route Monitoring',
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Usage report generated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'success',
                            type: 'boolean',
                            example: true,
                        ),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'report_type',
                                    type: 'string',
                                    example: 'legacy_route_usage',
                                ),
                                new OA\Property(
                                    property: 'generated_at',
                                    type: 'string',
                                    example: '2024-12-19T10:30:00Z',
                                ),
                                new OA\Property(
                                    property: 'usage_statistics',
                                    type: 'object',
                                ),
                                new OA\Property(
                                    property: 'migration_phase_statistics',
                                    type: 'object',
                                ),
                                new OA\Property(
                                    property: 'recommendations',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(
                                                property: 'type',
                                                type: 'string',
                                                example: 'high_usage_warning',
                                            ),
                                            new OA\Property(
                                                property: 'route',
                                                type: 'string',
                                                example: '/dashboard',
                                            ),
                                            new OA\Property(
                                                property: 'message',
                                                type: 'string',
                                                example: 'Route /dashboard has high usage',
                                            ),
                                            new OA\Property(
                                                property: 'priority',
                                                type: 'string',
                                                example: 'high',
                                            ),
                                        ],
                                        type: 'object',
                                    ),
                                ),
                            ],
                            type: 'object',
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(
                response: 401,
                description: 'Authentication required',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorEnvelope',
                ),
            ),
            new OA\Response(
                response: 403,
                description: 'Insufficient permissions - Admin role required',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorEnvelope',
                ),
            ),
        ],
    )]
    public function generateReport(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !$user->hasRole('admin')) {
                return response()->json([
                    'error' => [
                        'id' => 'req_' . substr(md5(uniqid()), 0, 8),
                        'code' => 'E403.AUTHORIZATION',
                        'message' => 'Admin role required to generate legacy route reports',
                        'details' => [],
                    ],
                ], 403);
            }

            $report = $this->monitoringService->generateUsageReport();

            return response()->json([
                'success' => true,
                'data' => $report,
                'message' => 'Legacy route usage report generated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'id' => 'req_' . substr(md5(uniqid()), 0, 8),
                    'code' => 'E500.SERVER_ERROR',
                    'message' => 'Failed to generate legacy route usage report',
                    'details' => [
                        'exception' => $e->getMessage(),
                    ],
                ],
            ], 500);
        }
    }

    #[OA\Post(
        path: '/api/v1/legacy-routes/record-usage',
        description: 'Record usage of a legacy route for monitoring purposes',
        summary: 'Record legacy route usage',
        security: [
            [
                'sanctum' => [],
            ],
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'legacy_path',
                        type: 'string',
                        example: '/dashboard',
                    ),
                    new OA\Property(
                        property: 'new_path',
                        type: 'string',
                        example: '/app/dashboard',
                    ),
                    new OA\Property(
                        property: 'metadata',
                        properties: [
                            new OA\Property(
                                property: 'user_agent',
                                type: 'string',
                                example: 'Mozilla/5.0...',
                            ),
                            new OA\Property(
                                property: 'referer',
                                type: 'string',
                                example: 'https://example.com',
                            ),
                        ],
                        type: 'object',
                    ),
                ],
                type: 'object',
            ),
        ),
        tags: [
            'Legacy Route Monitoring',
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Usage recorded successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'success',
                            type: 'boolean',
                            example: true,
                        ),
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Legacy route usage recorded successfully',
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorEnvelope',
                ),
            ),
        ],
    )]
    public function recordUsage(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'legacy_path' => 'required|string',
                'new_path' => 'required|string',
                'metadata' => 'sometimes|array',
            ]);

            $this->monitoringService->recordUsage(
                $request->input('legacy_path'),
                $request->input('new_path'),
                $request->input('metadata', [])
            );

            return response()->json([
                'success' => true,
                'message' => 'Legacy route usage recorded successfully',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => [
                    'id' => 'req_' . substr(md5(uniqid()), 0, 8),
                    'code' => 'E422.VALIDATION',
                    'message' => 'Validation failed',
                    'details' => [
                        'validation' => $e->errors(),
                    ],
                ],
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'id' => 'req_' . substr(md5(uniqid()), 0, 8),
                    'code' => 'E500.SERVER_ERROR',
                    'message' => 'Failed to record legacy route usage',
                    'details' => [
                        'exception' => $e->getMessage(),
                    ],
                ],
            ], 500);
        }
    }

    #[OA\Post(
        path: '/api/v1/legacy-routes/cleanup',
        description: 'Clean up old legacy route monitoring data to free up storage',
        summary: 'Clean up old monitoring data',
        security: [
            [
                'sanctum' => [],
            ],
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'days_to_keep',
                        description: 'Number of days to keep data',
                        type: 'integer',
                        example: 30,
                    ),
                ],
                type: 'object',
            ),
        ),
        tags: [
            'Legacy Route Monitoring',
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Data cleanup completed successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'success',
                            type: 'boolean',
                            example: true,
                        ),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'cleared_entries',
                                    type: 'integer',
                                    example: 150,
                                ),
                            ],
                            type: 'object',
                        ),
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Legacy route monitoring data cleaned successfully',
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(
                response: 401,
                description: 'Authentication required',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorEnvelope',
                ),
            ),
            new OA\Response(
                response: 403,
                description: 'Insufficient permissions - Admin role required',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorEnvelope',
                ),
            ),
        ],
    )]
    public function cleanup(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !$user->hasRole('admin')) {
                return response()->json([
                    'error' => [
                        'id' => 'req_' . substr(md5(uniqid()), 0, 8),
                        'code' => 'E403.AUTHORIZATION',
                        'message' => 'Admin role required to clean up monitoring data',
                        'details' => [],
                    ],
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'days_to_keep' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => [
                        'id' => 'req_' . substr(md5(uniqid()), 0, 8),
                        'code' => 'E422.VALIDATION',
                        'message' => 'Validation failed',
                        'details' => [
                            'validation' => $validator->errors()->toArray(),
                        ],
                    ],
                ], 422);
            }

            $daysToKeep = (int) $request->input('days_to_keep', 30);
            $clearedEntries = $this->monitoringService->clearOldData($daysToKeep);

            return response()->json([
                'success' => true,
                'data' => [
                    'cleared_entries' => $clearedEntries,
                    'days_kept' => $daysToKeep,
                ],
                'message' => 'Legacy route monitoring data cleaned successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'id' => 'req_' . substr(md5(uniqid()), 0, 8),
                    'code' => 'E500.SERVER_ERROR',
                    'message' => 'Failed to clean up monitoring data',
                    'details' => [
                        'exception' => $e->getMessage(),
                    ],
                ],
            ], 500);
        }
    }
}
