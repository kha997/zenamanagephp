<?php declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EVIDENCE TEST — not a regression guard, not shipped as a permanent suite member.
 *
 * Verifies AUD-04 from docs/audits/2026-07-23-end-to-end-operational-audit.md:
 * "SupportTicket không có Policy đăng ký... authorize()/can() luôn thất bại".
 *
 * IMPORTANT CORRECTION found during verification: the class with the broken
 * authorize()/can('update', $ticket) calls is app/Http/Controllers/SupportTicketController.php
 * (non-Api) — grep confirms it is NOT mounted in any route file (routes/api.php,
 * routes/web.php). The LIVE, routed controller is
 * app/Http/Controllers/Api/SupportTicketController.php (routes/api.php:241-244,
 * name prefix api.support.tickets.*), whose update() method does NOT call
 * authorize()/can() at all — it only compares $ticket->tenant_id to the request's
 * tenant. The route group's only gate is the bare `rbac` middleware (no
 * permission string), which — per RoleBasedAccessControlMiddleware::handleGeneralAccess()
 * — only requires the user to hold ANY role from a fixed list (super_admin,
 * admin, project_manager, team_member, client, viewer, designer, site_engineer,
 * qc_engineer, qc_inspector, procurement, finance). There is no ticket-ownership
 * or ticket-specific-permission check on the live path at all.
 *
 * This test proves the LIVE behavior: a user who did not create a ticket, and
 * holds only the lowest-privilege listed role ("viewer"), can update/close ANY
 * ticket in their own tenant via PUT /api/support/tickets/{ticket}.
 */
class AudSupportTicketAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_owner_non_assignee_non_admin_cannot_update_a_ticket(): void
    {
        $tenant = Tenant::factory()->create();

        $ticketOwner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
        $unrelatedViewer = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'viewer']);

        $ticket = SupportTicket::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $ticketOwner->id,
            'status' => 'open',
        ]);

        $response = $this->actingAs($unrelatedViewer, 'sanctum')
            ->withHeaders(['X-Tenant-ID' => (string) $tenant->id])
            ->putJson("/api/support/tickets/{$ticket->id}", [
                'status' => 'closed',
                'assigned_to' => null,
            ]);

        $response->assertStatus(403);
        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
    }

    public function test_owner_can_update_their_own_ticket(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'viewer']);

        $ticket = SupportTicket::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'status' => 'open',
        ]);

        $response = $this->actingAs($owner, 'sanctum')
            ->withHeaders(['X-Tenant-ID' => (string) $tenant->id])
            ->putJson("/api/support/tickets/{$ticket->id}", [
                'status' => 'closed',
                'assigned_to' => null,
            ]);

        $response->assertStatus(200);
    }

    public function test_assignee_can_update_a_ticket_assigned_to_them(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
        $assignee = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'viewer']);

        $ticket = SupportTicket::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'assigned_to' => $assignee->id,
            'status' => 'open',
        ]);

        $response = $this->actingAs($assignee, 'sanctum')
            ->withHeaders(['X-Tenant-ID' => (string) $tenant->id])
            ->putJson("/api/support/tickets/{$ticket->id}", [
                'status' => 'closed',
                'assigned_to' => $assignee->id,
            ]);

        $response->assertStatus(200);
    }

    public function test_update_without_assigned_to_key_in_payload_does_not_crash(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'viewer']);

        $ticket = SupportTicket::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'status' => 'open',
        ]);

        // Deliberately omit 'assigned_to' entirely -- validation allows it
        // (nullable), the pre-fix controller crashed with "Undefined array key".
        $response = $this->actingAs($owner, 'sanctum')
            ->withHeaders(['X-Tenant-ID' => (string) $tenant->id])
            ->putJson("/api/support/tickets/{$ticket->id}", [
                'status' => 'closed',
            ]);

        $response->assertStatus(200);
    }

    public function test_the_broken_authorize_controller_is_unreachable_dead_code(): void
    {
        $reflection = new \ReflectionClass(\App\Http\Controllers\SupportTicketController::class);
        $this->assertTrue($reflection->hasMethod('update'));

        $liveRoutes = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->filter(fn ($route) => str_contains((string) $route->getActionName(), 'App\\Http\\Controllers\\SupportTicketController@'))
            ->count();

        fwrite(STDERR, "\n[AUD-04 EVIDENCE] Routes pointing at App\\Http\\Controllers\\SupportTicketController (the one with broken authorize() calls): {$liveRoutes}\n");
        fwrite(STDERR, '[AUD-04 EVIDENCE] ' . ($liveRoutes === 0
            ? "CONFIRMED: 0 routes — this controller is dead code, its authorize()-always-fails defect is not reachable in production.\n"
            : "NOT dead — {$liveRoutes} route(s) found, original hypothesis may still apply directly.\n"));

        $this->assertSame(0, $liveRoutes, 'If this fails, the non-Api SupportTicketController IS routed and the always-deny defect is live.');
    }
}
