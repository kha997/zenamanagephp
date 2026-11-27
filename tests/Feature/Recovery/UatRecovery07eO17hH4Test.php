<?php

declare(strict_types=1);

namespace Tests\Feature\Recovery;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Document;
use App\Models\Project;
use App\Models\Role;
use App\Models\SidebarConfig;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use App\Policies\SidebarConfigPolicy;
use App\Support\Navigation\OperatorNavigationComposer;
use App\Support\Navigation\OperatorNavItem;
use App\Support\PlatformPermissions;
use App\Support\Rbac\RbacProtection;
use Illuminate\Auth\Access\Gate as GateImpl;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * UAT RECOVERY 07e — OWN-2026-024 (O-17h) H4 (Owner: "H3_ACCEPTED / H4_LOCAL_IMPLEMENTATION_APPROVED", 2026-11-25).
 *
 * Reachable policy shortcuts removed: DocumentPolicy (role names + legacy users.role), SidebarConfigPolicy::before() and its
 * role-name abilities, the unguarded SimpleDocumentController::destroy / index / store behind api/documents-simple (parameterless
 * role-name `rbac` gate only), and navigation visibility read straight from role_permissions (ignoring revocations and the
 * Platform Operator ledger). Fixture: production:rbac on a fresh database (the real grant set), test-owned roles only.
 */
