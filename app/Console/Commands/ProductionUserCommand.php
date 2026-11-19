<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Admin\StaffAccountService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * GAP-115 U-1 — creates a staff account with a canonical role (accountant, PM, …) on a real environment, or resets its
 * password.
 *
 * - The password is generated and shown once.
 * - Only roles loaded by production:rbac are accepted.
 * - Every action is audited.
 */
class ProductionUserCommand extends Command
{
    protected $signature = 'production:user
        {--email= : Login email}
        {--name= : Full name (new account)}
        {--role= : One of the canonical roles (new account)}
        {--tenant= : Company slug or id — required when there are several companies}
        {--reset-password : Issue a new password for an existing account}';

    protected $description = 'Create a staff account with a canonical role, or reset its password (GAP-115) — generated one-time password';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->option('email')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('A valid --email is required.');

            return 1;
        }
        $tenant = $this->tenant();
        if ($tenant === null) {
            return 1;
        }

        return $this->option('reset-password') ? $this->resetPassword($tenant, $email) : $this->create($tenant, $email);
    }

    private function create(Tenant $tenant, string $email): int
    {
        $name = trim((string) $this->option('name'));
        $roleName = trim((string) $this->option('role'));
        if ($name === '') {
            $this->error('--name is required for a new account.');

            return 1;
        }
        if (!array_key_exists($roleName, ProductionRbacCommand::ROLES)) {
            $this->error('Unknown --role. Choose one of: ' . implode(', ', array_keys(ProductionRbacCommand::ROLES)) . '.');

            return 1;
        }
        /** @var Role|null $role */
        $role = Role::query()->where('name', $roleName)->first();
        if ($role === null || !$role->permissions()->exists()) {
            $this->error("Role {$roleName} has not been loaded — run php artisan production:rbac first.");

            return 1;
        }
        if (User::query()->where('email', $email)->exists()) {
            $this->error('An account with this email already exists — use --reset-password to issue a new password.');

            return 1;
        }

        // GAP-129: the account itself is created by the service shared with the admin pages (same audit row).
        [$user, $password] = app(StaffAccountService::class)->create($tenant, $email, $name, $role->name, null, 'cli');

        $this->info('Account created.');
        $this->line('Company: ' . $tenant->name . ' (' . $tenant->slug . ')');
        $this->line("Account: {$user->email} — role {$role->name}");
        $this->printPassword($password);

        return 0;
    }

    private function resetPassword(Tenant $tenant, string $email): int
    {
        /** @var User|null $user */
        $user = User::query()->where('tenant_id', (string) $tenant->id)->where('email', $email)->first();
        if ($user === null) {
            $this->error('No account with this email in that company.');

            return 1;
        }
        $password = app(StaffAccountService::class)->resetPassword($user, null, 'cli');

        $this->info('Password reset.');
        $this->printPassword($password);

        return 0;
    }

    private function tenant(): ?Tenant
    {
        $option = trim((string) $this->option('tenant'));
        if ($option !== '') {
            /** @var Tenant|null $tenant */
            $tenant = Tenant::query()->where('slug', $option)->orWhere('id', $option)->first();
            if ($tenant === null) {
                $this->error("No company with slug or id {$option}.");
            }

            return $tenant;
        }
        $count = Tenant::query()->count();
        if ($count === 0) {
            $this->error('No company yet — run php artisan production:bootstrap first.');

            return null;
        }
        if ($count > 1) {
            $this->error('Several companies exist — pass --tenant=<slug>.');

            return null;
        }

        /** @var Tenant $tenant */
        $tenant = Tenant::query()->firstOrFail();

        return $tenant;
    }

    private function printPassword(string $password): void
    {
        $this->line('Generated password (record this now, it will not be shown again): ' . OutputFormatter::escape($password));
    }
}
