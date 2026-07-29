<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Rfi;
use App\Models\RfiEscalation;
use App\Models\RfiLegacyMigrationConfirmation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RfiConfirmLegacyEscalation extends Command
{
    protected $signature = 'rfi:confirm-legacy-escalation
        {rfi_id : The RFI to confirm}
        {--lifecycle= : open|in_progress|answered|closed|cancelled}
        {--escalation= : unresolved|resolved|none}
        {--confirmed-by= : User id of the operator confirming this record}
        {--reason= : Required. Why this lifecycle/escalation state was chosen for this record}';

    protected $description = 'Record an operator confirmation (with a full source snapshot) for one legacy RFI row ahead of the escalation cutover';

    public function handle(): int
    {
        $rfiId = $this->argument('rfi_id');
        $lifecycle = $this->option('lifecycle');
        $escalationState = $this->option('escalation');
        $confirmedBy = $this->option('confirmed-by');
        $reason = $this->option('reason');

        if (!in_array($lifecycle, ['open', 'in_progress', 'answered', 'closed', 'cancelled'], true)) {
            $this->error('--lifecycle must be one of: open, in_progress, answered, closed, cancelled');
            return self::FAILURE;
        }

        if (!in_array($escalationState, ['unresolved', 'resolved', 'none'], true)) {
            $this->error('--escalation must be one of: unresolved, resolved, none');
            return self::FAILURE;
        }

        if (!$confirmedBy) {
            $this->error('--confirmed-by is required');
            return self::FAILURE;
        }

        if (!$reason) {
            $this->error('--reason is required — the confirmation must record why this state was chosen, not just what was chosen');
            return self::FAILURE;
        }

        $rfi = Rfi::query()->find($rfiId);

        if (!$rfi) {
            $this->error("RFI {$rfiId} not found");
            return self::FAILURE;
        }

        $sourceSnapshot = [
            'status' => $rfi->status,
            'assigned_to' => $rfi->assigned_to,
            'escalated_to' => $rfi->escalated_to,
            'escalated_by' => $rfi->escalated_by,
            'escalated_at' => $rfi->escalated_at?->toIso8601String(),
            'escalation_reason' => $rfi->escalation_reason,
            'updated_at' => $rfi->updated_at->toIso8601String(),
        ];

        DB::transaction(function () use ($rfi, $lifecycle, $escalationState, $confirmedBy, $reason, $sourceSnapshot) {
            if ($escalationState === 'unresolved') {
                $escalation = RfiEscalation::query()->create([
                    'rfi_id' => $rfi->id, 'tenant_id' => $rfi->tenant_id,
                    'escalated_to' => $rfi->escalated_to ?? $confirmedBy,
                    'escalated_by' => $rfi->escalated_by ?? $confirmedBy,
                    'escalated_at' => $rfi->escalated_at ?? now(),
                    'escalation_reason' => $rfi->escalation_reason ?? 'Backfilled from legacy status=escalated during migration confirmation.',
                ]);
                $rfi->current_escalation_id = $escalation->id;
            } elseif ($escalationState === 'resolved') {
                $escalation = RfiEscalation::query()->create([
                    'rfi_id' => $rfi->id, 'tenant_id' => $rfi->tenant_id,
                    'escalated_to' => $rfi->escalated_to ?? $confirmedBy,
                    'escalated_by' => $rfi->escalated_by ?? $confirmedBy,
                    'escalated_at' => $rfi->escalated_at ?? $rfi->updated_at,
                    'escalation_reason' => $rfi->escalation_reason ?? 'Backfilled: legacy escalation snapshot found on a non-escalated RFI.',
                    'resolved_at' => $rfi->updated_at,
                    'resolved_by' => null,
                    'resolution' => 'Backfilled automatically: exact resolution time/actor unknown, no event log available. Estimated from updated_at.',
                    'resolution_type' => RfiEscalation::RESOLUTION_TYPE_MANUALLY_RESOLVED,
                ]);
                $rfi->current_escalation_id = null;
            } else {
                $rfi->current_escalation_id = null;
            }

            $rfi->status = $lifecycle;
            $rfi->save();

            RfiLegacyMigrationConfirmation::query()->updateOrCreate(
                ['rfi_id' => $rfi->id],
                [
                    'confirmed_by' => $confirmedBy,
                    'confirmed_at' => now(),
                    'confirmed_lifecycle_status' => $lifecycle,
                    'confirmed_escalation_state' => $escalationState,
                    'reason' => $reason,
                    'source_snapshot' => json_encode($sourceSnapshot),
                ],
            );
        });

        $this->info("Confirmed RFI {$rfi->id}: lifecycle={$lifecycle}, escalation={$escalationState}");

        return self::SUCCESS;
    }
}