#[Group('uat-recovery-07-o17h')]
class UatRecovery07eO17hH4Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Policies that still decide by role NAME / users.role but have NO caller today (H4 inventory, 105 §2). Wiring any of them
     * (authorize / can / @can / can: middleware) needs a separate Owner gate first; this tripwire fails if one becomes reachable.
     */
    private const UNREACHABLE_ROLE_NAME_POLICIES = [
        'ChangeRequest', 'Component', 'Ncr', 'Rfi', 'QcPlan', 'QcInspection', 'Notification', 'Template', 'Invitation', 'User',
    ];

    private Tenant $a;

    private Tenant $b;

    private Role $ownerRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        Storage::fake('local');
        Artisan::call('production:rbac');
        $this->a = Tenant::factory()->create();
        $this->b = Tenant::factory()->create();
        $this->ownerRole = Role::query()->where('name', 'super_admin')->firstOrFail();
    }

    // ===================================================================== zero-grant super_admin / admin, users.role

    public function test_role_names_and_users_role_alone_authorize_no_document_or_sidebar_ability(): void
    {
        $zeroOwnerRole = Role::query()->create(['name' => 'o17h-h4-zero', 'scope' => 'custom', 'is_active' => true]);
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin'], ['scope' => 'system', 'is_active' => true]);
        $actors = [
            // the Owner role with every grant except the ones under test is covered by the revocation test; here: names only
            'role admin, zero grants' => $this->userWith($this->a, [$adminRole]),
            'users.role admin' => $this->userWith($this->a, [], 'admin'),
            'users.role super_admin' => $this->userWith($this->a, [], 'super_admin'),
            'users.role pm' => $this->userWith($this->a, [], 'pm'),
            'zero-grant custom role' => $this->userWith($this->a, [$zeroOwnerRole]),
        ];
        $doc = $this->document($this->a);
        $config = $this->sidebarConfig($this->a);

        $allowed = [];
        foreach ($actors as $label => $user) {
            foreach (['viewAny' => Document::class, 'create' => Document::class, 'view' => $doc, 'update' => $doc, 'delete' => $doc, 'restore' => $doc, 'forceDelete' => $doc, 'download' => $doc, 'approve' => $doc, 'assignApprover' => $doc] as $ability => $subject) {
                if (Gate::forUser($user)->allows($ability, $subject)) {
                    $allowed[] = "{$label}: document.{$ability}";
                }
            }
            foreach (['viewAny' => SidebarConfig::class, 'create' => SidebarConfig::class, 'manage' => SidebarConfig::class, 'clone' => SidebarConfig::class, 'reset' => SidebarConfig::class, 'view' => $config, 'update' => $config, 'delete' => $config] as $ability => $subject) {
                if (Gate::forUser($user)->allows($ability, $subject)) {
                    $allowed[] = "{$label}: sidebar.{$ability}";
                }
            }
        }
        $this->assertSame([], $allowed);

        // The zero-grant Owner ROLE (all grants detached) is denied too.
        $zeroOwner = $this->userWith($this->a, [$this->ownerRole]);
        DB::table('role_permissions')->where('role_id', (string) $this->ownerRole->id)->delete();
        $this->assertFalse(Gate::forUser($zeroOwner->fresh())->allows('delete', $doc));
        $this->assertFalse(Gate::forUser($zeroOwner->fresh())->allows('viewAny', SidebarConfig::class));
    }

    // ===================================================================== real grants permit, unauthorized deletion denied

    public function test_documents_simple_delete_needs_canonical_authority_and_persists_nothing_on_denial(): void
    {
        $doc = $this->document($this->a);
        $siteEngineer = $this->userWith($this->a, [Role::query()->where('name', 'site_engineer')->firstOrFail()]); // passes the parameterless gate
        $pm = $this->userWith($this->a, [Role::query()->where('name', 'Project Manager')->firstOrFail()]);
        $legacyAdmin = $this->userWith($this->a, [Role::query()->where('name', 'site_engineer')->firstOrFail()], 'admin');

        foreach (['site_engineer role' => $siteEngineer, 'Project Manager role' => $pm, 'users.role admin' => $legacyAdmin] as $label => $actor) {
            Sanctum::actingAs($actor, ['*']);
            $status = $this->withHeaders(['X-Tenant-ID' => (string) $this->a->id])->deleteJson("/api/documents-simple/{$doc->id}")->status();
            $this->assertNotContains($status, [200, 202, 204], "{$label}: HTTP {$status}");
            $this->assertFalse($this->trashed($doc), "{$label}: document must survive");
        }

        // The canonical Owner (document.delete + Owner authority) deletes; a foreign-tenant Owner cannot even see it.
        $foreignOwner = $this->userWith($this->b, [$this->ownerRole]);
        Sanctum::actingAs($foreignOwner, ['*']);
        $this->assertNotContains($this->withHeaders(['X-Tenant-ID' => (string) $this->b->id])->deleteJson("/api/documents-simple/{$doc->id}")->status(), [200, 202, 204]);
        $this->assertFalse($this->trashed($doc));

        $owner = $this->userWith($this->a, [$this->ownerRole]);
        Sanctum::actingAs($owner, ['*']);
        $this->withHeaders(['X-Tenant-ID' => (string) $this->a->id])->deleteJson("/api/documents-simple/{$doc->id}")->assertOk();
        $this->assertTrue($this->trashed($doc), 'soft-deleted by the canonical Owner');
    }

    public function test_documents_simple_index_and_store_follow_canonical_grants(): void
    {
        $this->document($this->a);
        $siteEngineer = $this->userWith($this->a, [Role::query()->where('name', 'site_engineer')->firstOrFail()]);
        Sanctum::actingAs($siteEngineer, ['*']);
        $h = ['X-Tenant-ID' => (string) $this->a->id];
        // With APP_DEBUG on in a testing/local environment, routes/debug_api.php re-registers GET api/documents-simple as an
        // unauthenticated stub AFTER routes/api.php, replacing the real route (finding DBG-1, never loaded in production). Assert
        // the real controller only where it actually answers; the policy decision is asserted unconditionally.
        $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($siteEngineer)->allows('viewAny', Document::class), 'no document.view');
        $indexRoute = \Illuminate\Support\Facades\Route::getRoutes()->match(\Illuminate\Http\Request::create('/api/documents-simple', 'GET'));
        if ($indexRoute->getActionName() === \App\Http\Controllers\Api\SimpleDocumentController::class . '@index') {
            $this->assertSame(403, $this->withHeaders($h)->getJson('/api/documents-simple')->status(), 'no document.view');
        }
        $before = Document::withoutGlobalScopes()->count();
        $this->withHeaders($h)->postJson('/api/documents-simple', ['title' => 'x'])->assertForbidden();
        $this->assertSame($before, Document::withoutGlobalScopes()->count());

        $owner = $this->userWith($this->a, [$this->ownerRole]);
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($owner)->allows('viewAny', Document::class));
        Sanctum::actingAs($owner, ['*']);
        $this->withHeaders($h)->getJson('/api/documents-simple')->assertOk();
    }

    public function test_unauthorized_document_approval_is_denied(): void
    {
        $doc = $this->document($this->a);
        $member = $this->userWith($this->a, [Role::query()->where('name', 'Project Member')->firstOrFail()], 'pm');
        $this->assertFalse(Gate::forUser($member)->allows('approve', $doc));
        $foreignPm = $this->userWith($this->b, [Role::query()->where('name', 'Project Manager')->firstOrFail()]);
        $this->assertFalse(Gate::forUser($foreignPm)->allows('approve', $doc), 'cross-tenant approval');
        $pm = $this->userWith($this->a, [Role::query()->where('name', 'Project Manager')->firstOrFail()]);
        $this->assertTrue(Gate::forUser($pm)->allows('approve', $doc), 'the seeded PM holds document.approve');
    }

    // ===================================================================== revocation is honoured by policies and visibility

    public function test_revoked_owner_grants_deny_policy_abilities_and_navigation(): void
    {
        $approver = $this->userWith($this->a, [$this->ownerRole]);
        $owner = $this->userWith($this->a, [$this->ownerRole]);
        $doc = $this->document($this->a);
        $this->assertTrue(Gate::forUser($owner)->allows('delete', $doc));
        $this->assertContains('document.delete', $owner->grantedPermissionNames());

        $this->artisan('rbac:permission', ['action' => 'revoke', '--role' => 'super_admin', '--code' => 'document.delete', '--reason' => 'h4 test', '--approval' => 'OWN-2026-024', '--approved-by' => $approver->email, '--yes' => true])->assertExitCode(0);
        Artisan::call('production:rbac');

        $owner = $owner->fresh();
        $this->assertFalse(Gate::forUser($owner)->allows('delete', $doc));
        $this->assertFalse(Gate::forUser($owner)->allows('forceDelete', $doc));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $doc), 'only the revoked pair is affected');
        $this->assertNotContains('document.delete', $owner->grantedPermissionNames());
        $this->assertNotContains('document.delete', app(OperatorNavigationComposer::class)->loadPermissionNames($owner));

        // No policy before() hook and no Gate::before callback can override the revocation.
        $this->assertFalse(method_exists(SidebarConfigPolicy::class, 'before'));
        $gate = app(GateContract::class);
        $this->assertInstanceOf(GateImpl::class, $gate);
        $before = (new \ReflectionProperty(GateImpl::class, 'beforeCallbacks'))->getValue($gate);
        $this->assertSame([], $before, 'no Gate::before hook may exist');
    }

    public function test_navigation_visibility_follows_real_permissions_and_operator_status(): void
    {
        $composer = app(OperatorNavigationComposer::class);
        $items = [
            new OperatorNavItem('Company', 'app.admin.roles', 'x', 'x'),     // rbac:admin.company.manage
            new OperatorNavItem('Host console', 'admin-dashboard', 'x', 'x'), // rbac:platform.ops.view
        ];
        $labels = static fn (array $v): array => array_map(static fn (OperatorNavItem $i): string => $i->label, array_merge(...array_values($v)));

        $owner = $this->userWith($this->a, [$this->ownerRole]);
        $this->assertSame(['Company'], $labels($composer->visibleFromItems($items, $owner)), 'Owner: no host console');

        // Operator role WITHOUT the ledger row (a leaked assignment) shows nothing host-level.
        $this->artisan('platform:operator', ['action' => 'grant', '--user' => $this->userWith($this->b, [])->email, '--reason' => 'creates role', '--approval' => 'x', '--yes' => true])->assertExitCode(0);
        $operatorRole = Role::query()->where('name', RbacProtection::PLATFORM_OPERATOR_ROLE)->firstOrFail();
        $leaked = $this->userWith($this->a, [$operatorRole]);
        $this->assertSame([], $labels($composer->visibleFromItems($items, $leaked)));
        $this->assertSame([], array_values(array_filter($leaked->grantedPermissionNames(), [PlatformPermissions::class, 'isPlatformCode'])));

        $operator = $this->userWith($this->b, []);
        $this->artisan('platform:operator', ['action' => 'grant', '--user' => $operator->email, '--reason' => 'h4', '--approval' => 'x', '--yes' => true])->assertExitCode(0);
        $this->assertSame(['Host console'], $labels($composer->visibleFromItems($items, $operator->fresh())), 'operator: host console, no tenant admin');
    }

    // ===================================================================== sidebar configs: Owner, own tenant only

    public function test_sidebar_configs_are_managed_by_the_canonical_owner_of_the_same_tenant_only(): void
    {
        $owner = $this->userWith($this->a, [$this->ownerRole]);
        $own = $this->sidebarConfig($this->a);
        $foreign = $this->sidebarConfig($this->b);
        $global = $this->sidebarConfig(null);

        $gate = Gate::forUser($owner);
        $this->assertTrue($gate->allows('viewAny', SidebarConfig::class));
        $this->assertTrue($gate->allows('view', $own));
        $this->assertTrue($gate->allows('update', $own));
        $this->assertFalse($gate->allows('view', $foreign), 'cross-tenant');
        $this->assertFalse($gate->allows('update', $foreign), 'cross-tenant');
        $this->assertFalse($gate->allows('delete', $foreign), 'cross-tenant');
        $this->assertFalse($gate->allows('update', $global), 'global configs are not tenant-managed (separate Owner gate)');

        $this->assertTrue($gate->allows('delete', $own));
        Sanctum::actingAs($owner, ['*']);

        // A Platform Operator is not a tenant Owner: platform authority does not open tenant sidebar management.
        $operator = $this->userWith($this->a, []);
        $this->artisan('platform:operator', ['action' => 'grant', '--user' => $operator->email, '--reason' => 'h4', '--approval' => 'x', '--yes' => true])->assertExitCode(0);
        $this->assertFalse(Gate::forUser($operator->fresh())->allows('viewAny', SidebarConfig::class));
        $this->assertFalse(Gate::forUser($operator->fresh())->allows('delete', $this->document($this->a)));

        // The Owner keeps its owner-management workflow (canonical, not by name).
        $this->assertTrue(RbacProtection::hasOwnerAuthority($owner));
        $staff = $this->userWith($this->a, []);
        $this->postJson("/api/v1/rbac/assignments/users/{$staff->id}/roles", ['role_id' => (string) $this->ownerRole->id])->assertOk();
    }

    // ===================================================================== tripwire: unreachable role-name policies stay unreachable

    public function test_role_name_policies_without_callers_stay_unreachable(): void
    {
        $roots = ['app', 'src', 'routes', 'resources/views'];
        $hits = [];
        foreach ($roots as $root) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($root), \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                $path = (string) $file;
                if (!str_ends_with($path, '.php') || str_contains($path, DIRECTORY_SEPARATOR . 'Policies' . DIRECTORY_SEPARATOR) || str_ends_with($path, 'AuthServiceProvider.php')) {
                    continue;
                }
                $source = (string) file_get_contents($path);
                foreach (self::UNREACHABLE_ROLE_NAME_POLICIES as $model) {
                    $var = lcfirst($model);
                    // ability call whose SUBJECT argument is the model (class or a same-named variable), incl. @can/can: forms
                    // subject: the model class, or ANY variable whose name contains the model name (case-insensitive), e.g.
                    // $changeRequest, $crChangeRequest, $rfiItem. Known limit (105 §2): a variable not named after the model or a
                    // variable ability is not detected; the Owner gate for these policies is the real control.
                    $subject = '(\\$\w*' . $var . '\w*\b|\\$\w*' . $model . '\w*\b|(\\?App\\\\Models\\\\)?' . $model . '::class)';
                    $patterns = [
                        '/(->authorize|->authorizeForUser|->can|->cannot|->canAny|->allows|->denies|->inspect|Gate::(allows|denies|check|any|none|inspect|authorize)|@can|@cannot|@canany)\s*\([^;{}]{0,200}?,\s*\[?\s*' . $subject . '/si',
                        '/can:\s*[a-zA-Z_.-]+\s*,\s*[\'"]?\s*(\\?App\\\\Models\\\\)?' . $model . '\b/i',
                    ];
                    foreach ($patterns as $pattern) {
                        if (preg_match_all($pattern, $source, $m, PREG_OFFSET_CAPTURE)) {
                            foreach ($m[0] as [$_text, $offset]) {
                                $hits[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path) . ':' . (substr_count(substr($source, 0, (int) $offset), "\n") + 1) . " ({$model})";
                            }
                        }
                    }
                }
            }
        }
        // TaskPolicy's legacy abilities (view/create/comment/attachFiles/changeStatus/time tracking) likewise have no caller.
        foreach (['view', 'create', 'comment', 'attachFiles', 'changeStatus', 'viewTimeTracking', 'editTimeTracking'] as $ability) {
            $out = shell_exec('grep -rnE "(authorize|can|cannot|allows|denies)\\(\'' . $ability . '\', *\\$(task|assignedTask)\\b" ' . escapeshellarg(base_path('app')) . ' ' . escapeshellarg(base_path('src')) . ' 2>/dev/null') ?? '';
            if (trim((string) $out) !== '') {
                $hits[] = "TaskPolicy::{$ability}: " . trim((string) $out);
            }
        }

        // Unguarded dead document controllers (Gate 8 finding): no route may reach them until they carry policy checks.
        foreach (\Illuminate\Support\Facades\Route::getRoutes() as $route) {
            $action = $route->getActionName();
            if (str_starts_with($action, 'App\\Http\\Controllers\\Api\\DocumentController@')
                || in_array($action, ['App\\Http\\Controllers\\Web\\DocumentController@destroy', 'App\\Http\\Controllers\\Web\\DocumentController@download'], true)) {
                $hits[] = 'unguarded document controller routed: ' . implode('|', $route->methods()) . ' ' . $route->uri() . ' -> ' . $action;
            }
        }

        $this->assertSame([], $hits, 'A role-name policy or an unguarded document controller became reachable; it needs canonical rules under a new Owner gate first (105 §2).');
    }

    // ===================================================================== helpers

    /** @param list<Role> $roles */
    private function userWith(Tenant $tenant, array $roles, ?string $legacyRole = null): User
    {
        $user = User::factory()->create(['tenant_id' => (string) $tenant->id, 'is_active' => true]);
        if ($legacyRole !== null) {
            $user->forceFill(['role' => $legacyRole])->save();
        }
        foreach ($roles as $role) {
            UserRole::query()->create(['user_id' => (string) $user->id, 'role_id' => (string) $role->id]);
        }

        return $user->fresh();
    }

    private function document(Tenant $tenant): Document
    {
        $pm = User::factory()->create(['tenant_id' => (string) $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => (string) $tenant->id, 'created_by' => (string) $pm->id, 'pm_id' => (string) $pm->id]);

        return Document::factory()->create(['tenant_id' => (string) $tenant->id, 'project_id' => (string) $project->id, 'uploaded_by' => (string) $pm->id]);
    }

    private function sidebarConfig(?Tenant $tenant): SidebarConfig
    {
        // No migration creates `sidebar_configs` (finding SB-1, 105 §6): the policy only reads tenant_id, so an unsaved instance
        // is enough to exercise every object ability.
        $config = new SidebarConfig();
        $config->forceFill(['tenant_id' => $tenant?->id, 'role_name' => SidebarConfig::VALID_ROLES[0]]);

        return $config;
    }

    private function trashed(Document $doc): bool
    {
        return DB::table('documents')->where('id', (string) $doc->id)->whereNotNull('deleted_at')->exists();
    }
}
