<?php

declare(strict_types=1);

namespace Tests\Feature\Recovery;

use App\Models\Account;
use App\Models\Contract;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\TimeTracking\TimeEntry;
use App\Models\User;
use App\Services\TimeTracking\TimeEntryService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ZenaProjectManagerRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * UAT-RECOVERY-01 / O-18 (Owner decision 2026-11-24: Option D, including Project Manager).
 *
 * - Project Member: deterministic 26-code baseline on the first and every later seed run.
 * - Project Manager: gains the six approved operational codes; nothing else.
 * - crm.view, contract.view, boq.view, vendor.view, event-record.view are withheld from Project Member.
 * - Re-seeding is additive: grants added by hand are never deleted (and nothing is revoked automatically).
 */
#[Group('uat-recovery-05-o18')]
class UatRecovery05O18PermissionBaselineTest extends TestCase
{
    use RefreshDatabase;

    /** The decided Project Member baseline (20 first-seed codes before O-18 + the six approved codes). */
    private const MEMBER_BASELINE = [
        'baseline.view', 'change_request.create', 'change_request.read', 'compensation.view', 'component.read',
        'design-item.view', 'document.create', 'document.read', 'interaction_log.create', 'interaction_log.read',
        'material-receipt.view', 'material.read', 'material.view', 'notification.read', 'project.read',
        'rfi.resolve-escalation', 'settings.general.read', 'settings.security.read', 'site_diary.view', 'task.read',
        'task.update', 'task.view', 'treasury.view', 'user-preferences.read', 'work.update', 'work.view',
    ];

    /** Project Manager before O-18 (60 codes, identical on every seed run) — pinned to detect unintended expansion. */
    private const MANAGER_BEFORE_O18 = [
        'baseline.create', 'baseline.delete', 'baseline.edit', 'baseline.view', 'change-request.approve',
        'change-request.create', 'change-request.reject', 'change-request.view', 'change_request.approve',
        'change_request.create', 'change_request.read', 'change_request.reject', 'change_request.submit',
        'compensation.preview', 'compensation.stats', 'compensation.view', 'component.create', 'component.read',
        'component.update', 'document.approve', 'document.create', 'document.read', 'document.update',
        'interaction_log.approve', 'interaction_log.create', 'interaction_log.read', 'notification.manage_rules',
        'notification.read', 'project.create', 'project.read', 'project.update', 'rfi.cancel', 'rfi.escalate',
        'rfi.resolve-escalation', 'settings.general.read', 'settings.general.update', 'settings.security.read',
        'settings.security.update', 'task.assign', 'task.create', 'task.read', 'task.update', 'template.apply',
        'template.create', 'template.edit', 'template.view', 'treasury.create_expense', 'treasury.create_transfer',
        'treasury.declare_funding', 'treasury.submit_expense', 'treasury.view', 'user-preferences.read',
        'user-preferences.update', 'user.manage_roles', 'user.read', 'user.view', 'work.approve', 'work.export',
        'work.update', 'work.view',
    ];

    private const APPROVED_SIX = ['task.view', 'site_diary.view', 'design-item.view', 'material.read', 'material-receipt.view', 'material.view'];

    private const WITHHELD = ['crm.view', 'contract.view', 'boq.view', 'vendor.view', 'event-record.view'];

    /** Administrative codes no ordinary project role may hold (O-19 G-2). */
    private const ADMIN_ONLY = [
        'role.view', 'role.create', 'role.edit', 'role.delete', 'role.assign', 'permission.view', 'permission.create',
        'permission.edit', 'permission.delete', 'permission.export', 'permission.import', 'audit.view',
        'project.assign_manager', 'ai.suggest', 'crm.manage', 'payroll.manage', 'payroll.close',
    ];

    // ------------------------------------------------------------------ helpers

    /** @return list<string> */
    private function codes(string $role): array
    {
        $codes = DB::table('role_permissions')
            ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('roles.name', $role)
            ->pluck('permissions.code')
            ->map(static fn ($c): string => (string) $c)
            ->all();
        sort($codes);

        return array_values($codes);
    }

    /** @return array<string, list<string>> */
    private function snapshot(): array
    {
        return [
            'System Admin' => $this->codes('System Admin'),
            'Project Manager' => $this->codes('Project Manager'),
            'Project Member' => $this->codes('Project Member'),
        ];
    }

    /** @param list<string> $codes @return list<string> */
    private static function sorted(array $codes): array
    {
        sort($codes);

        return array_values($codes);
    }

