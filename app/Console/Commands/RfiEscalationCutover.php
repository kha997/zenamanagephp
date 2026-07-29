<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Rfi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RfiEscalationCutover extends Command
{
    protected $signature = 'rfi:escalation-cutover';

    protected $description = 'Flip application-level validation to reject status=escalated/pending, ONLY if every legacy row has an operator confirmation';

    public function handle(): int
    {
        $unconfirmed = function ($query) {
            $query->select(DB::raw(1))->from('rfi_legacy_migration_confirmations')
                ->whereColumn('rfi_legacy_migration_confirmations.rfi_id', 'rfis.id');
        };

        $unconfirmedEscalated = Rfi::query()->where('status', 'escalated')->whereNotExists($unconfirmed)->count();
        $unconfirmedPending = Rfi::query()->where('status', 'pending')->whereNotExists($unconfirmed)->count();
        $unconfirmedSnapshot = Rfi::query()->where('status', '!=', 'escalated')->whereNotNull('escalated_to')->whereNotExists($unconfirmed)->count();

        $total = $unconfirmedEscalated + $unconfirmedPending + $unconfirmedSnapshot;

        if ($total > 0) {
            $this->error("Cutover blocked: {$total} legacy RFI record(s) still unconfirmed (escalated={$unconfirmedEscalated}, pending={$unconfirmedPending}, snapshot-only={$unconfirmedSnapshot}). Run rfi:escalation-preflight-report and rfi:confirm-legacy-escalation for each one first.");
            return self::FAILURE;
        }

        DB::table('rfi_escalation_migration_state')->insert(['cutover_completed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->info('Cutover complete: application-level validation will now reject status=escalated/pending on new writes.');

        return self::SUCCESS;
    }
}
