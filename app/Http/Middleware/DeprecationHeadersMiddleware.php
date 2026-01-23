<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use App\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class DeprecationHeadersMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('Deprecation', 'true');
        $response->headers->set('X-API-Legacy', '1');

        $sunset = config('api_migration.sunset');
        if ($sunset) {
            $response->headers->set('Sunset', $sunset);
        }

        $docsUrl = config('api_migration.docs_url');
        if ($docsUrl) {
            $response->headers->set('Link', '<'.$docsUrl.'>; rel="deprecation"');
        }

        $this->logLegacyTrafficIfRequired($request, $response);

        return $response;
    }

    private function logLegacyTrafficIfRequired(Request $request, $response): void
    {
        if (! config('api_migration.log_legacy_traffic')) {
            return;
        }

        $sampleRate = (float) config('api_migration.log_sample_rate', 1.0);
        if ($sampleRate <= 0.0) {
            return;
        }

        if (! $this->shouldLogSample($sampleRate)) {
            return;
        }

        $route = $request->route();
        $user = Auth::user();

        Log::info('api.legacy_traffic', [
            'path' => $request->getPathInfo(),
            'method' => $request->method(),
            'route_name' => $route ? $route->getName() : null,
            'controller_action' => $route ? $route->getActionName() : null,
            'status_code' => $response->getStatusCode(),
            'user_id' => $user ? $user->getAuthIdentifier() : null,
            'tenant_id' => $user ? data_get($user, 'tenant_id') : null,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $request->header('X-Request-Id') ?? $request->header('X-Request-ID'),
        ]);
    }

    private function shouldLogSample(float $sampleRate): bool
    {
        $rate = min(1.0, max(0.0, $sampleRate));
        if ($rate >= 1.0) {
            return true;
        }

        $scale = 1_000_000;
        return random_int(0, $scale - 1) / $scale < $rate;
    }
}