    private function staff(string $seededRole, Tenant $tenant): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => null]);
        $roleId = DB::table('roles')->where('name', $seededRole)->value('id');
        $this->assertNotNull($roleId, "seeded role '{$seededRole}' must exist");
        DB::table('user_roles')->insert(['user_id' => $user->id, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function grantByHand(string $role, string $code): void
    {
        DB::table('role_permissions')->insert([
            'role_id' => DB::table('roles')->where('name', $role)->value('id'),
            'permission_id' => DB::table('permissions')->where('code', $code)->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ------------------------------------------------------------------ seed state

    public function test_first_seed_gives_member_exactly_the_26_code_baseline(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertCount(26, self::MEMBER_BASELINE);
        $this->assertSame(self::sorted(self::MEMBER_BASELINE), $this->codes('Project Member'));
    }

    public function test_manager_gains_exactly_the_six_approved_codes_and_nothing_else(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(self::sorted(ZenaProjectManagerRolePermissionSeeder::O18_OPERATIONAL_PERMISSION_CODES), self::sorted(self::APPROVED_SIX));
        $this->assertSame(self::sorted(array_merge(self::MANAGER_BEFORE_O18, self::APPROVED_SIX)), $this->codes('Project Manager'));
    }

    public function test_first_second_and_third_seed_are_identical_for_every_role(): void
    {
        $this->seed(DatabaseSeeder::class);
        $first = $this->snapshot();
        $this->seed(DatabaseSeeder::class);
        $second = $this->snapshot();
        $this->seed(DatabaseSeeder::class);
        $third = $this->snapshot();

        $this->assertSame($first, $second, 'second seed must not change any role grant');
        $this->assertSame($first, $third, 'third seed must not change any role grant');
        $this->assertCount(26, $third['Project Member']);
        $this->assertCount(66, $third['Project Manager']);
        // OWN-2026-024 H2a (Owner Gate 2, D1/D6): System Admin keeps every permission EXCEPT the platform.* namespace, which no
        // tenant/admin role may receive implicitly. Amended from "every permission" (the only change to this assertion).
        $this->assertSame(DB::table('permissions')->where('code', 'not like', 'platform.%')->count(), count($third['System Admin']), 'System Admin keeps every non-platform permission');
        $this->assertSame([], array_values(array_filter($third['System Admin'], static fn (string $code): bool => str_starts_with($code, 'platform.'))), 'System Admin holds no platform permission');
    }

    public function test_reseeding_never_deletes_grants_added_by_hand(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->grantByHand('Project Member', 'document.update');
        $this->grantByHand('Project Manager', 'webhook.view');

        $this->seed(DatabaseSeeder::class);

        $this->assertContains('document.update', $this->codes('Project Member'));
        $this->assertContains('webhook.view', $this->codes('Project Manager'));
        // ... and re-seeding adds nothing else beyond the baseline.
        $this->assertSame(self::sorted(array_merge(self::MEMBER_BASELINE, ['document.update'])), $this->codes('Project Member'));
    }

    public function test_withheld_and_admin_codes_are_never_granted_to_member_or_manager_by_seeding(): void
    {
        foreach ([1, 2, 3] as $run) {
            $this->seed(DatabaseSeeder::class);
            foreach (['Project Member', 'Project Manager'] as $role) {
                $this->assertSame([], array_values(array_intersect($this->codes($role), self::WITHHELD)), "{$role} after seed #{$run}: withheld commercial codes");
                $this->assertSame([], array_values(array_intersect($this->codes($role), self::ADMIN_ONLY)), "{$role} after seed #{$run}: admin-only codes");
            }
        }
    }

    // ------------------------------------------------------------------ effective access (HTTP)

    public function test_member_and_manager_can_open_my_work_today_schedule_and_own_timesheet(): void
    {
        $this->seed(DatabaseSeeder::class);
        $tenant = Tenant::factory()->create();
        $member = $this->staff('Project Member', $tenant);
        $manager = $this->staff('Project Manager', $tenant);
        Project::factory()->create(['tenant_id' => $tenant->id, 'pm_id' => $manager->id]);

        foreach (['Project Member' => $member, 'Project Manager' => $manager] as $label => $user) {
            foreach (['/app/my-work', '/app/today', '/operator/schedule', '/app/time'] as $uri) {
                $this->assertSame(200, $this->actingAs($user)->get($uri)->getStatusCode(), "{$label} GET {$uri}");
            }
        }
    }

    public function test_member_is_denied_commercial_data_and_the_ai_summary(): void
    {
        $this->seed(DatabaseSeeder::class);
        $tenant = Tenant::factory()->create();
        $member = $this->staff('Project Member', $tenant);
        $manager = $this->staff('Project Manager', $tenant);
        $project = Project::factory()->create(['tenant_id' => $tenant->id, 'pm_id' => $manager->id]);
        $account = Account::query()->create(['tenant_id' => (string) $tenant->id, 'display_name' => 'O18 Account']);
        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id, 'account_id' => (string) $account->id, 'opportunity_name' => 'O18 Opp',
            'pipeline_stage' => Opportunity::STAGE_BRIEF_DISCOVERY, 'sales_owner_id' => (string) $manager->id, 'created_by' => (string) $manager->id,
        ]);
        $contract = Contract::factory()->create(['tenant_id' => $tenant->id, 'project_id' => $project->id, 'created_by' => $manager->id]);

        $api = [
            '/api/zena/crm/opportunities', '/api/zena/crm/accounts', '/api/zena/crm/leads',
            '/api/zena/projects/' . $project->id . '/contracts', '/api/zena/projects/' . $project->id . '/contracts/' . $contract->id,
            '/api/zena/vendors', '/api/zena/boqs', '/api/zena/event-records',
        ];
        $web = [
            '/operator/crm', '/operator/crm/opportunities/' . $opportunity->id, '/operator/crm/reports',
            '/operator/contracts', '/operator/contracts/' . $contract->id, '/operator/vendors', '/operator/boqs',
            '/operator/activity-feed',
        ];

        // Project Manager never held these codes either: O-18 must not have expanded them.
        foreach (['Project Member' => $member, 'Project Manager' => $manager] as $label => $user) {
            foreach ($api as $uri) {
                $this->assertSame(403, $this->actingAs($user, 'sanctum')->getJson($uri)->getStatusCode(), "{$label} GET {$uri}");
            }
            foreach ($web as $uri) {
                $this->assertNotSame(200, $this->actingAs($user)->get($uri)->getStatusCode(), "{$label} GET {$uri}");
            }
        }

        $this->assertNotSame(200, $this->actingAs($member)->post('/operator/crm/opportunities/' . $opportunity->id . '/ai-summary')->getStatusCode(), 'AI summary');
    }

    public function test_tenant_isolation_holds_for_the_new_operational_grants(): void
    {
        $this->seed(DatabaseSeeder::class);
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $member = $this->staff('Project Member', $tenantA);
        $projectB = Project::factory()->create(['tenant_id' => $tenantB->id]);
        $foreign = Task::factory()->create(['tenant_id' => $tenantB->id, 'project_id' => $projectB->id, 'name' => 'O18-FOREIGN-TASK', 'title' => 'O18-FOREIGN-TASK']);

        $this->assertNotSame(200, $this->actingAs($member, 'sanctum')->getJson('/api/zena/tasks/' . $foreign->id)->getStatusCode(), 'foreign-tenant task');
        $list = $this->actingAs($member, 'sanctum')->getJson('/api/zena/tasks');
        $this->assertSame(200, $list->getStatusCode());
        $this->assertStringNotContainsString((string) $foreign->id, (string) $list->getContent());
        $this->assertStringNotContainsString('O18-FOREIGN-TASK', (string) $this->actingAs($member)->get('/app/my-work')->getContent());
    }

    public function test_task_view_does_not_open_other_peoples_timesheets(): void
    {
        Carbon::setTestNow('2026-11-05 09:00:00');

        try {
            $this->seed(DatabaseSeeder::class);
            $tenant = Tenant::factory()->create();
            $a = $this->staff('Project Member', $tenant);
            $b = $this->staff('Project Member', $tenant);
            $entry = app(TimeEntryService::class)->addManual($b, ['category' => TimeEntry::CATEGORY_ADMIN, 'work_date' => '2026-11-04', 'minutes' => '60', 'note' => 'B own entry']);
            $before = $entry->fresh()?->only(['minutes', 'status', 'note']);

            $this->actingAs($a)->post('/app/time/entries/' . $entry->id, ['work_date' => '2026-11-04', 'minutes' => 480, 'note' => 'tamper']);
            $this->actingAs($a)->post('/app/time/entries/' . $entry->id . '/delete');
            $this->assertSame(403, $this->actingAs($a)->post('/app/time/review', ['ids' => [(string) $entry->id], 'action' => 'approve'])->getStatusCode());
            $this->assertSame(403, $this->actingAs($a)->get('/app/time/report')->getStatusCode());
            $this->assertSame(403, $this->actingAs($a)->get('/app/time/report?export=csv')->getStatusCode());
            $this->assertSame(403, $this->actingAs($a)->get('/app/time/review')->getStatusCode());

            $after = TimeEntry::query()->withoutGlobalScopes()->find($entry->id);
            $this->assertNotNull($after, 'another member cannot delete the entry');
            $this->assertSame($before, $after->only(['minutes', 'status', 'note']), 'another member cannot edit the entry');
        } finally {
            Carbon::setTestNow();
        }
    }
}
