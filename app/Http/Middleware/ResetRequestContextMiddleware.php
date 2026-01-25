<?php

namespace App\Http\Middleware;

use App\Services\TenancyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResetRequestContextMiddleware
{
    public function __construct(private TenancyService $tenancyService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->tenancyService->clearTenantContext();

        try {
            app('auth')->forgetGuards();
        } catch (\Throwable $e) {
            // no-op
        }

        return $next($request);
    }
}
