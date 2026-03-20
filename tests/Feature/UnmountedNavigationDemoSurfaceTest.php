<?php

namespace Tests\Feature;

use Tests\TestCase;

class UnmountedNavigationDemoSurfaceTest extends TestCase
{
    public function test_navigation_demo_route_is_not_mounted(): void
    {
        $this->get('/navigation-demo')->assertStatus(404);
    }

    public function test_debug_navigation_demo_route_is_not_mounted(): void
    {
        $this->get('/_debug/navigation-demo')->assertStatus(404);
    }
}
