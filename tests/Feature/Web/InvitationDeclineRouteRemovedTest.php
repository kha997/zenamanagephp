<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * GAP-017: the invitation decline link pointed to a controller method that never existed
 * and answered every request with HTTP 500. The route and its legacy redirect were removed.
 */
class InvitationDeclineRouteRemovedTest extends TestCase
{
    public function test_decline_url_returns_not_found(): void
    {
        $this->get('/invitations/decline/some-token')->assertNotFound();
    }

    public function test_legacy_decline_url_returns_not_found(): void
    {
        $this->get('/invite/decline/some-token')->assertNotFound();
    }

    public function test_decline_route_name_is_gone_and_accept_route_remains(): void
    {
        $this->assertFalse(Route::has('invitations.decline'));
        $this->assertTrue(Route::has('invitations.accept'));
    }
}
