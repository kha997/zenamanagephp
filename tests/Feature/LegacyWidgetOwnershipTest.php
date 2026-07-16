<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Dashboard;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Widget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test for a system-audit finding: /api/widgets (store/update/destroy)
 * has no tenant.isolation or rbac:* middleware. Confirms this is NOT an exploitable
 * IDOR — WidgetController checks $widget->user_id === $user->id (and, for store,
 * $dashboard->user_id === $user->id) directly in the controller body, independent
 * of tenant middleware. A different user — even one in a different tenant — cannot
 * read, mutate, or delete another user's widget.
 */
class LegacyWidgetOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_denied_for_a_different_users_widget_in_the_same_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => (string) $tenant->id]);
        $otherUser = User::factory()->create(['tenant_id' => (string) $tenant->id]);

        $dashboard = Dashboard::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'user_id' => (string) $owner->id,
        ]);

        $widget = Widget::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'dashboard_id' => (string) $dashboard->id,
            'user_id' => (string) $owner->id,
        ]);

        $response = $this->actingAs($otherUser)
            ->putJson("/api/widgets/{$widget->id}", ['title' => 'Hijacked title']);

        $response->assertForbidden();

        $widget->refresh();
        $this->assertNotSame('Hijacked title', $widget->name);
    }

    public function test_destroy_denied_for_a_different_users_widget_across_tenants(): void
    {
        $ownerTenant = Tenant::factory()->create();
        $attackerTenant = Tenant::factory()->create();

        $owner = User::factory()->create(['tenant_id' => (string) $ownerTenant->id]);
        $attacker = User::factory()->create(['tenant_id' => (string) $attackerTenant->id]);

        $dashboard = Dashboard::factory()->create([
            'tenant_id' => (string) $ownerTenant->id,
            'user_id' => (string) $owner->id,
        ]);

        $widget = Widget::factory()->create([
            'tenant_id' => (string) $ownerTenant->id,
            'dashboard_id' => (string) $dashboard->id,
            'user_id' => (string) $owner->id,
        ]);

        $response = $this->actingAs($attacker)
            ->deleteJson("/api/widgets/{$widget->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('widgets', ['id' => (string) $widget->id, 'deleted_at' => null]);
    }

    public function test_store_denied_when_dashboard_belongs_to_another_user(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => (string) $tenant->id]);
        $attacker = User::factory()->create(['tenant_id' => (string) $tenant->id]);

        $dashboard = Dashboard::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'user_id' => (string) $owner->id,
        ]);

        $response = $this->actingAs($attacker)->postJson('/api/widgets', [
            'dashboard_id' => (string) $dashboard->id,
            'type' => 'chart',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('widgets', ['dashboard_id' => (string) $dashboard->id]);
    }

    public function test_update_allowed_for_the_widgets_own_owner(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => (string) $tenant->id]);

        $dashboard = Dashboard::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'user_id' => (string) $owner->id,
        ]);

        $widget = Widget::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'dashboard_id' => (string) $dashboard->id,
            'user_id' => (string) $owner->id,
        ]);

        $response = $this->actingAs($owner)
            ->putJson("/api/widgets/{$widget->id}", ['title' => 'Legit rename']);

        $response->assertOk();

        $widget->refresh();
        $this->assertSame('Legit rename', $widget->name);
    }
}
