<?php declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryWallet;
use App\Support\Navigation\OperatorNavigationComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GAP-063 S1 — operator pages (Gate 2 Option A §6).
 */
class TreasuryWebPagesTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $this->seedTreasuryPermissions();
        $this->tenant = Tenant::factory()->create();
        $this->headers = ['X-Tenant-ID' => (string) $this->tenant->id];
    }

    public function test_accountant_sets_up_a_party_and_a_wallet_through_the_pages(): void
    {
        $accountant = $this->userWithRole($this->tenant, 'Finance');
        $project = $this->projectFor($this->tenant);

        $this->actingAs($accountant)->get(route('operator.treasury.index'), $this->headers)
            ->assertOk()->assertSee($project->name);

        $this->actingAs($accountant)->post(route('operator.treasury.parties.store'), [
            'party_type' => 'employee',
            'name' => 'Kỹ sư công trường',
        ], $this->headers)->assertRedirect(route('operator.treasury.parties.index'));
        $party = TreasuryFinancialParty::query()->firstOrFail();

        $this->actingAs($accountant)->get(route('operator.treasury.parties.index'), $this->headers)
            ->assertOk()->assertSee('Kỹ sư công trường');

        $this->actingAs($accountant)->post(route('operator.treasury.projects.wallets.store', $project->id), [
            'wallet_type' => 'employee_cash',
            'name' => 'Tiền mặt công trường',
            'custodian_party_id' => (string) $party->id,
        ], $this->headers)->assertRedirect(route('operator.treasury.projects.show', $project->id));

        $this->actingAs($accountant)->get(route('operator.treasury.projects.show', $project->id), $this->headers)
            ->assertOk()
            ->assertSee('Tiền mặt công trường')
            ->assertSee('Kỹ sư công trường')
            ->assertSee('treasury-wallet-form', false);

        $wallet = TreasuryWallet::query()->firstOrFail();
        $this->actingAs($accountant)->post(route('operator.treasury.projects.wallets.update', [$project->id, $wallet->id]), [
            'wallet_type' => 'employee_cash',
            'name' => 'Quỹ công trường A',
            'custodian_party_id' => '',
        ], $this->headers)->assertRedirect(route('operator.treasury.projects.show', $project->id));
        $this->assertSame('Quỹ công trường A', $wallet->fresh()?->name);
        $this->assertNull($wallet->fresh()?->custodian_party_id);

        $this->actingAs($accountant)->post(route('operator.treasury.projects.wallets.destroy', [$project->id, $wallet->id]), [], $this->headers)
            ->assertRedirect(route('operator.treasury.projects.show', $project->id));
        $this->assertDatabaseMissing('treasury_wallets', ['id' => $wallet->id]);
    }

    public function test_engineer_member_sees_wallets_read_only_and_only_member_projects(): void
    {
        $engineer = $this->userWithRole($this->tenant, 'site_engineer');
        $member = $this->projectFor($this->tenant);
        $notMember = $this->projectFor($this->tenant);
        $this->addMember($member, $engineer);
        TreasuryWallet::factory()->create(['project_id' => $member->id, 'name' => 'Ví xem được']);

        $this->actingAs($engineer)->get(route('operator.treasury.index'), $this->headers)
            ->assertOk()->assertSee($member->name)->assertDontSee($notMember->name);

        $this->actingAs($engineer)->get(route('operator.treasury.projects.show', $member->id), $this->headers)
            ->assertOk()->assertSee('Ví xem được')->assertDontSee('treasury-wallet-form', false);

        $this->actingAs($engineer)->get(route('operator.treasury.projects.show', $notMember->id), $this->headers)
            ->assertForbidden();
        // Missing codes are rejected by the rbac middleware, which answers web
        // requests with a redirect + friendly error (content negotiation).
        $this->actingAs($engineer)->get(route('operator.treasury.parties.index'), $this->headers)
            ->assertRedirect()->assertSessionHas('error');
        $this->actingAs($engineer)->post(route('operator.treasury.projects.wallets.store', $member->id), [
            'wallet_type' => 'company_cash',
            'name' => 'W',
        ], $this->headers)->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseMissing('treasury_wallets', ['name' => 'W']);
    }

    public function test_client_cannot_open_treasury(): void
    {
        $client = $this->userWithRole($this->tenant, 'Client');
        $project = $this->projectFor($this->tenant);
        $this->addMember($project, $client);

        $this->actingAs($client)->get(route('operator.treasury.index'), $this->headers)
            ->assertRedirect()->assertSessionHas('error');
        $this->actingAs($client)->get(route('operator.treasury.projects.show', $project->id), $this->headers)
            ->assertRedirect()->assertSessionHas('error');
    }

    public function test_other_tenant_project_page_is_not_found(): void
    {
        $accountant = $this->userWithRole($this->tenant, 'Finance');
        $foreign = $this->projectFor(Tenant::factory()->create());

        $this->actingAs($accountant)->get(route('operator.treasury.projects.show', $foreign->id), $this->headers)->assertNotFound();
    }

    public function test_used_party_is_not_deleted_from_the_page(): void
    {
        $accountant = $this->userWithRole($this->tenant, 'Finance');
        $party = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id]);
        TreasuryWallet::factory()->create(['project_id' => $this->projectFor($this->tenant)->id, 'custodian_party_id' => $party->id]);

        $this->actingAs($accountant)->get(route('operator.treasury.parties.index'), $this->headers)->assertOk();
        $this->actingAs($accountant)
            ->from(route('operator.treasury.parties.index'))
            ->post(route('operator.treasury.parties.destroy', $party->id), [], $this->headers)
            ->assertRedirect(route('operator.treasury.parties.index'))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('treasury_financial_parties', ['id' => $party->id]);
    }

    public function test_navigation_shows_treasury_items_by_permission(): void
    {
        $composer = app(OperatorNavigationComposer::class);
        $labels = static fn (array $sections): array => collect($sections['Tài chính'] ?? [])->pluck('label')->all();

        $this->assertSame(['Ngân quỹ', 'Đối tác tài chính'], $labels($composer->visibleFor($this->userWithRole($this->tenant, 'Finance'))));
        $this->assertSame(['Ngân quỹ'], $labels($composer->visibleFor($this->userWithRole($this->tenant, 'site_engineer'))));
        $this->assertSame([], $labels($composer->visibleFor($this->userWithRole($this->tenant, 'Client'))));
    }

    public function test_project_page_links_to_treasury_only_for_allowed_users(): void
    {
        $project = $this->projectFor($this->tenant);
        $accountant = $this->userWithRole($this->tenant, 'Finance');
        $accountant->roles()->first()?->permissions()->syncWithoutDetaching(
            \App\Models\Permission::query()->whereIn('code', ['project.view', 'project.read'])->pluck('id')->all()
        );

        $this->actingAs($accountant)->get(route('app.projects.show', $project->id), $this->headers)
            ->assertOk()
            ->assertSee(route('operator.treasury.projects.show', $project->id), false);
    }
}
