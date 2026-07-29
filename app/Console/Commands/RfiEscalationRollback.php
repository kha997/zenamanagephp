<?php declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RfiEscalationRollback extends Command
{
    protected $signature = 'rfi:escalation-rollback {--reason=}';

    protected $description = 'Revert application-level validation to pre-cutover behavior. Never touches rfi_escalations or confirmation data.';

    public function handle(): int
    {
        $reason = $this->option('reason');

        if (!$reason) {
            $this->error('--reason is required — rollback must record why it happened');
            return self::FAILURE;
        }

        DB::table('rfi_escalation_migration_state')->update(['cutover_completed_at' => null, 'updated_at' => now()]);

        Log::warning('rfi_escalation_cutover_rolled_back', ['reason' => $reason, 'rolled_back_at' => now()->toIso8601String()]);

        $this->info('Cutover flag cleared. status=escalated/pending are accepted again at the application layer. No rows in rfi_escalations or rfi_legacy_migration_confirmations were modified.');

        return self::SUCCESS;
    }
}
