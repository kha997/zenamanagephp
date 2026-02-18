<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\SupportDocumentation;
use App\Models\SupportTicket;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Traits\AuthenticationTrait;
use Tests\Traits\RouteNameTrait;
use Tests\TestCase;

class SupportTenantIsolationTest extends TestCase
{
    use RefreshDatabase, AuthenticationTrait, RouteNameTrait;

    public function test_support_ticket_store_ignores_payload_tenant_id(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $user = $this->createRbacAdminUser($tenantA);

        $payload = [
            'tenant_id' => $tenantB->id,
            'subject' => 'Customer question',
            'description' => 'Need help with billing codes',
            'category' => 'billing',
            'priority' => 'medium',
        ];

        $response = $this->apiAs($user, $tenantA)
            ->postJson($this->namedRoute('api.support.tickets.store'), $payload);

        $response->assertStatus(201)
            ->assertJsonPath('tenant_id', $tenantA->id);

        $this->assertDatabaseHas('support_tickets', [
            'tenant_id' => $tenantA->id,
            'subject' => 'Customer question',
        ]);
    }

    public function test_support_ticket_cross_tenant_returns_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $owner = $this->createRbacAdminUser($tenantA);
        $ticket = SupportTicket::create([
            'tenant_id' => $tenantA->id,
            'ticket_number' => SupportTicket::generateTicketNumber($tenantA->id),
            'user_id' => $owner->id,
            'subject' => 'Tenant A issue',
            'description' => 'Issue details',
            'category' => 'technical',
            'priority' => 'high',
            'status' => 'open',
        ]);

        $crossTenantUser = $this->createRbacAdminUser($tenantB);

        $response = $this->apiAs($crossTenantUser, $tenantB)
            ->getJson($this->namedRoute('api.support.tickets.show', ['ticket' => $ticket->id]));

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'E404.NOT_FOUND');
    }

    public function test_support_ticket_assign_outside_tenant_rejected(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $owner = $this->createRbacAdminUser($tenantA);
        $assignee = $this->createRbacAdminUser($tenantB);

        $ticket = SupportTicket::create([
            'tenant_id' => $tenantA->id,
            'ticket_number' => SupportTicket::generateTicketNumber($tenantA->id),
            'user_id' => $owner->id,
            'subject' => 'Tenant A assignment',
            'description' => 'Assignment test',
            'category' => 'technical',
            'priority' => 'high',
            'status' => 'open',
        ]);

        $response = $this->apiAs($owner, $tenantA)
            ->putJson($this->namedRoute('api.support.tickets.update', ['ticket' => $ticket->id]), [
                'status' => 'open',
                'assigned_to' => $assignee->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'E422.VALIDATION')
            ->assertJsonPath('error.details.validation.assigned_to.0', 'Assigned user not found in tenant')
            ->assertJsonPath('errors.assigned_to.0', 'Assigned user not found in tenant');
    }

    public function test_support_documentation_search_scoped(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $authorA = $this->createRbacAdminUser($tenantA);
        $authorB = $this->createRbacAdminUser($tenantB);

        SupportDocumentation::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenantA->id,
            'title' => 'Tenant A guide',
            'slug' => 'tenant-a-guide',
            'content' => 'Tenant A content',
            'category' => 'getting_started',
            'status' => 'published',
            'tags' => 'tenant,guide',
            'author_id' => $authorA->id,
        ]);

        SupportDocumentation::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenantB->id,
            'title' => 'Tenant B guide',
            'slug' => 'tenant-b-guide',
            'content' => 'Tenant B content',
            'category' => 'api',
            'status' => 'published',
            'tags' => 'tenant,api',
            'author_id' => $authorB->id,
        ]);

        $response = $this->apiAs($authorA, $tenantA)
            ->getJson('/api/support/documentation/search?q=guide');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tenant_id', $tenantA->id);
    }

}
