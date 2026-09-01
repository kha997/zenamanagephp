<?php declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Account;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteLineItem;
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/login');
    }

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

    private function authHeaders(User $user): array
    {
        $token = $user->createToken('gap048-quote-gate-test')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];
    }

    // Case K — sendQuote() blocked without CONFIRMED
    public function test_send_quote_blocked_without_confirmed(): void
    {
        [$actor, $opportunity] = $this->fixture(Opportunity::STAGE_NEW_LEAD);
        $quote = Quote::factory()->create(['tenant_id' => $opportunity->tenant_id, 'opportunity_id' => $opportunity->id, 'status' => Quote::STATUS_DRAFT]);
        QuoteLineItem::factory()->create(['tenant_id' => $opportunity->tenant_id, 'quote_id' => $quote->id]);

        $response = $this->actingAs($actor)->post(route('operator.crm.quotes.send', ['id' => $quote->id]));

        $response->assertRedirect();
        $this->assertSame(Quote::STATUS_DRAFT, $quote->fresh()->status);
    }

    public function test_send_quote_allowed_with_confirmed(): void
    {
        [$actor, $opportunity] = $this->fixture(Opportunity::STAGE_NEW_LEAD);
        $opportunity->serviceLines()->create(['service_line' => ServiceLine::DESIGN, 'provenance' => ServiceLineProvenance::CONFIRMED]);
        $quote = Quote::factory()->create(['tenant_id' => $opportunity->tenant_id, 'opportunity_id' => $opportunity->id, 'status' => Quote::STATUS_DRAFT]);
        QuoteLineItem::factory()->create(['tenant_id' => $opportunity->tenant_id, 'quote_id' => $quote->id]);

        $response = $this->actingAs($actor)->post(route('operator.crm.quotes.send', ['id' => $quote->id]));

        $response->assertRedirect();
        $this->assertSame(Quote::STATUS_SENT, $quote->fresh()->status);
    }

    // Case L — external accepted snapshot syncs freely, but createContract() is blocked
    public function test_create_contract_blocked_without_confirmed_even_with_external_accepted_snapshot(): void
    {
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $tenant = Tenant::factory()->create();
        $actor = $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage', 'crm.convert']);
        $account = Account::query()->create(['tenant_id' => (string) $tenant->id, 'account_type' => Account::TYPE_INDIVIDUAL, 'display_name' => 'L account', 'status' => Account::STATUS_ACTIVE]);
        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Case L',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'created_by' => (string) $actor->id,
            'external_quote_snapshot' => ['status' => 'ACCEPTED', 'total' => 1000],
        ]);

        $response = $this->postJson(
            route('api.zena.crm.opportunities.create-contract', ['id' => $opportunity->id], false),
            [],
            $this->authHeaders($actor)
        );

        $response->assertStatus(422);
        $this->assertArrayHasKey('service_line', $response->json('error.details.data'));
    }

    // Case M — already-WON legacy Opportunity still blocked (no grandfather)
    public function test_already_won_opportunity_convert_blocked_until_confirmed(): void
    {
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $tenant = Tenant::factory()->create();
        $actor = $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage', 'crm.convert']);
        $account = Account::query()->create(['tenant_id' => (string) $tenant->id, 'account_type' => Account::TYPE_INDIVIDUAL, 'display_name' => 'M account', 'status' => Account::STATUS_ACTIVE]);
        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Case M',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'created_by' => (string) $actor->id,
        ]);

        $response = $this->postJson(
            route('api.zena.crm.opportunities.convert', ['id' => $opportunity->id], false),
            [],
            $this->authHeaders($actor)
        );

        $response->assertStatus(422);
        $this->assertArrayHasKey('service_line', $response->json('error.details.data'));
    }
}
