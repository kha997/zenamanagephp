<?php

namespace Tests\Feature;

use Tests\TestCase;

class SimpleAuthTest extends TestCase
{
    public function test_auth_me_requires_authentication(): void
    {
        // This endpoint exists in our API and is protected by auth:sanctum
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }
}
