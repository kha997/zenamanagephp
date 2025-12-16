<?php declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FeatureFlagService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * FeatureFlagController
 * 
 * Admin endpoints for managing feature flags (global, tenant, user level)
 */
class FeatureFlagController extends Controller
{
    protected FeatureFlagService $featureFlagService;

    public function __construct(FeatureFlagService $featureFlagService)
    {
        $this->featureFlagService = $featureFlagService;
    }

    /**
     * Get all feature flags for a context
     * 
     * GET /api/v1/admin/feature-flags?tenant_id=xxx&user_id=yyy
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $tenantId = $request->get('tenant_id');
            $userId = $request->get('user_id');

            $flags = $this->featureFlagService->getAllFlags($tenantId, $userId);

            return response()->json([
                'ok' => true,
                'data' => $flags,
                'context' => [
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'code' => 'SERVER_ERROR',
                'message' => 'Failed to get feature flags',
                'traceId' => $request->header('X-Request-Id', uniqid('req_', true)),
            ], 500);
        }
    }

    /**
     * Enable/disable a feature flag
     * 
     * POST /api/v1/admin/feature-flags/{flag}
     * Body: { enabled: true, tenant_id?: string, user_id?: string }
     */
    public function update(Request $request, string $flag): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'enabled' => 'required|boolean',
                'tenant_id' => 'nullable|string',
                'user_id' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'ok' => false,
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                    'traceId' => $request->header('X-Request-Id', uniqid('req_', true)),
                ], 422);
            }

            $enabled = $request->boolean('enabled');
            $tenantId = $request->get('tenant_id');
            $userId = $request->get('user_id');

            $result = $this->featureFlagService->setEnabled(
                $flag,
                $enabled,
                $tenantId,
                $userId
            );

            if (!$result) {
                return response()->json([
                    'ok' => false,
                    'code' => 'UPDATE_FAILED',
                    'message' => 'Failed to update feature flag',
                    'traceId' => $request->header('X-Request-Id', uniqid('req_', true)),
                ], 500);
            }

            return response()->json([
                'ok' => true,
                'message' => "Feature flag '{$flag}' " . ($enabled ? 'enabled' : 'disabled'),
                'data' => [
                    'flag' => $flag,
                    'enabled' => $enabled,
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'code' => 'SERVER_ERROR',
                'message' => 'Failed to update feature flag',
                'traceId' => $request->header('X-Request-Id', uniqid('req_', true)),
            ], 500);
        }
    }

    /**
     * Get feature flag status for a specific flag
     * 
     * GET /api/v1/admin/feature-flags/{flag}?tenant_id=xxx&user_id=yyy
     */
    public function show(Request $request, string $flag): JsonResponse
    {
        try {
            $tenantId = $request->get('tenant_id');
            $userId = $request->get('user_id');

            $enabled = $this->featureFlagService->isEnabled($flag, $tenantId, $userId);

            return response()->json([
                'ok' => true,
                'data' => [
                    'flag' => $flag,
                    'enabled' => $enabled,
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'code' => 'SERVER_ERROR',
                'message' => 'Failed to get feature flag status',
                'traceId' => $request->header('X-Request-Id', uniqid('req_', true)),
            ], 500);
        }
    }

    /**
     * Clear feature flag cache
     * 
     * DELETE /api/v1/admin/feature-flags/cache?flag=xxx&tenant_id=yyy&user_id=zzz
     */
    public function clearCache(Request $request): JsonResponse
    {
        try {
            $flag = $request->get('flag');
            $tenantId = $request->get('tenant_id');
            $userId = $request->get('user_id');

            $this->featureFlagService->clearCache($flag, $tenantId, $userId);

            return response()->json([
                'ok' => true,
                'message' => 'Feature flag cache cleared',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'code' => 'SERVER_ERROR',
                'message' => 'Failed to clear cache',
                'traceId' => $request->header('X-Request-Id', uniqid('req_', true)),
            ], 500);
        }
    }
}

