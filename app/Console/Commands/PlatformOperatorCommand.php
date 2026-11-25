<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Rbac\PlatformOperators;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * OWN-2026-024 (O-17h) H2b — Owner Gate 2 D6: provision / revoke Platform Operators from the server console only.
 *
 * Authority = shell access to the host (independent of any tenant Owner or tenant role-management grant). Writes the
 * platform_operators ledger, the global "Platform Operator" role assignment and an audit row in one transaction. No HTTP route
 * reaches this; migrations and seeders never provision anyone.
 */
class PlatformOperatorCommand extends Command
{
    protected $signature = 'platform:operator
        {action : grant | revoke | list}
        {--user= : e-mail of the app user}
        {--reason= : why (required)}
        {--approval= : approval reference (required for grant)}
        {--yes : do not ask for confirmation}';

    protected $description = 'Provision or revoke a Platform Operator (OWN-2026-024 D6) — server console only';

    public function handle(): int
    {
        $action = (string) $this->argument('action');
        if ($action === 'list') {
            $rows = DB::table('platform_operators')->join('users', 'users.id', '=', 'platform_operators.user_id')
                ->whereNull('platform_operators.revoked_at')->get(['users.email', 'platform_operators.granted_at', 'platform_operators.granted_by_actor']);
            $this->table(['User', 'Granted at', 'Granted by'], $rows->map(fn ($r): array => [$r->email, $r->granted_at, $r->granted_by_actor])->all());

            return self::SUCCESS;
        }
        if (!in_array($action, ['grant', 'revoke'], true)) {
            $this->error('Action must be grant, revoke or list.');

            return self::INVALID;
        }

        /** @var User|null $user */
        $user = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim((string) $this->option('user')))])->first();
        if ($user === null) {
            $this->error('Unknown user.');

            return self::FAILURE;
        }
        if (!$this->option('yes') && !$this->confirm("{$action} Platform Operator for {$user->email}?")) {
            return self::FAILURE;
        }

        try {
            if ($action === 'grant') {
                PlatformOperators::grant($user, (string) $this->option('reason'), (string) $this->option('approval'));
            } else {
                PlatformOperators::revoke($user, (string) $this->option('reason'));
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("Done: {$action} Platform Operator for {$user->email}.");

        return self::SUCCESS;
    }
}
