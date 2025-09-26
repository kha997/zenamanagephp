<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class LegacyRouteMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        // Check if this is a legacy route
        $legacyRoute = $this->getLegacyRoute($request->path());
        
        if ($legacyRoute) {
            $this->addDeprecationHeaders($response, $legacyRoute);
            $this->logLegacyUsage($request, $legacyRoute);
        }
        
        return $response;
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
     * Add deprecation headers to response
     */
    private function addDeprecationHeaders(Response $response, array $legacyRoute): void
    {
        $currentDate = now()->format('Y-m-d');
        
        // Add deprecation header
        $response->headers->set('Deprecation', 'true');
        
        // Add sunset header with removal date
        $response->headers->set('Sunset', $legacyRoute['remove_date']);
        
        // Add link header pointing to new route
        $response->headers->set('Link', '<' . $legacyRoute['new_path'] . '>; rel="successor-version"');
        
        // Add custom headers for migration info
        $response->headers->set('X-Legacy-Route', 'true');
        $response->headers->set('X-New-Route', $legacyRoute['new_path']);
        $response->headers->set('X-Migration-Reason', $legacyRoute['reason']);
        
        // Add phase-specific headers
        if ($currentDate < $legacyRoute['redirect_date']) {
            $response->headers->set('X-Migration-Phase', 'announce');
            $response->headers->set('X-Redirect-Date', $legacyRoute['redirect_date']);
        } elseif ($currentDate < $legacyRoute['remove_date']) {
            $response->headers->set('X-Migration-Phase', 'redirect');
            $response->headers->set('X-Remove-Date', $legacyRoute['remove_date']);
        } else {
            $response->headers->set('X-Migration-Phase', 'remove');
        }
    }

    /**
     * Log legacy route usage
     */
    private function logLegacyUsage(Request $request, array $legacyRoute): void
    {
        Log::info('Legacy route accessed', [
            'legacy_path' => $request->path(),
            'new_path' => $legacyRoute['new_path'],
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'referer' => $request->header('Referer'),
            'timestamp' => now()->toISOString(),
            'migration_phase' => $this->getCurrentPhase($legacyRoute)
        ]);
    }

    /**
     * Get current migration phase
     */
    private function getCurrentPhase(array $legacyRoute): string
    {
        $currentDate = now()->format('Y-m-d');
        
        if ($currentDate < $legacyRoute['redirect_date']) {
            return 'announce';
        } elseif ($currentDate < $legacyRoute['remove_date']) {
            return 'redirect';
        } else {
            return 'remove';
        }
    }
}
