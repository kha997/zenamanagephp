<?php declare(strict_types=1);

namespace App\Http\Middleware;

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
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
                'message' => 'User not authenticated',
                'code' => 'USER_NOT_AUTHENTICATED'
            ], 401);
        }

        $headerTenantId = trim((string) $request->header('X-Tenant-ID'));
        $isWebPath = $this->isWebPath($request);
        $isApiRequest = $request->is('api/*') || ($request->expectsJson() && !$isWebPath);

        // Ensure user has a tenant_id
        if (!$user->tenant_id) {
            Log::warning('User without tenant_id attempted to access API', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip()
            ]);

            return ErrorEnvelopeService::error(
                'NO_TENANT_ACCESS',
                'User is not assigned to any tenant',
                [],
                403,
                ErrorEnvelopeService::getCurrentRequestId()
            );
        }

        $userTenantId = (string) $user->tenant_id;

        if ($isApiRequest) {
            if ($headerTenantId === '') {
                return ErrorEnvelopeService::error(
                    'TENANT_REQUIRED',
                    'X-Tenant-ID header is required',
                    [],
                    400,
                    ErrorEnvelopeService::getCurrentRequestId()
                );
            }

            if ($userTenantId !== $headerTenantId) {
                $context = [
                    'route' => $request->route()?->getName(),
                    'header_tenant' => $headerTenantId,
                    'user_tenant' => $userTenantId,
                    'user_id' => $user->id,
                    'token_prefix' => substr((string) $request->bearerToken(), 0, 5),
                    'request_type' => 'api'
                ];

                Log::info('Tenant mismatch debug', $context);
                Log::warning('Tenant mismatch', array_merge($context, [
                    'ip' => $request->ip(),
                ]));

                return ErrorEnvelopeService::error(
                    'TENANT_INVALID',
                    'X-Tenant-ID does not match authenticated user',
                    [],
                    403,
                    ErrorEnvelopeService::getCurrentRequestId()
                );
            }

            $tenantId = $headerTenantId;
        } else {
            $sessionTenantId = trim((string) $request->session()->get('tenant_id', ''));

            if ($sessionTenantId !== '' && $sessionTenantId !== $userTenantId) {
                $context = [
                    'route' => $request->route()?->getName(),
                    'session_tenant' => $sessionTenantId,
                    'user_tenant' => $userTenantId,
                    'user_id' => $user->id,
                    'request_type' => 'web'
                ];

                Log::info('Tenant session mismatch debug', $context);
                Log::warning('Tenant session mismatch', array_merge($context, [
                    'ip' => $request->ip(),
                ]));

                return ErrorEnvelopeService::error(
                    'TENANT_INVALID',
                    'Session tenant does not match authenticated user',
                    [],
                    403,
                    ErrorEnvelopeService::getCurrentRequestId()
                );
            }

            if ($headerTenantId !== '' && $userTenantId !== $headerTenantId) {
                $context = [
                    'route' => $request->route()?->getName(),
                    'header_tenant' => $headerTenantId,
                    'user_tenant' => $userTenantId,
                    'user_id' => $user->id,
                    'request_type' => 'web'
                ];

                Log::info('Tenant mismatch debug', $context);
                Log::warning('Tenant mismatch', array_merge($context, [
                    'ip' => $request->ip(),
                ]));

                return ErrorEnvelopeService::error(
                    'TENANT_INVALID',
                    'X-Tenant-ID does not match authenticated user',
                    [],
                    403,
                    ErrorEnvelopeService::getCurrentRequestId()
                );
            }

            $tenantId = $sessionTenantId !== '' ? $sessionTenantId : $userTenantId;

            if ($request->hasSession()) {
                $request->session()->put('tenant_id', $tenantId);
            }
        }

        // Set tenant context globally
        app()->instance('current_tenant_id', $tenantId);
        app()->instance('tenant', $user->tenant);

        // Add tenant context to request
        $request->attributes->set('tenant_id', $tenantId);
        $request->attributes->set('tenant_user', $user);
        $request->merge(['tenant_id' => $tenantId]);
        
        // Log tenant access
        Log::info('Tenant isolation applied', [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'ip' => $request->ip()
        ]);
        
        return $next($request);
    }

    protected function isWebPath(Request $request): bool
    {
        $path = trim((string) $request->path(), '/');

        if (
            $path === ''
            || str_starts_with($path, 'admin')
            || str_starts_with($path, 'app')
            || str_starts_with($path, '_debug')
            || str_starts_with($path, 'test')
            || $request->is('login')
            || $request->is('logout')
        ) {
            return true;
        }

        $routeName = (string) ($request->route()?->getName() ?? '');

        if ($routeName !== '') {
            return str_starts_with($routeName, 'admin-')
                || str_starts_with($routeName, 'admin.dashboard')
                || str_starts_with($routeName, 'app.')
                || str_starts_with($routeName, 'debug.');
        }

        return false;
    }
}
