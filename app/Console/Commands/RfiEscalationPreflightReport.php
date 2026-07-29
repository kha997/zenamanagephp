<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Rfi;
use Illuminate\Console\Command;

class RfiEscalationPreflightReport extends Command
{
    protected $signature = 'rfi:escalation-preflight-report {--output= : Path to write the CSV report to}';

    protected $description = 'List every legacy RFI row that needs an operator confirmation before the escalation cutover can run';

    public function handle(): int
    {
        $outputPath = $this->option('output') ?: storage_path('app/rfi-escalation-preflight-' . now()->format('Ymd-His') . '.csv');

        $rows = [];
        $rows[] = ['rfi_id', 'legacy_status', 'assigned_to', 'has_escalation_snapshot', 'proposed_lifecycle', 'proposed_escalation_state', 'reason'];

        Rfi::query()->where('status', 'escalated')->orderBy('id')->chunk(200, function ($chunk) use (&$rows) {
            foreach ($chunk as $rfi) {
                $proposedLifecycle = $rfi->assigned_to ? 'in_progress' : 'open';
                $rows[] = [$rfi->id, 'escalated', (string) $rfi->assigned_to, 'yes', $proposedLifecycle, 'unresolved', 'status=escalated, no event log to confirm timing'];
            }
        });

        Rfi::query()->where('status', '!=', 'escalated')->whereNotNull('escalated_to')->orderBy('id')
            ->chunk(200, function ($chunk) use (&$rows) {
                foreach ($chunk as $rfi) {
                    $rows[] = [$rfi->id, $rfi->status, (string) $rfi->assigned_to, 'yes', $rfi->status, 'resolved_estimated', 'has escalation snapshot but status already moved on; resolved_at will be estimated from updated_at'];
                }
            });

        Rfi::query()->where('status', 'pending')->orderBy('id')->chunk(200, function ($chunk) use (&$rows) {
            foreach ($chunk as $rfi) {
                $rows[] = [$rfi->id, 'pending', (string) $rfi->assigned_to, 'no', 'open', 'none', 'anomaly: pending status never set by any current action'];
            }
        });

        $handle = fopen($outputPath, 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        $this->info("Preflight report written to {$outputPath} (" . (count($rows) - 1) . ' records needing confirmation)');

        return self::SUCCESS;
    }
}
