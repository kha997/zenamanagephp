<?php

namespace Tests\Feature;

use Tests\TestCase;

class UnmountedApiDemoSurfaceTest extends TestCase
{
    public function test_api_demo_route_is_not_mounted(): void
    {
        $this->get('/api-demo')->assertStatus(404);
    }
}
