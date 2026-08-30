<?php declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Account;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Crm\OpportunityStageTransitionService;
use App\Support\ServiceLine;
use App\Support\ServiceLineProvenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-048 §11/§17/§18 — pipeline classification gate: fires entering
 * scope_defined and every downstream sales stage except lost/no_bid/nurture,
 * backed by the shared CONFIRMED predicate, no grandfather exception.
 */
class ServiceLineGateTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private function fixture(string $stage): array
    {
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $tenant = Tenant::factory()->create();
        $actor = $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage']);
        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Gate fixture account',
            'status' => Account::STATUS_ACTIVE,
        ]);
        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Gate fixture',
            'pipeline_stage' => $stage,
            'created_by' => (string) $actor->id,
        ]);

        return [$actor, $opportunity];
    }

    public function test_transition_into_scope_defined_blocked_without_confirmed(): void
    {
        [$actor, $opportunity] = $this->fixture(Opportunity::STAGE_SURVEY_OR_INPUTS_RECEIVED);

        $this->expectException(ValidationException::class);
        app(OpportunityStageTransitionService::class)->transition($actor, $opportunity, Opportunity::STAGE_SCOPE_DEFINED, null);
    }

    public function test_transition_into_scope_defined_allowed_with_confirmed(): void
    {
        [$actor, $opportunity] = $this->fixture(Opportunity::STAGE_SURVEY_OR_INPUTS_RECEIVED);
        $opportunity->serviceLines()->create(['service_line' => ServiceLine::DESIGN, 'provenance' => ServiceLineProvenance::CONFIRMED]);

        $result = app(OpportunityStageTransitionService::class)->transition($actor, $opportunity->fresh(), Opportunity::STAGE_SCOPE_DEFINED, null);

        $this->assertSame(Opportunity::STAGE_SCOPE_DEFINED, $result->pipeline_stage);
    }

    public function test_transition_into_scope_defined_blocked_with_inferred_only(): void
    {
        [$actor, $opportunity] = $this->fixture(Opportunity::STAGE_SURVEY_OR_INPUTS_RECEIVED);
        $opportunity->serviceLines()->create(['service_line' => ServiceLine::DESIGN, 'provenance' => ServiceLineProvenance::INFERRED]);

        $this->expectException(ValidationException::class);
        app(OpportunityStageTransitionService::class)->transition($actor, $opportunity->fresh(), Opportunity::STAGE_SCOPE_DEFINED, null);
    }

    // Case O — always-allowed exits, zero classification
    public function test_lost_no_bid_nurture_transitions_never_gated(): void
    {
        foreach ([Opportunity::STAGE_LOST, Opportunity::STAGE_NO_BID, Opportunity::STAGE_NURTURE] as $stage) {
            [$actor, $opportunity] = $this->fixture(Opportunity::STAGE_QUALIFIED);
            $lostReason = $stage === Opportunity::STAGE_LOST ? 'price' : null;

            $result = app(OpportunityStageTransitionService::class)->transition($actor, $opportunity, $stage, $lostReason);

            $this->assertSame($stage, $result->pipeline_stage);
        }
    }

    // No-grandfather: the immediately-prior gated step is still blocked regardless of
    // how far along the deal already was before this feature shipped.
    public function test_negotiation_to_contracting_still_blocked_without_confirmed(): void
    {
        [$actor, $opportunity] = $this->fixture(Opportunity::STAGE_NEGOTIATION);

        $this->expectException(ValidationException::class);
        app(OpportunityStageTransitionService::class)->transition($actor, $opportunity, Opportunity::STAGE_CONTRACTING, null);
    }

    public function test_contracting_to_won_still_blocked_without_confirmed(): void
    {
        [$actor, $opportunity] = $this->fixture(Opportunity::STAGE_CONTRACTING);

        $this->expectException(ValidationException::class);
        app(OpportunityStageTransitionService::class)->transition($actor, $opportunity, Opportunity::STAGE_WON, null);
    }
}
