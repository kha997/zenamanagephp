<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Navigation\OperatorNavigationComposer;
use App\Support\Navigation\OperatorNavItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class OperatorNavigationTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $this->tenant = Tenant::factory()->create();
    }

    private function labels(array $sections): array
    {
        return collect($sections)->flatten()->map(fn (OperatorNavItem $i) => $i->routeName)->all();
    }

    public function test_every_defined_navigation_route_resolves(): void
    {
        foreach (\App\Support\Navigation\OperatorNavigationDefinition::items() as $item) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Route::has($item->routeName),
                "Nav item '{$item->label}' references undefined route '{$item->routeName}'."
            );
            $this->assertNotSame('', $item->label);
            $this->assertNotSame('', $item->section);
            $this->assertNotSame('', $item->iconKey);
        }
    }

    public function test_zero_rbac_route_on_verified_baseline_allowlist_is_visible(): void
    {
        $employee = $this->createTenantUser($this->tenant, [], ['member'], []);

        $composer = app(OperatorNavigationComposer::class);
        $requirement = $composer->resolveAuthorization('app.tasks');
        $this->assertSame('baseline', $requirement->type);

        $labels = $this->labels($composer->visibleFor($employee));
        $this->assertContains('app.tasks', $labels);
    }

    public function test_single_rbac_route_hidden_without_permission_and_visible_with_it(): void
    {
        // Distinct role names deliberately used here (not both 'member'): Role
        // permissions in this schema are role-level, not per-user — two users
        // sharing one role name would bleed permissions into each other via
        // TenantUserFactoryTrait's Role::firstOrCreate(), silently blinding
        // this test. See feedback_fixture_role_bleed lesson.
        $employee = $this->createTenantUser($this->tenant, [], ['member'], []);
        $pm = $this->createTenantUser($this->tenant, [], ['workload-pm'], ['task.view']);

        $composer = app(OperatorNavigationComposer::class);

        $this->assertNotContains('app.workload.index', $this->labels($composer->visibleFor($employee)));
        $this->assertContains('app.workload.index', $this->labels($composer->visibleFor($pm)));
    }

    public function test_can_middleware_route_hidden_without_matching_permission_and_visible_with_it(): void
    {
        // Distinct role names (see note above) — must not both be 'member'.
        $withoutTeamView = $this->createTenantUser($this->tenant, [], ['member'], []);
        $withTeamView = $this->createTenantUser($this->tenant, [], ['team-viewer'], ['team.view']);

        $composer = app(OperatorNavigationComposer::class);
        $requirement = $composer->resolveAuthorization('app.team.index');
        $this->assertSame('can', $requirement->type);
        $this->assertSame('viewAny', $requirement->ability);
        $this->assertSame(\App\Models\Team::class, $requirement->subjectClass);

        $this->assertNotContains('app.team.index', $this->labels($composer->visibleFor($withoutTeamView)));
        $this->assertContains('app.team.index', $this->labels($composer->visibleFor($withTeamView)));
    }

    public function test_unresolvable_route_fails_closed(): void
    {
        $anyUser = $this->createTenantUser($this->tenant, [], ['super_admin'], []);
        $composer = app(OperatorNavigationComposer::class);

        $requirement = $composer->resolveAuthorization('this.route.does.not.exist');

        $this->assertSame('unresolvable', $requirement->type);

        $syntheticItems = [new OperatorNavItem('Ẩn', 'this.route.does.not.exist', 'Test', 'generic')];
        $this->assertSame([], $composer->visibleFromItems($syntheticItems, $anyUser));
    }

    public function test_zero_rbac_route_not_on_baseline_allowlist_fails_closed(): void
    {
        $anyUser = $this->createTenantUser($this->tenant, [], ['super_admin'], []);
        $composer = app(OperatorNavigationComposer::class);

        // app.tasks.show carries auth+tenant.isolation only (verified earlier),
        // and is deliberately NOT on the baseline allowlist because it was never
        // reviewed as a sidebar destination (it is a detail page, not a nav item)
        // — this proves zero-rbac alone is not sufficient without the allowlist.
        $requirement = $composer->resolveAuthorization('app.tasks.show');

        $this->assertSame('unresolvable', $requirement->type);
    }

    public function test_empty_sections_are_omitted(): void
    {
        $employee = $this->createTenantUser($this->tenant, [], ['member'], []);

        $composer = app(OperatorNavigationComposer::class);
        $visible = $composer->visibleFor($employee);

        foreach ($visible as $section => $items) {
            $this->assertNotEmpty($items, "Section '{$section}' must not appear with zero items.");
        }
    }

    public function test_hidden_navigation_does_not_weaken_route_rbac(): void
    {
        $employee = $this->createTenantUser($this->tenant, [], ['member'], []);

        // rbac:* denial (RoleBasedAccessControlMiddleware) content-negotiates a
        // friendly redirect (302) for web requests (PR#220). can:* denial goes
        // through Laravel's built-in Authorize middleware, which throws
        // AuthorizationException and renders as a plain 403 — verified here,
        // not assumed. Either way the destination itself still refuses access;
        // that is what this test protects.
        $this->actingAs($employee)->get(route('app.workload.index'))->assertStatus(302);
        $this->actingAs($employee)->get(route('app.team.index'))->assertStatus(403);
    }

    public function test_nav_item_never_visible_when_destination_deterministically_returns_302_for_same_actor(): void
    {
        $employee = $this->createTenantUser($this->tenant, [], ['member'], []);
        $composer = app(OperatorNavigationComposer::class);

        $visibleRouteNames = $this->labels($composer->visibleFor($employee));

        foreach ($visibleRouteNames as $routeName) {
            $status = $this->actingAs($employee)->get(route($routeName))->getStatusCode();
            $this->assertNotSame(302, $status, "Nav shows '{$routeName}' but the route redirects (would 403-equivalent) for this actor.");
        }
    }

    public function test_permission_query_count_is_bounded_independent_of_nav_item_count(): void
    {
        $employee = $this->createTenantUser($this->tenant, [], ['member'], ['task.view']);
        $composer = app(OperatorNavigationComposer::class);

        // Baseline measured via a single rbac:*-only item, NOT visibleFor()'s
        // full 28-item definition — the real definition also includes
        // app.team.index (a can:* item), whose Gate::allows() legitimately
        // costs 1 extra query that has nothing to do with rbac:* scaling.
        // Comparing against that would conflate two different code paths;
        // this isolates the one under test (rbac:* evaluation must be O(1)
        // in query count regardless of item count).
        $singleItem = [new OperatorNavItem('Baseline', 'app.workload.index', 'Test', 'generic')];

        DB::flushQueryLog();
        DB::enableQueryLog();
        $composer->visibleFromItems($singleItem, $employee);
        $baselineCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 50 synthetic rbac-gated items reusing 2 already-checked permissions —
        // must not add any new query, because rbac evaluation reads the
        // preloaded in-memory permission set, not the database, per item.
        $syntheticItems = array_map(
            fn (int $i) => new OperatorNavItem("Synthetic {$i}", 'app.workload.index', 'Test', 'generic'),
            range(1, 50)
        );

        DB::flushQueryLog();
        DB::enableQueryLog();
        $composer->visibleFromItems($syntheticItems, $employee);
        $syntheticCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($baselineCount, $syntheticCount);
    }

    public function test_multiple_rbac_requirements_on_one_route_use_and_semantics(): void
    {
        // routes/web.php:981 (design-items.suggest-description) carries both
        // rbac:design-item.manage AND rbac:ai.suggest — verified real 2-permission case.
        $composer = app(OperatorNavigationComposer::class);
        $requirement = $composer->resolveAuthorization('operator.design-items.suggest-description');

        $this->assertSame('rbac', $requirement->type);
        $this->assertSame(['design-item.manage', 'ai.suggest'], $requirement->permissions);

        // Distinct role names (see note above) — must not both be 'member'.
        $onlyOne = $this->createTenantUser($this->tenant, [], ['design-item-manager'], ['design-item.manage']);
        $both = $this->createTenantUser($this->tenant, [], ['design-item-ai-suggester'], ['design-item.manage', 'ai.suggest']);

        $syntheticItems = [new OperatorNavItem('Gợi ý AI', 'operator.design-items.suggest-description', 'Test', 'generic')];
        $this->assertSame([], $composer->visibleFromItems($syntheticItems, $onlyOne));
        $this->assertNotEmpty($composer->visibleFromItems($syntheticItems, $both));
    }

    public function test_every_nav_item_icon_key_has_a_rendered_case_in_the_icon_component(): void
    {
        $iconComponentSource = file_get_contents(resource_path('views/components/operator-nav-icon.blade.php'));

        foreach (\App\Support\Navigation\OperatorNavigationDefinition::items() as $item) {
            $this->assertStringContainsString(
                "@case('{$item->iconKey}')",
                $iconComponentSource,
                "Icon component has no @case for iconKey '{$item->iconKey}' (route '{$item->routeName}')."
            );
        }
    }

    public function test_active_route_class_applied_to_current_page_link(): void
    {
        $viewer = $this->createTenantUser($this->tenant, [], ['member'], ['task.view']);

        $response = $this->actingAs($viewer)->get(route('app.workload.index'));

        $response->assertOk();
        // is-active phải nằm trên đúng link Khối lượng, không phải link khác.
        $response->assertSee('operator-nav-link is-active', false);
    }
}
