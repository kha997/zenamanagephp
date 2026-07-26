<?php declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RfiEscalationConflictException;
use App\Models\Rfi;
use App\Models\RfiEscalation;
use Illuminate\Support\Facades\DB;

/**
 * Owns the escalation cycle ONLY. Knows nothing about RFI lifecycle status —
 * that belongs to RfiLifecycleService.
 */
class RfiEscalationService
{
    public function hasActiveEscalation(string $rfiId): bool
    {
        return RfiEscalation::where('rfi_id', $rfiId)->whereNull('resolved_at')->exists();
    }

    public function escalate(Rfi $rfi, string $escalatedTo, string $escalatedBy, string $reason): RfiEscalation
    {
        return DB::transaction(function () use ($rfi, $escalatedTo, $escalatedBy, $reason) {
            $lockedRfi = Rfi::where('id', $rfi->id)->lockForUpdate()->firstOrFail();

            $activeExists = RfiEscalation::where('rfi_id', $lockedRfi->id)
                ->whereNull('resolved_at')
                ->lockForUpdate()
                ->exists();

            if ($activeExists) {
                throw new RfiEscalationConflictException('An active escalation already exists for this RFI.');
            }

            $escalation = RfiEscalation::create([
                'rfi_id' => $lockedRfi->id,
                'tenant_id' => $lockedRfi->tenant_id,
                'escalated_to' => $escalatedTo,
                'escalated_by' => $escalatedBy,
                'escalated_at' => now(),
                'escalation_reason' => $reason,
            ]);

            $lockedRfi->update([
                'current_escalation_id' => $escalation->id,
                'escalated_to' => $escalation->escalated_to,
                'escalated_by' => $escalation->escalated_by,
                'escalated_at' => $escalation->escalated_at,
                'escalation_reason' => $escalation->escalation_reason,
            ]);

            return $escalation;
        });
    }
}
