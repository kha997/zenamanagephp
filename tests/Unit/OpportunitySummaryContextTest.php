<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\Web\CrmPageController;
use App\Models\Account;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\OpportunityAppointment;
use App\Models\Quote;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * The control test for goal #8 anonymization: the context sent to the AI is a
 * strict whitelist. Any new field must be added here deliberately — nothing
 * leaks by default.
 */
class OpportunitySummaryContextTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    public function test_context_contains_exactly_the_whitelisted_keys(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view']);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'display_name' => 'Anh Minh - 0901234567',
        ]);

        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Biệt thự anh Minh Quận 2',
            'service_category' => 'interior',
            'service_scope_summary' => 'Nội thất biệt thự 3 tầng',
            'pipeline_stage' => Opportunity::STAGE_PROPOSAL_SENT,
            'sales_owner_id' => (string) $user->id,
            'created_by' => (string) $user->id,
        ]);

        Lead::query()->create([
            'tenant_id' => (string) $tenant->id,
            'contact_hint' => 'Anh Minh - 0901234567',
            'project_description' => 'Biệt thự 3 tầng cần thiết kế nội thất',
            'source' => 'zalo',
            'status' => Lead::STATUS_CONVERTED,
            'captured_by' => (string) $user->id,
            'converted_opportunity_id' => (string) $opportunity->id,
        ]);

        OpportunityAppointment::query()->create([
            'tenant_id' => (string) $tenant->id,
            'opportunity_id' => (string) $opportunity->id,
            'type' => OpportunityAppointment::TYPE_CONSULTATION,
            'scheduled_at' => '2026-07-15 09:00:00',
            'location' => '12 Trần Não, Quận 2',
            'status' => OpportunityAppointment::STATUS_COMPLETED,
            'outcome_notes' => 'Khách muốn phong cách tối giản',
            'created_by' => (string) $user->id,
        ]);

        Quote::query()->create([
            'tenant_id' => (string) $tenant->id,
            'opportunity_id' => (string) $opportunity->id,
            'quote_number' => Quote::nextNumber((string) $tenant->id),
            'revision_no' => Quote::nextRevision((string) $opportunity->id),
            'status' => Quote::STATUS_DRAFT,
            'total' => 250000000,
            'created_by' => (string) $user->id,
        ]);

        $context = (new CrmPageController())->buildOpportunitySummaryContext($opportunity->fresh());

        // Top level: exactly these keys.
        $this->assertSame(
            ['opportunity', 'lead_origin', 'appointments', 'quotes'],
            array_keys($context)
        );

        // Opportunity block: exact whitelist — opportunity_name MUST be absent.
        $this->assertSame(
            [
                'service_category', 'service_scope_summary', 'pipeline_stage',
                'forecast_category', 'estimated_fee', 'estimated_project_value',
                'probability', 'expected_close_date', 'priority', 'lost_reason', 'created_at',
            ],
            array_keys($context['opportunity'])
        );

        // Lead origin: exact whitelist.
        $this->assertSame(['project_description', 'created_at'], array_keys($context['lead_origin']));

        // Each appointment: exact whitelist — location MUST be absent.
        $this->assertCount(1, $context['appointments']);
        $this->assertSame(
            ['type', 'scheduled_at', 'status', 'outcome_notes'],
            array_keys($context['appointments'][0])
        );

        // Each quote: exact whitelist.
        $this->assertCount(1, $context['quotes']);
        $this->assertSame(
            ['quote_number', 'revision_no', 'status', 'total', 'sent_at', 'decided_at', 'valid_until'],
            array_keys($context['quotes'][0])
        );

        // Belt-and-braces: no identity strings anywhere in the payload.
        $json = (string) json_encode($context, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('Biệt thự anh Minh Quận 2', $json);
        $this->assertStringNotContainsString('0901234567', $json);
        $this->assertStringNotContainsString('12 Trần Não', $json);
    }

    public function test_lead_origin_is_null_without_converted_lead(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view']);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'display_name' => 'Test Account',
        ]);

        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Direct Opp',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $user->id,
            'created_by' => (string) $user->id,
        ]);

        $context = (new CrmPageController())->buildOpportunitySummaryContext($opportunity);

        $this->assertNull($context['lead_origin']);
        $this->assertSame([], $context['appointments']);
        $this->assertSame([], $context['quotes']);
    }
}
