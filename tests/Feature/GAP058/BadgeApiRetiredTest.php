<?php

namespace Tests\Feature\GAP058;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * GAP-058: the sidebar badge API was dead (no rendered caller, hard-coded 0,
 * placeholder token, every action 500) and the Owner chose not to have menu
 * badges now, so the surface is retired and must not come back unnoticed.
 */
final class BadgeApiRetiredTest extends TestCase
{
    public function test_no_badge_routes_are_registered(): void
    {
        $offenders = [];
        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            if (str_starts_with($route->uri(), 'api/badges') || str_starts_with($name, 'api.badges.')) {
                $offenders[] = $route->uri() . ' (' . $name . ')';
            }
        }

        $this->assertSame([], $offenders);
    }

    public function test_badge_classes_and_view_are_gone(): void
    {
        $this->assertFalse(class_exists('App\\Http\\Controllers\\Api\\BadgeController'));
        $this->assertFalse(class_exists('App\\Services\\BadgeService'));
        $this->assertFalse(class_exists('App\\View\\Components\\Sidebar'));
        $this->assertFalse(view()->exists('components.sidebar'));
    }

    public function test_nothing_references_the_retired_badge_surface(): void
    {
        $root = dirname(__DIR__, 3);
        $offenders = [];
        foreach (['app', 'resources', 'routes'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (!preg_match('/\.php$/', $file->getFilename())) {
                    continue;
                }
                $source = (string) file_get_contents($file->getPathname());
                if (str_contains($source, 'BadgeService') || str_contains($source, 'BadgeController') || str_contains($source, '/api/badges')) {
                    $offenders[] = substr($file->getPathname(), strlen($root) + 1);
                }
            }
        }

        $this->assertSame([], $offenders);
    }
}
