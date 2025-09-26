<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LegacyRouteMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Legacy Route Monitoring",
 *     description="Legacy route usage monitoring and migration management"
 * )
 */
class LegacyRouteMonitoringController extends Controller
{
    protected $monitoringService;

    public function __construct(LegacyRouteMonitoringService $monitoringService)
    {
        $this->monitoringService = $monitoringService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/legacy-routes/usage",
     *     summary="Get legacy route usage statistics",
     *     description="Retrieve comprehensive usage statistics for all legacy routes",
     *     tags={"Legacy Route Monitoring"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Usage statistics retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="routes",
     *                     type="object",
     *                     @OA\Property(
     *                         property="/dashboard",
     *                         type="object",
     *                         @OA\Property(property="legacy_path", type="string", example="/dashboard"),
     *                         @OA\Property(property="total_usage", type="integer", example=150),
     *                         @OA\Property(property="last_7_days_total", type="integer", example=25),
     *                         @OA\Property(property="average_daily", type="number", format="float", example=3.57),
     *                         @OA\Property(property="trend", type="string", example="decreasing")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="summary",
     *                 type="object",
     *                 @OA\Property(property="total_legacy_usage", type="integer", example=450),
     *                 @OA\Property(property="last_7_days_total", type="integer", example=75),
     *                 @OA\Property(property="average_daily_total", type="number", format="float", example=10.71),
     *                 @OA\Property(property="highest_usage_route", type="string", example="/dashboard"),
     *                 @OA\Property(property="highest_usage_count", type="integer", example=150),
     *                 @OA\Property(property="active_routes", type="integer", example=3)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Authentication required",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorEnvelope")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Insufficient permissions - Admin role required",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorEnvelope")
     *     )
     * )
     * 
     * Get legacy route usage statistics
     */
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
                        'details' => []
                    ]
                ], 403);
            }

            $stats = $this->monitoringService->getAllUsageStats();

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Legacy route usage statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'id' => 'req_' . substr(md5(uniqid()), 0, 8),
                    'code' => 'E500.SERVER_ERROR',
                    'message' => 'Failed to retrieve legacy route usage statistics',
                    'details' => [
                        'exception' => $e->getMessage()
                    ]
                ]
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/legacy-routes/migration-phase",
     *     summary="Get migration phase statistics",
     *     description="Retrieve current migration phase statistics for all legacy routes",
     *     tags={"Legacy Route Monitoring"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Migration phase statistics retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_date", type="string", example="2024-12-19"),
     *                 @OA\Property(
     *                     property="phase_distribution",
     *                     type="object",
     *                     @OA\Property(property="announce", type="integer", example=0),
     *                     @OA\Property(property="redirect", type="integer", example=3),
     *                     @OA\Property(property="remove", type="integer", example=0)
     *                 ),
     *                 @OA\Property(property="total_routes", type="integer", example=3),
     *                 @OA\Property(
     *                     property="migration_progress",
     *                     type="object",
     *                     @OA\Property(property="completed_announce", type="integer", example=3),
     *                     @OA\Property(property="completed_redirect", type="integer", example=0),
     *                     @OA\Property(property="completion_percentage", type="number", format="float", example=0.0)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Authentication required",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorEnvelope")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Insufficient permissions - Admin role required",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorEnvelope")
     *     )
     * )
     * 
     * Get migration phase statistics
     */
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
                        'details' => []
                    ]
                ], 403);
            }

            $stats = $this->monitoringService->getMigrationPhaseStats();

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Migration phase statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'id' => 'req_' . substr(md5(uniqid()), 0, 8),
                    'code' => 'E500.SERVER_ERROR',
                    'message' => 'Failed to retrieve migration phase statistics',
                    'details' => [
                        'exception' => $e->getMessage()
                    ]
                ]
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/legacy-routes/report",
     *     summary="Generate comprehensive usage report",
     *     description="Generate a comprehensive report including usage statistics, migration progress, and recommendations",
     *     tags={"Legacy Route Monitoring"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Usage report generated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="report_type", type="string", example="legacy_route_usage"),
     *                 @OA\Property(property="generated_at", type="string", example="2024-12-19T10:30:00Z"),
     *                 @OA\Property(property="usage_statistics", type="object"),
     *                 @OA\Property(property="migration_phase_statistics", type="object"),
     *                 @OA\Property(
     *                     property="recommendations",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="type", type="string", example="high_usage_warning"),
     *                         @OA\Property(property="route", type="string", example="/dashboard"),
     *                         @OA\Property(property="message", type="string", example="Route /dashboard has high usage"),
     *                         @OA\Property(property="priority", type="string", example="high")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Authentication required",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorEnvelope")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Insufficient permissions - Admin role required",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorEnvelope")
     *     )
     * )
     * 
     * Generate comprehensive usage report
     */
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
                        'details' => []
                    ]
                ], 403);
            }

            $report = $this->monitoringService->generateUsageReport();

            return response()->json([
                'success' => true,
                'data' => $report,
                'message' => 'Legacy route usage report generated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'id' => 'req_' . substr(md5(uniqid()), 0, 8),
                    'code' => 'E500.SERVER_ERROR',
                    'message' => 'Failed to generate legacy route usage report',
                    'details' => [
                        'exception' => $e->getMessage()
                    ]
                ]
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/legacy-routes/record-usage",
     *     summary="Record legacy route usage",
     *     description="Record usage of a legacy route for monitoring purposes",
     *     tags={"Legacy Route Monitoring"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="legacy_path", type="string", example="/dashboard"),
     *             @OA\Property(property="new_path", type="string", example="/app/dashboard"),
     *             @OA\Property(
     *                 property="metadata",
     *                 type="object",
     *                 @OA\Property(property="user_agent", type="string", example="Mozilla/5.0..."),
     *                 @OA\Property(property="referer", type="string", example="https://example.com")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Usage recorded successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Legacy route usage recorded successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorEnvelope")
     *     )
     * )
     * 
     * Record legacy route usage
     */
    public function recordUsage(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'legacy_path' => 'required|string',
                'new_path' => 'required|string',
                'metadata' => 'sometimes|array'
            ]);

            $this->monitoringService->recordUsage(
                $request->input('legacy_path'),
                $request->input('new_path'),
                $request->input('metadata', [])
            );

            return response()->json([
                'success' => true,
                'message' => 'Legacy route usage recorded successfully'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => [
                    'id' => 'req_' . substr(md5(uniqid()), 0, 8),
                    'code' => 'E422.VALIDATION',
                    'message' => 'Validation failed',
                    'details' => [
                        'validation' => $e->errors()
                    ]
                ]
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'id' => 'req_' . substr(md5(uniqid()), 0, 8),
                    'code' => 'E500.SERVER_ERROR',
                    'message' => 'Failed to record legacy route usage',
                    'details' => [
                        'exception' => $e->getMessage()
                    ]
                ]
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/legacy-routes/cleanup",
     *     summary="Clean up old monitoring data",
     *     description="Clean up old legacy route monitoring data to free up storage",
     *     tags={"Legacy Route Monitoring"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="days_to_keep", type="integer", example=30, description="Number of days to keep data")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data cleanup completed successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object", @OA\Property(property="cleared_entries", type="integer", example=150)),
     *             @OA\Property(property="message", type="string", example="Legacy route monitoring data cleaned successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Authentication required",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorEnvelope")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Insufficient permissions - Admin role required",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorEnvelope")
     *     )
     * )
     * 
     * Clean up old monitoring data
     */
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
                        'details' => []
                    ]
                ], 403);
            }

            $daysToKeep = $request->input('days_to_keep', 30);
            $clearedEntries = $this->monitoringService->clearOldData($daysToKeep);

            return response()->json([
                'success' => true,
                'data' => [
                    'cleared_entries' => $clearedEntries,
                    'days_kept' => $daysToKeep
                ],
                'message' => 'Legacy route monitoring data cleaned successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'id' => 'req_' . substr(md5(uniqid()), 0, 8),
                    'code' => 'E500.SERVER_ERROR',
                    'message' => 'Failed to clean up monitoring data',
                    'details' => [
                        'exception' => $e->getMessage()
                    ]
                ]
            ], 500);
        }
    }
}
