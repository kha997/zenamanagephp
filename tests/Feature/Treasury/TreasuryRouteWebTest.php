<?php

declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryPaymentRoute as Route;
use App\Models\Treasury\TreasuryPaymentRouteLeg as Leg;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GAP-094 S4b-1 — route section on the project page and the route page.
 */
class TreasuryRouteWebTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;

    private Project $project;

    private User $accountant;

    private User $engineer;

    private TreasuryFinancialParty $investor;

    private TreasuryWallet $transit;

    private TreasuryWallet $siteCash;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $this->seedTreasuryPermissions();
        $this->tenant = Tenant::factory()->create();
        $this->headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $this->project = $this->projectFor($this->tenant);
        $this->accountant = $this->userWithRole($this->tenant, 'Finance');
        $this->engineer = $this->userWithRole($this->tenant, 'site_engineer');
        $this->addMember($this->project, $this->engineer);
        $this->investor = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor', 'name' => 'Chủ đầu tư A']);
        $this->transit = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'TK công ty – trung chuyển', 'wallet_type' => 'intermediary_control']);
        $this->siteCash = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Quỹ công trường']);
    }

    public function test_accountant_creates_a_route_and_records_its_legs_from_the_pages(): void
    {
        $projectPage = $this->url('operator.treasury.projects.show');
        $this->actingAs($this->accountant)->get($projectPage, $this->headers)->assertOk()
            ->assertSee('treasury-route-form', false)
            ->assertSee('Chưa có tuyến tiền');

        $this->actingAs($this->accountant)->post($this->url('operator.treasury.projects.routes.store'), [
            'document_type' => 'funding', 'source_party_id' => (string) $this->investor->id,
            'destination_wallet_id' => (string) $this->siteCash->id, 'amount' => '1500000',
            'transaction_date' => '2026-10-01', 'reference' => 'UNC-5',
        ], $this->headers)->assertRedirect()->assertSessionHas('success');
        $route = Route::query()->firstOrFail();
        $routePage = $this->url('operator.treasury.projects.routes.show', ['treasuryRoute' => (string) $route->id]);

        $this->actingAs($this->accountant)->get($routePage, $this->headers)->assertOk()
            ->assertSee('treasury-route-leg-form', false)
            ->assertSee('Từ bên ngoài')
            ->assertSee('Chưa có chặng nào');

        foreach ([[null, $this->transit], [$this->transit, $this->siteCash]] as [$from, $to]) {
            $this->actingAs($this->accountant)->post($this->url('operator.treasury.projects.routes.legs.store', ['treasuryRoute' => (string) $route->id]), [
                'from_wallet_id' => $from === null ? '' : (string) $from->id, 'to_wallet_id' => (string) $to->id,
                'amount' => '1500000', 'occurred_at' => '2026-10-02',
            ], $this->headers)->assertRedirect($routePage)->assertSessionHas('success');
        }

        $this->assertSame(Route::STATUS_COMPLETED, $route->fresh()?->status);
        $this->assertSame(Document::STATUS_POSTED_UNRECONCILED, Document::query()->findOrFail($route->linked_financial_document_id)->status);
        $this->actingAs($this->accountant)->get($routePage, $this->headers)->assertOk()
            ->assertSee('Hoàn tất')
            ->assertDontSee('treasury-route-leg-form', false);
        $this->actingAs($this->accountant)->get($projectPage, $this->headers)->assertOk()
            ->assertSee('via-route-badge', false)
            ->assertSee('treasury-route-row', false);
    }

    public function test_engineer_sees_read_only_route_page_and_cannot_create_or_reverse(): void
    {
        $this->actingAs($this->engineer)->get($this->url('operator.treasury.projects.show'), $this->headers)->assertOk()
            ->assertDontSee('treasury-route-form', false);
        $this->actingAs($this->engineer)->post($this->url('operator.treasury.projects.routes.store'), [
            'document_type' => 'funding', 'source_party_id' => (string) $this->investor->id,
            'destination_wallet_id' => (string) $this->siteCash->id, 'amount' => '1', 'transaction_date' => '2026-10-01',
        ], $this->headers)->assertRedirect()->assertSessionHas('error');

        $this->actingAs($this->accountant)->post($this->url('operator.treasury.projects.routes.store'), [
            'document_type' => 'funding', 'source_party_id' => (string) $this->investor->id,
            'destination_wallet_id' => (string) $this->siteCash->id, 'amount' => '1000', 'transaction_date' => '2026-10-01',
        ], $this->headers);
        $route = Route::query()->firstOrFail();
        $legs = $this->url('operator.treasury.projects.routes.legs.store', ['treasuryRoute' => (string) $route->id]);
        $this->actingAs($this->engineer)->post($legs, [
            'from_wallet_id' => '', 'to_wallet_id' => (string) $this->transit->id, 'amount' => '1000', 'occurred_at' => '2026-10-02',
        ], $this->headers)->assertSessionHasErrors('from_wallet_id');
        $this->assertSame(0, Leg::query()->count());

        $this->actingAs($this->accountant)->post($legs, [
            'from_wallet_id' => '', 'to_wallet_id' => (string) $this->transit->id, 'amount' => '1000', 'occurred_at' => '2026-10-02',
        ], $this->headers);
        $leg = Leg::query()->firstOrFail();
        $this->actingAs($this->engineer)->get($this->url('operator.treasury.projects.routes.show', ['treasuryRoute' => (string) $route->id]), $this->headers)
            ->assertOk()->assertDontSee('Đảo chặng')->assertDontSee('treasury-route-cancel-form', false);
        $this->actingAs($this->engineer)->post($this->url('operator.treasury.projects.route-legs.reverse', ['treasuryRouteLeg' => (string) $leg->id]), ['reason' => 'x'], $this->headers)
            ->assertRedirect()->assertSessionHas('error');
        $this->assertSame(Leg::STATUS_SETTLED, $leg->fresh()?->status);
    }

    public function test_accountant_reverses_a_leg_then_cancels_the_route(): void
    {
        $this->actingAs($this->accountant)->get($this->url('operator.treasury.projects.show'), $this->headers)->assertOk();
        $this->actingAs($this->accountant)->post($this->url('operator.treasury.projects.routes.store'), [
            'document_type' => 'funding', 'source_party_id' => (string) $this->investor->id,
            'destination_wallet_id' => (string) $this->siteCash->id, 'amount' => '1000', 'transaction_date' => '2026-10-01',
        ], $this->headers)->assertSessionHasNoErrors()->assertSessionHas('success');
        $route = Route::query()->firstOrFail();
        $this->actingAs($this->accountant)->post($this->url('operator.treasury.projects.routes.legs.store', ['treasuryRoute' => (string) $route->id]), [
            'from_wallet_id' => '', 'to_wallet_id' => (string) $this->transit->id, 'amount' => '400', 'occurred_at' => '2026-10-02',
        ], $this->headers);
        $leg = Leg::query()->firstOrFail();

        $cancel = $this->url('operator.treasury.projects.routes.cancel', ['treasuryRoute' => (string) $route->id]);
        $this->actingAs($this->accountant)->post($cancel, ['reason' => 'bỏ'], $this->headers)->assertSessionHasErrors('route');
        $this->actingAs($this->accountant)->post($this->url('operator.treasury.projects.route-legs.reverse', ['treasuryRouteLeg' => (string) $leg->id]), ['reason' => 'Ghi nhầm'], $this->headers)
            ->assertRedirect()->assertSessionHas('success');
        $this->actingAs($this->accountant)->post($cancel, ['reason' => 'Không chuyển nữa'], $this->headers)
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame(Route::STATUS_CANCELLED, $route->fresh()?->status);
    }

    /**
     * @param array<string, string> $params
     */
    private function url(string $name, array $params = []): string
    {
        return route($name, ['project' => (string) $this->project->id] + $params, false);
    }
}
