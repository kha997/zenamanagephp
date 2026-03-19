<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegacyDebugRootRedirectTest extends TestCase
{
    public function test_active_legacy_debug_root_get_routes_permanently_redirect_to_mounted_debug_targets(): void
    {
        $redirects = [
            '/dashboard-data' => '/_debug/dashboard-data',
            '/test-api-admin-dashboard' => '/_debug/test-api-admin-stats',
            '/test-permissions' => '/_debug/test-permissions',
            '/test-api-admin-stats' => '/_debug/test-api-admin-stats',
            '/test-session-auth' => '/_debug/test-session-auth',
            '/test-login/superadmin@zena.com' => '/_debug/test-login/superadmin@zena.com',
        ];

        foreach ($redirects as $from => $to) {
            $response = $this->get($from);

            $response->assertStatus(301);
            $response->assertRedirect($to);
        }
    }

    public function test_post_only_debug_login_helper_no_longer_exposes_a_broken_root_get_redirect(): void
    {
        $this->get('/test-login-simple')->assertNotFound();
    }
}
