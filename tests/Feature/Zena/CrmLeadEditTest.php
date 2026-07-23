<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * User-reported gap: http://127.0.0.1:8000/operator/crm/leads offered no way
 * to edit an existing lead — Api\LeadController had no update() method at
 * all (only index/store/convert/discard), even though LeadPolicy::update()
 * was already fully implemented and unused. This closes that gap.
 */
class CrmLeadEditTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser(
            $this->tenant,
            [],
            ['admin'],
            ['crm.view', 'crm.manage']
        );
    }

    private function createLead(array $overrides = []): Lead
    {
        return Lead::query()->create(array_merge([
            'tenant_id' => (string) $this->tenant->id,
            'contact_hint' => 'Anh Cu - 090xxxxxxx',
            'project_description' => 'Mo ta cu',
            'source' => 'zalo',
            'status' => Lead::STATUS_NEW,
            'captured_by' => (string) $this->user->id,
        ], $overrides));
    }

    public function test_leads_page_shows_edit_action_for_new_lead(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $this->createLead();

        $this->actingAs($this->user)
            ->get(route('operator.crm.leads'), $headers)
            ->assertOk()
            ->assertSee('Sửa');
    }

    public function test_user_can_edit_a_new_lead(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $lead = $this->createLead();

        // Prime the session/CSRF token with a real GET first -- see
        // tests/TestCase.php:70-72 (ensureCsrfToken requires an established
        // session, not a synthesized one), matching the pattern already used
        // throughout tests/Feature/Zena/OperatorCrmUiTest.php.
        $this->actingAs($this->user)->get(route('operator.crm.leads'), $headers);

        $response = $this->actingAs($this->user)
            ->put(route('operator.crm.leads.update', $lead->id), [
                'contact_hint' => 'Anh Cu - 091yyyyyyy (da sua)',
                'project_description' => 'Mo ta da chinh sua',
                'source' => 'hotline',
            ], $headers);

        $response->assertRedirect(route('operator.crm.leads'));
        $response->assertSessionHas('success', 'Đã cập nhật lead');

        $lead->refresh();
        $this->assertSame('Anh Cu - 091yyyyyyy (da sua)', $lead->contact_hint);
        $this->assertSame('Mo ta da chinh sua', $lead->project_description);
        $this->assertSame('hotline', (string) $lead->source);
    }

    public function test_edit_requires_contact_hint(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $lead = $this->createLead();

        // Prime the session/CSRF token with a real GET first -- see
        // tests/TestCase.php:70-72 (ensureCsrfToken requires an established
        // session, not a synthesized one), matching the pattern already used
        // throughout tests/Feature/Zena/OperatorCrmUiTest.php.
        $this->actingAs($this->user)->get(route('operator.crm.leads'), $headers);

        $response = $this->actingAs($this->user)
            ->put(route('operator.crm.leads.update', $lead->id), [
                'contact_hint' => '',
            ], $headers);

        $response->assertSessionHasErrors('contact_hint');
        $lead->refresh();
        $this->assertSame('Anh Cu - 090xxxxxxx', $lead->contact_hint);
    }

    public function test_converted_lead_cannot_be_edited(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $lead = $this->createLead(['status' => Lead::STATUS_CONVERTED]);

        // Prime the session/CSRF token with a real GET first -- see
        // tests/TestCase.php:70-72 (ensureCsrfToken requires an established
        // session, not a synthesized one), matching the pattern already used
        // throughout tests/Feature/Zena/OperatorCrmUiTest.php.
        $this->actingAs($this->user)->get(route('operator.crm.leads'), $headers);

        $response = $this->actingAs($this->user)
            ->put(route('operator.crm.leads.update', $lead->id), [
                'contact_hint' => 'Should not apply',
            ], $headers);

        $response->assertStatus(302);
        $lead->refresh();
        $this->assertSame('Anh Cu - 090xxxxxxx', $lead->contact_hint);
    }

    public function test_discarded_lead_has_no_edit_action_on_the_page(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $this->createLead(['status' => Lead::STATUS_DISCARDED]);

        $this->actingAs($this->user)
            ->get(route('operator.crm.leads'), $headers)
            ->assertOk()
            ->assertDontSee('Sửa');
    }

    public function test_edit_denied_without_manage_permission(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $lead = $this->createLead();
        $viewer = $this->createTenantUser($this->tenant, [], ['crm_viewer'], ['crm.view']);

        $this->actingAs($viewer)->get(route('operator.crm.leads'), $headers);

        $response = $this->actingAs($viewer)
            ->put(route('operator.crm.leads.update', $lead->id), [
                'contact_hint' => 'Should be denied',
            ], $headers);

        $response->assertStatus(302);
        $lead->refresh();
        $this->assertSame('Anh Cu - 090xxxxxxx', $lead->contact_hint);
    }

    public function test_edit_denied_across_tenants(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = $this->createTenantUser($otherTenant, [], ['admin'], ['crm.view', 'crm.manage']);
        $lead = $this->createLead();
        $otherHeaders = ['X-Tenant-ID' => (string) $otherTenant->id];

        $this->actingAs($otherUser)->get(route('operator.crm.leads'), $otherHeaders);

        $response = $this->actingAs($otherUser)
            ->put(route('operator.crm.leads.update', $lead->id), [
                'contact_hint' => 'Cross tenant edit attempt',
            ], $otherHeaders);

        // The Web layer (DelegatesToApiControllers::handleErrorResponse) turns
        // any non-2xx API response, including the API's raw 404, into a 302
        // redirect-back with a flash error -- it never passes a 404 status
        // through to the browser. See app/Http/Controllers/Web/Concerns/DelegatesToApiControllers.php.
        $response->assertStatus(302);
        $lead->refresh();
        $this->assertSame('Anh Cu - 090xxxxxxx', $lead->contact_hint);
    }
}
