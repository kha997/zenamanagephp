<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class LegacyGoneMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $legacyRoute = $this->getLegacyRoute($request->path());
        
        if ($legacyRoute && $this->shouldReturnGone($legacyRoute)) {
            return $this->createGoneResponse($request, $legacyRoute);
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
     * Check if route should return 410 Gone
     */
    private function shouldReturnGone(array $legacyRoute): bool
    {
        $currentDate = now()->format('Y-m-d');
        
        // Return 410 if we're past the remove date
        return $currentDate >= $legacyRoute['remove_date'];
    }

    /**
     * Create 410 Gone response
     */
    private function createGoneResponse(Request $request, array $legacyRoute): Response
    {
        // Log the 410 response
        Log::warning('Legacy route returned 410 Gone', [
            'legacy_path' => $request->path(),
            'new_path' => $legacyRoute['new_path'],
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'referer' => $request->header('Referer'),
            'timestamp' => now()->toISOString(),
            'response_code' => 410
        ]);
        
        // Create 410 Gone response
        $response = response()->json([
            'error' => [
                'id' => 'req_' . substr(md5(uniqid()), 0, 8),
                'code' => 'E410.GONE',
                'message' => 'This route has been permanently removed',
                'details' => [
                    'legacy_path' => $request->path(),
                    'new_path' => $legacyRoute['new_path'],
                    'removal_date' => $legacyRoute['remove_date'],
                    'reason' => $legacyRoute['reason'],
                    'migration_guide' => '/docs/migration/legacy-routes'
                ]
            ]
        ], 410);
        
        // Add headers
        $response->headers->set('X-Legacy-Gone', 'true');
        $response->headers->set('X-Original-Path', $request->path());
        $response->headers->set('X-New-Path', $legacyRoute['new_path']);
        $response->headers->set('X-Removal-Date', $legacyRoute['remove_date']);
        $response->headers->set('X-Removal-Reason', $legacyRoute['reason']);
        
        return $response;
    }
}
