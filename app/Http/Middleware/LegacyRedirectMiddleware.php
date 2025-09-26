<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class LegacyRedirectMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $legacyRoute = $this->getLegacyRoute($request->path());
        
        if ($legacyRoute && $this->shouldRedirect($legacyRoute)) {
            return $this->createRedirectResponse($request, $legacyRoute);
        }
        
        return $next($request);
    }

    /**
     * Get legacy route information
     */
    private function getLegacyRoute(string $path): ?array
    {
        $legacyRoutes = [
            '/dashboard' => [
                'new_path' => '/app/dashboard',
                'announce_date' => '2024-12-20',
                'redirect_date' => '2024-12-27',
                'remove_date' => '2025-01-10',
                'reason' => 'Standardize app routes'
            ],
            '/projects' => [
                'new_path' => '/app/projects',
                'announce_date' => '2024-12-20',
                'redirect_date' => '2024-12-27',
                'remove_date' => '2025-01-10',
                'reason' => 'Standardize app routes'
            ],
            '/tasks' => [
                'new_path' => '/app/tasks',
                'announce_date' => '2024-12-20',
                'redirect_date' => '2024-12-27',
                'remove_date' => '2025-01-10',
                'reason' => 'Standardize app routes'
            ]
        ];

        return $legacyRoutes[$path] ?? null;
    }

    /**
     * Check if route should be redirected
     */
    private function shouldRedirect(array $legacyRoute): bool
    {
        $currentDate = now()->format('Y-m-d');
        
        // Redirect if we're in the redirect phase
        return $currentDate >= $legacyRoute['redirect_date'] && $currentDate < $legacyRoute['remove_date'];
    }

    /**
     * Create redirect response
     */
    private function createRedirectResponse(Request $request, array $legacyRoute): Response
    {
        $newUrl = $legacyRoute['new_path'];
        
        // Preserve query parameters
        if ($request->getQueryString()) {
            $newUrl .= '?' . $request->getQueryString();
        }
        
        // Log the redirect
        Log::info('Legacy route redirected', [
            'legacy_path' => $request->path(),
            'new_path' => $newUrl,
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'referer' => $request->header('Referer'),
            'timestamp' => now()->toISOString(),
            'redirect_type' => '301'
        ]);
        
        // Create 301 permanent redirect
        return redirect($newUrl, 301)
            ->header('X-Legacy-Redirect', 'true')
            ->header('X-Original-Path', $request->path())
            ->header('X-Redirect-Reason', $legacyRoute['reason']);
    }
}
