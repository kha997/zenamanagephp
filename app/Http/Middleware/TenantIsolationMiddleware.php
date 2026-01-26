<?php declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\TenancyService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant Isolation Middleware
 * 
 * Ensures that all database queries are properly scoped to the authenticated user's tenant.
 * This is critical for multi-tenant security.
 */
class TenantIsolationMiddleware
{
    public function __construct(private TenancyService $tenancyService)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->tenancyService->clearTenantContext();

        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
                'message' => 'User not authenticated',
                'code' => 'USER_NOT_AUTHENTICATED'
            ], 401);
        }

        $headerTenantId = $request->header('X-Tenant-ID');
        $headerNorm = trim((string) $headerTenantId);
        $userNorm = trim((string) $user->tenant_id);
        $tenantId = $headerNorm !== '' ? $headerNorm : $userNorm;
        $isAuthMeRoute = in_array(
            $request->path(),
            ['api/auth/me', 'api/zena/auth/me'],
            true
        );

        if (!$tenantId) {
            Log::warning('User without tenant_id attempted to access API', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'No Tenant Access',
                'message' => 'User is not assigned to any tenant',
                'code' => 'NO_TENANT_ACCESS'
            ], 403);
        }

        if ($headerNorm !== '' && $userNorm !== '' && strcasecmp($headerNorm, $userNorm) !== 0) {
            if ($isAuthMeRoute) {
                if ($this->shouldTraceTenantMismatch()) {
                    $traceLine = sprintf(
                        'TENANT_TRACE path=%s user_id=%s user_tenant=%s header_tenant=%s',
                        $request->path(),
                        $user->id,
                        $user->tenant_id,
                        $headerTenantId ?? '(none)'
                    );
                    fwrite(STDERR, $traceLine . PHP_EOL);
                }
                $tenantId = $userNorm;
            } else {
                Log::warning('Tenant header mismatch', [
                    'user_id' => $user->id,
                    'expected_tenant' => $user->tenant_id,
                    'header_tenant' => $headerTenantId
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Tenant mismatch',
                    'message' => 'The X-Tenant-ID header does not match the authenticated tenant',
                    'code' => 'TENANT_MISMATCH'
                ], 401);
            }
        }

        $this->tenancyService->setTenantContext($tenantId, $user->tenant);

        // Add tenant context to request
        $request->attributes->set('tenant_id', $tenantId);
        $request->attributes->set('tenant_user', $user);
        $request->headers->set('X-Tenant-ID', $tenantId);
        $request->merge(['tenant_id' => $tenantId]);
        
        // Log tenant access
        Log::info('Tenant isolation applied', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'ip' => $request->ip()
        ]);
        try {
            return $next($request);
        } finally {
            $this->tenancyService->clearTenantContext();
        }
    }

    private function shouldTraceTenantMismatch(): bool
    {
        return app()->environment('testing') && (bool) env('ZENA_TEST_TRACE');
    }
}
