<?php declare(strict_types=1);

namespace Tests\Unit\Services\Crm;

use App\Models\Account;
use App\Models\EventRecord;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Services\Crm\OpportunityStageTransitionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class OpportunityStageTransitionServiceTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private function makeOpportunity(Tenant $tenant, array $overrides = []): Opportunity
    {
        $manager = $this->createTenantUser($tenant, [], ['admin'], ['crm.manage']);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khách hàng test',
            'status' => Account::STATUS_ACTIVE,
        ]);

        return Opportunity::query()->create(array_merge([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Cơ hội test',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $manager->id,
            'created_by' => (string) $manager->id,
        ], $overrides));
    }

    public function test_transition_updates_stage_and_records_event(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createTenantUser($tenant, [], ['admin'], ['crm.manage']);
        $opportunity = $this->makeOpportunity($tenant);

        $updated = (new OpportunityStageTransitionService())->transition(
            $actor,
            $opportunity,
            Opportunity::STAGE_QUALIFIED,
            null
        );

        $this->assertSame(Opportunity::STAGE_QUALIFIED, $updated->pipeline_stage);
        $this->assertDatabaseHas('event_records', [
            'aggregate_id' => (string) $opportunity->id,
            'event_key' => 'crm.opportunity.stage_changed',
        ]);
    }

    public function test_transition_rejects_actor_without_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createTenantUser($tenant, [], ['team_member'], ['crm.view']);
        $opportunity = $this->makeOpportunity($tenant);

        $this->expectException(AuthorizationException::class);

        (new OpportunityStageTransitionService())->transition(
            $actor,
            $opportunity,
            Opportunity::STAGE_QUALIFIED,
            null
        );
    }

    public function test_transition_rejects_actor_from_another_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $actorFromB = $this->createTenantUser($tenantB, [], ['admin'], ['crm.manage']);
        $opportunity = $this->makeOpportunity($tenantA);

        $this->expectException(AuthorizationException::class);

        (new OpportunityStageTransitionService())->transition(
            $actorFromB,
            $opportunity,
            Opportunity::STAGE_QUALIFIED,
            null
        );
    }

    public function test_transition_rejects_invalid_stage(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createTenantUser($tenant, [], ['admin'], ['crm.manage']);
        $opportunity = $this->makeOpportunity($tenant);

        $this->expectException(ValidationException::class);

        (new OpportunityStageTransitionService())->transition(
            $actor,
            $opportunity,
            'not_a_real_stage',
            null
        );
    }

    public function test_transition_blocks_terminal_opportunity(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createTenantUser($tenant, [], ['admin'], ['crm.manage']);
        $opportunity = $this->makeOpportunity($tenant, ['pipeline_stage' => Opportunity::STAGE_WON]);

        $this->expectException(ValidationException::class);

        (new OpportunityStageTransitionService())->transition(
            $actor,
            $opportunity,
            Opportunity::STAGE_QUALIFIED,
            null
        );
    }

    public function test_transition_to_lost_requires_reason(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createTenantUser($tenant, [], ['admin'], ['crm.manage']);
        $opportunity = $this->makeOpportunity($tenant);

        $this->expectException(ValidationException::class);

        (new OpportunityStageTransitionService())->transition(
            $actor,
            $opportunity,
            Opportunity::STAGE_LOST,
            null
        );
    }

    public function test_transition_to_lost_with_reason_sets_lost_reason_and_forecast_category(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createTenantUser($tenant, [], ['admin'], ['crm.manage']);
        $opportunity = $this->makeOpportunity($tenant);

        $updated = (new OpportunityStageTransitionService())->transition(
            $actor,
            $opportunity,
            Opportunity::STAGE_LOST,
            'Khách chọn đối thủ khác'
        );

        $this->assertSame(Opportunity::STAGE_LOST, $updated->pipeline_stage);
        $this->assertSame('Khách chọn đối thủ khác', $updated->lost_reason);
        $this->assertSame('closed_lost', $updated->forecast_category);
    }

    public function test_transition_to_nurture_is_not_terminal(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createTenantUser($tenant, [], ['admin'], ['crm.manage']);
        $opportunity = $this->makeOpportunity($tenant);

        $updated = (new OpportunityStageTransitionService())->transition(
            $actor,
            $opportunity,
            Opportunity::STAGE_NURTURE,
            null
        );

        $this->assertSame(Opportunity::STAGE_NURTURE, $updated->pipeline_stage);
        $this->assertFalse($updated->isTerminal());
    }
}
