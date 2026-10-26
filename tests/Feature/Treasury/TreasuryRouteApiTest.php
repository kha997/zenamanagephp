<?php

declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryPaymentRoute as Route;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GAP-094 S4b-1 — via-route payments over the canonical Zena API.
 */
class TreasuryRouteApiTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;

    private Project $project;

    private User $accountant;

    private User $engineer;

    private User $viewer;

    private TreasuryFinancialParty $investor;

    private TreasuryWallet $transit;

    private TreasuryWallet $siteCash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $this->seedTreasuryPermissions();
        $this->tenant = Tenant::factory()->create();
        $this->project = $this->projectFor($this->tenant);
        $this->accountant = $this->userWithRole($this->tenant, 'Finance');
        $this->engineer = $this->userWithRole($this->tenant, 'site_engineer');
        $this->addMember($this->project, $this->engineer);
        $this->viewer = $this->userWithRole($this->tenant, 'Designer');
        $this->addMember($this->project, $this->viewer);
        $this->investor = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor']);
        $this->transit = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Trung chuyển', 'wallet_type' => 'intermediary_control']);
        $custodian = TreasuryFinancialParty::factory()->create([
            'tenant_id' => $this->tenant->id, 'party_type' => 'employee', 'linked_user_id' => $this->engineer->id,
        ]);
        $this->siteCash = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Quỹ công trường', 'custodian_party_id' => $custodian->id]);
    }

    public function test_accountant_creates_a_route_records_legs_and_engineer_finishes_it(): void
    {
        $routeId = (string) $this->postJson($this->url('store'), $this->routePayload(), $this->headersFor($this->accountant))
            ->assertStatus(201)
            ->assertJsonPath('data.status', Route::STATUS_PLANNED)
            ->assertJsonPath('data.document_status', Document::STATUS_APPROVED)
            ->assertJsonPath('data.total_amount', '2000000.00')
            ->json('data.id');

        $this->postJson($this->url('store'), $this->routePayload(), $this->headersFor($this->accountant))
            ->assertStatus(409);

        $this->postJson($this->url('legs.store', $routeId), $this->legPayload(null, $this->transit), $this->headersFor($this->accountant))
            ->assertStatus(201)->assertJsonPath('data.status', Route::STATUS_PARTIAL);
        $this->postJson($this->url('legs.store', $routeId), $this->legPayload($this->transit, $this->siteCash), $this->headersFor($this->engineer))
            ->assertStatus(201)
            ->assertJsonPath('data.status', Route::STATUS_COMPLETED)
            ->assertJsonPath('data.document_status', Document::STATUS_POSTED_UNRECONCILED)
            ->assertJsonPath('data.remaining_amount', '0.00')
            ->assertJsonCount(2, 'data.legs');

        $this->getJson($this->url('index'), $this->headersFor($this->viewer))
            ->assertOk()->assertJsonPath('data.0.id', $routeId);
        $this->getJson($this->url('show', $routeId), $this->headersFor($this->viewer))
            ->assertOk()->assertJsonPath('data.legs.1.to_wallet_name', 'Quỹ công trường');
    }

    public function test_permissions_and_validation(): void
    {
        $this->postJson($this->url('store'), $this->routePayload(), $this->headersFor($this->engineer))->assertForbidden();
        $this->postJson($this->url('store'), $this->routePayload(), $this->headersFor($this->viewer))->assertForbidden();
        $this->postJson($this->url('store'), ['document_type' => 'expense'] + $this->routePayload(), $this->headersFor($this->accountant))
            ->assertStatus(422)->assertJsonValidationErrors(['document_type'], 'error.details.data');

        $routeId = (string) $this->postJson($this->url('store'), $this->routePayload(), $this->headersFor($this->accountant))->json('data.id');
        $this->postJson($this->url('legs.store', $routeId), $this->legPayload(null, $this->transit), $this->headersFor($this->viewer))
            ->assertStatus(422)->assertJsonValidationErrors(['from_wallet_id'], 'error.details.data');
        $this->postJson($this->url('legs.store', $routeId), $this->legPayload(null, $this->transit, '2000000.01'), $this->headersFor($this->accountant))
            ->assertStatus(422)->assertJsonValidationErrors(['amount'], 'error.details.data');
        $this->getJson($this->url('show', '01JAAAAAAAAAAAAAAAAAAAAAAA'), $this->headersFor($this->accountant))->assertNotFound();
    }

    public function test_reverse_leg_and_cancel(): void
    {
        $routeId = (string) $this->postJson($this->url('store'), $this->routePayload(), $this->headersFor($this->accountant))->json('data.id');
        $legId = (string) $this->postJson($this->url('legs.store', $routeId), $this->legPayload(null, $this->transit), $this->headersFor($this->accountant))
            ->json('data.legs.0.id');

        $this->postJson($this->url('legs.reverse', $legId), ['reason' => 'sai'], $this->headersFor($this->engineer))->assertForbidden();
        $this->postJson($this->url('cancel', $routeId), ['reason' => 'bỏ'], $this->headersFor($this->accountant))
            ->assertStatus(422)->assertJsonValidationErrors(['route'], 'error.details.data');
        $this->postJson($this->url('legs.reverse', $legId), [], $this->headersFor($this->accountant))
            ->assertStatus(422)->assertJsonValidationErrors(['reason'], 'error.details.data');
        $this->postJson($this->url('legs.reverse', $legId), ['reason' => 'Nhầm'], $this->headersFor($this->accountant))
            ->assertOk()->assertJsonPath('data.legs.0.status', 'reversed')->assertJsonPath('data.status', Route::STATUS_PLANNED);
        $this->postJson($this->url('cancel', $routeId), ['reason' => 'Không chuyển nữa'], $this->headersFor($this->accountant))
            ->assertOk()->assertJsonPath('data.status', Route::STATUS_CANCELLED)->assertJsonPath('data.document_status', Document::STATUS_REJECTED);
    }

    /**
     * @return array<string, string>
     */
    private function routePayload(): array
    {
        return [
            'document_type' => Document::TYPE_FUNDING,
            'source_party_id' => (string) $this->investor->id,
            'destination_wallet_id' => (string) $this->siteCash->id,
            'amount' => '2000000',
            'transaction_date' => '2026-10-01',
            'reference' => 'UNC-77',
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function legPayload(?TreasuryWallet $from, TreasuryWallet $to, string $amount = '2000000'): array
    {
        return [
            'from_wallet_id' => $from === null ? null : (string) $from->id,
            'to_wallet_id' => (string) $to->id,
            'amount' => $amount,
            'occurred_at' => '2026-10-02',
        ];
    }

    private function url(string $name, ?string $id = null): string
    {
        $params = ['project' => (string) $this->project->id];
        if (in_array($name, ['show', 'legs.store', 'cancel'], true)) {
            $params['treasuryRoute'] = (string) $id;
        }
        if ($name === 'legs.reverse') {
            $params['treasuryRouteLeg'] = (string) $id;
        }

        return route('api.zena.treasury.routes.' . $name, $params, false);
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(User $user): array
    {
        return [
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $user->createToken('treasury-route-test')->plainTextToken,
        ];
    }
}
