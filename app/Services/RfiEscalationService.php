<?php declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RfiEscalationConflictException;
use App\Exceptions\RfiEscalationNotFoundException;
use App\Models\Notification;
use App\Models\Rfi;
use App\Models\RfiEscalation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Owns the escalation cycle ONLY. Knows nothing about RFI lifecycle status —
 * that belongs to RfiLifecycleService.
 */
class RfiEscalationService
{
    public function hasActiveEscalation(string $rfiId): bool
    {
        return RfiEscalation::query()->where('rfi_id', $rfiId)->whereNull('resolved_at')->exists();
    }

    public function escalate(Rfi $rfi, string $escalatedTo, string $escalatedBy, string $reason): RfiEscalation
    {
        $escalation = null;

        DB::transaction(function () use ($rfi, $escalatedTo, $escalatedBy, $reason, &$escalation) {
            /** @var Rfi $lockedRfi */
            $lockedRfi = Rfi::query()->where('id', $rfi->id)->lockForUpdate()->firstOrFail();

            $activeExists = RfiEscalation::query()->where('rfi_id', $lockedRfi->id)
                ->whereNull('resolved_at')
                ->lockForUpdate()
                ->exists();

            if ($activeExists) {
                throw new RfiEscalationConflictException('An active escalation already exists for this RFI.');
            }

            $escalation = RfiEscalation::query()->create([
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
        });

        /** @var RfiEscalation $escalation */
        $this->dispatchEscalatedNotification($escalation);

        return $escalation;
    }

    private function dispatchEscalatedNotification(RfiEscalation $escalation): void
    {
        try {
            $target = User::query()->find($escalation->escalated_to);

            if ($target) {
                Notification::query()->create([
                    'user_id' => $escalation->escalated_to,
                    'tenant_id' => $escalation->tenant_id,
                    'type' => 'rfi_escalated',
                    'priority' => Notification::PRIORITY_CRITICAL,
                    'title' => 'RFI đã được escalate',
                    'body' => $escalation->escalation_reason,
                    'channel' => Notification::CHANNEL_INAPP,
                    'data' => [
                        'rfi_id' => $escalation->rfi_id,
                        'escalation_id' => $escalation->id,
                        'escalation_reason' => $escalation->escalation_reason,
                        'escalated_by' => $escalation->escalated_by,
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('rfi_escalation_notification_failed', [
                'escalation_id' => $escalation->id,
                'rfi_id' => $escalation->rfi_id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function resolveEscalation(
        Rfi $rfi,
        string $resolvedBy,
        string $resolution,
        string $resolutionType = RfiEscalation::RESOLUTION_TYPE_MANUALLY_RESOLVED,
    ): RfiEscalation {
        $escalation = null;

        DB::transaction(function () use ($rfi, $resolvedBy, $resolution, $resolutionType, &$escalation) {
            /** @var Rfi $lockedRfi */
            $lockedRfi = Rfi::query()->where('id', $rfi->id)->lockForUpdate()->firstOrFail();

            $lockedRfi->assertEscalationPointerIntegrity();

            if (!$lockedRfi->current_escalation_id) {
                $alreadyResolved = RfiEscalation::query()->where('rfi_id', $lockedRfi->id)
                    ->whereNotNull('resolved_at')
                    ->exists();

                if ($alreadyResolved) {
                    // Pointer was already cleared by a prior resolveEscalation() call —
                    // this is a double-resolve attempt (e.g. duplicate submit), not "never escalated".
                    throw new RfiEscalationConflictException('This escalation has already been resolved.');
                }

                throw new RfiEscalationNotFoundException('This RFI has no active escalation.');
            }

            /** @var RfiEscalation $escalation */
            $escalation = RfiEscalation::query()->where('id', $lockedRfi->current_escalation_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($escalation->resolved_at !== null) {
                throw new RfiEscalationConflictException('This escalation has already been resolved.');
            }

            $escalation->update([
                'resolved_at' => now(),
                'resolved_by' => $resolvedBy,
                'resolution' => $resolution,
                'resolution_type' => $resolutionType,
            ]);

            $lockedRfi->update([
                'current_escalation_id' => null,
                'escalated_to' => null,
                'escalated_by' => null,
                'escalated_at' => null,
                'escalation_reason' => null,
            ]);
        });

        /** @var RfiEscalation $escalation */
        return $escalation;
    }
}
