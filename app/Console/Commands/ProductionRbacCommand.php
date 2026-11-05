<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Database\Seeders\PayrollRolePermissionSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TreasuryRolePermissionSeeder;
use Database\Seeders\V1PermissionGapSeeder;
use Database\Seeders\WorkTemplateBaselineSeeder;
use Database\Seeders\ZenaAdminRolePermissionSeeder;
use Database\Seeders\ZenaPermissionsSeeder;
use Database\Seeders\ZenaProjectManagerRolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-115 R-1 — loads roles and permissions on a real environment. Run it after deploy:migrate and before / after
 * production:bootstrap.
 *
 * - Creates the canonical roles if missing.
 * - Runs only the permission and role-grant seeders listed below.
 * - Once a company exists, adds the baseline work templates.
 * - Never creates companies, users or demo data. Idempotent; --dry-run writes nothing.
 */
class ProductionRbacCommand extends Command
{
    /** Canonical roles: name => description. Role grants match these names (TreasuryRolePermissionSeeder lists). */
    public const ROLES = [
        'super_admin' => 'Owner / quản trị — toàn quyền',
        'Finance' => 'Kế toán — lương, Ngân quỹ (không chốt)',
        'Project Manager' => 'Quản lý dự án',
        'site_engineer' => 'Kỹ sư công trường',
        'Project Member' => 'Thành viên dự án (xem)',
    ];

    /**
     * Permission catalogue and role grants only — no seeder here creates companies, users or demo data. Order matters:
     * the canonical catalogue first, because PermissionSeeder grants Project Member codes only it creates; PermissionSeeder
     * sync()s Project Manager / Project Member, so ZenaPermissionsSeeder runs again right after to restore the grants it
     * adds (templates, change requests); the additive grant seeders follow.
     */
    private const SEEDERS = [
        ZenaPermissionsSeeder::class,
        PermissionSeeder::class,
        ZenaPermissionsSeeder::class,
        ZenaAdminRolePermissionSeeder::class,
        TreasuryRolePermissionSeeder::class,
        PayrollRolePermissionSeeder::class,
        ZenaProjectManagerRolePermissionSeeder::class,
        V1PermissionGapSeeder::class,
    ];

    /**
     * Baseline grants the go-live runbook relies on, added after the seeders (never removes anything): every staff role
     * logs time (task.view, GAP-112), project staff open project pages.
     */
    private const BASELINE_GRANTS = [
        'Finance' => ['task.view'],
        'Project Manager' => ['task.view', 'project.view'],
        'site_engineer' => ['task.view', 'project.view'],
        'Project Member' => ['task.view', 'project.view'],
    ];

    protected $signature = 'production:rbac {--dry-run : Show what would be created and loaded, write nothing}';

    protected $description = 'Load canonical roles, permissions and role grants on a real environment (GAP-115) — never creates users or demo data';

    public function handle(): int
    {
        foreach (['roles', 'permissions', 'role_permissions'] as $table) {
            if (!Schema::hasTable($table)) {
                $this->error("Table {$table} is missing — run php artisan deploy:migrate first.");

                return 1;
            }
        }
        $withTemplates = Schema::hasTable('work_templates') && Tenant::query()->exists();

        if ($this->option('dry-run')) {
            $this->info('Dry run — nothing will be written.');
            $this->table(['Role', 'State'], array_map(
                static fn (string $name): array => [$name, Role::query()->where('name', $name)->exists() ? 'exists' : 'will be created'],
                array_keys(self::ROLES),
            ));
            $this->line('Seeders to run: ' . implode(', ', array_map(static fn (string $c): string => class_basename($c), self::SEEDERS)));
            $this->line('Baseline grants: ' . implode('; ', array_map(static fn (string $r, array $c): string => $r . ' ' . implode(', ', $c), array_keys(self::BASELINE_GRANTS), self::BASELINE_GRANTS)));
            $this->line('Baseline work templates: ' . ($withTemplates ? 'yes (company exists)' : 'no (no company yet)'));

            return 0;
        }

        DB::transaction(function () use ($withTemplates): void {
            foreach (self::ROLES as $name => $description) {
                Role::query()->firstOrCreate(['name' => $name], ['scope' => 'system', 'description' => $description, 'is_active' => true]);
            }
            foreach (self::SEEDERS as $class) {
                $this->runSeeder($class);
            }
            foreach (self::BASELINE_GRANTS as $roleName => $codes) {
                /** @var Role $role */
                $role = Role::query()->where('name', $roleName)->firstOrFail();
                $role->permissions()->syncWithoutDetaching(Permission::query()->whereIn('code', $codes)->pluck('id')->all());
            }
            if ($withTemplates) {
                $this->runSeeder(WorkTemplateBaselineSeeder::class);
            }
        });

        $this->info('Roles and permissions loaded.');
        $this->table(['Role', 'Permissions'], array_map(
            static fn (string $name): array => [$name, (string) (Role::query()->where('name', $name)->first()?->permissions()->count() ?? 0)],
            array_keys(self::ROLES),
        ));
        if (!$withTemplates) {
            $this->line('No company yet: run production:bootstrap, then run production:rbac again to add the baseline work templates.');
        }

        return 0;
    }

    /**
     * @param class-string<Seeder> $class
     */
    private function runSeeder(string $class): void
    {
        /** @var Seeder $seeder */
        $seeder = $this->laravel->make($class);
        $seeder->setContainer($this->laravel)->setCommand($this)->__invoke();
    }
}
