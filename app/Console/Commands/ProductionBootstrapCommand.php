<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * GAP-049 production-safe first-database bootstrap. This command never
 * invokes any bulk data-seeding mechanism. Creates only the real initial
 * tenant and one real initial administrator. No demo data, no fixed/default
 * password. Idempotent/fail-closed: refuses to run if the database is not
 * empty. See docs/owner-decisions/GAP-049/02-design.md §3a.
 */
class ProductionBootstrapCommand extends Command
{
    private const BOOTSTRAP_MARKER_KEY = 'gap049_production_bootstrap_completed';

    protected $signature = 'production:bootstrap
        {--tenant-name= : Real organization name}
        {--tenant-slug= : Real organization slug}
        {--admin-email= : Real initial administrator email}';

    protected $description = 'Bootstrap the first real production tenant and administrator (GAP-049) — never runs bulk seeders, never creates demo data';

    public function handle(): int
    {
        $alreadyBootstrapped = (bool) Cache::get(self::BOOTSTRAP_MARKER_KEY, false);

        if (Tenant::count() > 0) {
            if ($alreadyBootstrapped) {
                $this->warn('Bootstrap already completed previously — refusing to run again (idempotent fail-closed).');
                return 2;
            }

            $this->warn('Database already contains tenant data not created by this command — verify existing state instead of bootstrapping.');
            $this->line('If this is the existing-data case, verify the real tenant/admin/RBAC state instead of bootstrapping.');
            return 1;
        }

        $tenantName = $this->option('tenant-name');
        $tenantSlug = $this->option('tenant-slug');
        $adminEmail = $this->option('admin-email');

        if (!$tenantName || !$tenantSlug || !$adminEmail) {
            $this->error('--tenant-name, --tenant-slug, and --admin-email are all required.');
            return 1;
        }

        $password = Str::password(20, symbols: true);

        DB::transaction(function () use ($tenantName, $tenantSlug, $adminEmail, $password) {
            $tenant = Tenant::create([
                'name' => $tenantName,
                'slug' => $tenantSlug,
                'status' => 'active',
                'is_active' => true,
            ]);

            $admin = User::create([
                'tenant_id' => $tenant->id,
                'name' => $tenantName . ' Administrator',
                'email' => $adminEmail,
                'password' => Hash::make($password),
                'is_active' => true,
            ]);

            $role = Role::firstOrCreate(
                ['name' => 'super_admin'],
                ['scope' => 'system']
            );
            $admin->roles()->syncWithoutDetaching([$role->id]);
        });

        Cache::forever(self::BOOTSTRAP_MARKER_KEY, true);

        $this->info('Bootstrap complete.');
        $this->line("Tenant: {$tenantName} ({$tenantSlug})");
        $this->line("Administrator: {$adminEmail}");
        $this->line("Generated password (record this now, it will not be shown again): {$password}");

        return 0;
    }
}
