<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class ApiProtectionTest extends TestCase
{
    public function test_analytics_dashboard_requires_authentication()
    {
        $response = $this->getJson('/api/analytics/dashboard');

        $response->assertStatus(401);
    }
}
