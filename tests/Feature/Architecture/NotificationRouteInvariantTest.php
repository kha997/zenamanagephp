<?php declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationRouteInvariantTest extends TestCase
{
    public function test_notification_v1_routes_stay_mounted_once_without_prefix_drift(): void
    {
        $routes = collect(Route::getRoutes())->filter(function ($route) {
            $uri = $route->uri();

            return Str::startsWith($uri, 'api/v1/notifications')
                || Str::startsWith($uri, 'api/v1/notification-rules');
        })->values();

        $this->assertCount(18, $routes, 'Expected the current Notification compatibility surface to stay mounted.');

        $duplicateKeys = $routes
            ->map(fn ($route) => implode('|', array_diff($route->methods(), ['HEAD'])) . ' ' . $route->uri())
            ->duplicates()
            ->values()
            ->all();

        $prefixDrift = $routes
            ->map(fn ($route) => $route->uri())
            ->filter(fn (string $uri) => Str::contains($uri, ['api' . '/v1/api' . '/v1', 'v1/v1']))
            ->values()
            ->all();

        $this->assertSame([], $duplicateKeys, 'Notification compatibility routes must not duplicate METHOD+URI pairs.');
        $this->assertSame([], $prefixDrift, 'Notification compatibility routes must not drift into double prefixes.');
    }
}
