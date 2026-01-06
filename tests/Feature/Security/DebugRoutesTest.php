<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class DebugRoutesTest extends TestCase
{
    public function test_debug_login_route_is_gated_in_testing()
    {
        $original = getenv('ZENA_DEBUG_ROUTES');
        $originalEnv = $_ENV['ZENA_DEBUG_ROUTES'] ?? null;
        $originalServer = $_SERVER['ZENA_DEBUG_ROUTES'] ?? null;

        putenv('ZENA_DEBUG_ROUTES=0');
        $_ENV['ZENA_DEBUG_ROUTES'] = '0';
        $_SERVER['ZENA_DEBUG_ROUTES'] = '0';

        $response = $this->postJson('/api/login', [
            'email' => 'client@zena.com',
            'password' => 'zena1234',
        ]);

        $response->assertStatus(404);

        if ($original !== false) {
            putenv("ZENA_DEBUG_ROUTES={$original}");
        } else {
            putenv('ZENA_DEBUG_ROUTES');
        }

        if ($originalEnv !== null) {
            $_ENV['ZENA_DEBUG_ROUTES'] = $originalEnv;
        } else {
            unset($_ENV['ZENA_DEBUG_ROUTES']);
        }

        if ($originalServer !== null) {
            $_SERVER['ZENA_DEBUG_ROUTES'] = $originalServer;
        } else {
            unset($_SERVER['ZENA_DEBUG_ROUTES']);
        }
    }
}
