<?php declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\ErrorEnvelopeService;
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
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return ErrorEnvelopeService::authenticationError(
                'User not authenticated',
                ErrorEnvelopeService::getCurrentRequestId()
            );
        }

        $headerTenantId = trim((string) $request->header('X-Tenant-ID', ''));
        $userTenantId = $user->tenant_id ? (string) $user->tenant_id : '';

        $tenantId = $headerTenantId !== '' ? $headerTenantId : $userTenantId;

        if ($tenantId === '') {
            return ErrorEnvelopeService::error(
                'TENANT_REQUIRED',
                'X-Tenant-ID header is required',
                [],
                400,
                ErrorEnvelopeService::getCurrentRequestId()
            );
        }

        if ($headerTenantId !== '' && $userTenantId !== '' && $headerTenantId !== $userTenantId) {
            $context = [
                'route' => $request->route()?->getName(),
                'header_tenant' => $headerTenantId,
                'user_tenant' => $userTenantId,
                'user_id' => $user->id,
                'token_prefix' => substr((string) $request->bearerToken(), 0, 5),
                'method' => $request->method(),
                'request_type' => $request->is('api/*') ? 'api' : 'web'
            ];

            Log::info('Tenant mismatch debug', $context);
            Log::warning('Tenant mismatch', array_merge($context, [
                'ip' => $request->ip(),
            ]));

            return ErrorEnvelopeService::error(
                'TENANT_INVALID',
                'X-Tenant-ID does not match authenticated user',
                [],
                Response::HTTP_FORBIDDEN,
                ErrorEnvelopeService::getCurrentRequestId()
            );
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return ErrorEnvelopeService::error(
                'TENANT_INVALID',
                'Tenant not found',
                [],
                404,
                ErrorEnvelopeService::getCurrentRequestId()
            );
        }

        app()->instance('current_tenant_id', $tenantId);
        app()->instance('tenant', $tenant);

        $request->attributes->set('tenant_id', $tenantId);
        $request->attributes->set('tenant_user', $user);

        Log::info('Tenant isolation applied', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'ip' => $request->ip()
        ]);

        return $next($request);
    }

}
