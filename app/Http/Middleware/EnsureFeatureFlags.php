<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\FeatureFlagService;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureFlags
{
    protected FeatureFlagService $featureFlagService;

    public function __construct(FeatureFlagService $featureFlagService)
    {
        $this->featureFlagService = $featureFlagService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$flags): Response
    {
        $user = $request->user();
        $tenantId = $user ? $user->tenant_id : null;
        $userId = $user ? $user->id : null;

        // Check if all required feature flags are enabled
        foreach ($flags as $flag) {
            if (!$this->featureFlagService->isEnabled($flag, $tenantId, $userId)) {
                return response()->json([
                    'success' => false,
                    'message' => "Feature '{$flag}' is not enabled",
                    'error' => [
                        'id' => 'FEATURE_DISABLED',
                        'code' => 'FEATURE_DISABLED',
                        'message' => "The requested feature is not available"
                    ]
                ], 403);
            }
        }

        return $next($request);
    }
}
