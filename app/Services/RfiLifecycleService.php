<?php declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RfiLifecycleTransitionException;
use App\Models\Rfi;
use App\Models\RfiEscalation;
use Illuminate\Support\Facades\DB;

/**
 * Sole owner of RFI lifecycle transitions (respond/close/cancel). Consults
 * RfiEscalationService only for "is there an active escalation" and to
 * atomically resolve one during cancel() — never touches rfi_escalations
 * directly otherwise, and never sets escalation fields.
 */
class RfiLifecycleService
{
    private const TERMINAL_STATUSES = ['closed', 'cancelled'];

    public function __construct(private readonly RfiEscalationService $escalationService)
    {
    }

    public function isTerminal(Rfi $rfi): bool
    {
        return in_array($rfi->status, self::TERMINAL_STATUSES, true);
    }

    public function assertCanRespond(Rfi $rfi): void
    {
        if (!in_array($rfi->status, ['open', 'in_progress'], true)) {
            throw new RfiLifecycleTransitionException('RFI can only be responded to while open or in progress.');
        }
    }

    public function respond(Rfi $rfi, string $userId, string $response, string $status): Rfi
    {
        $this->assertCanRespond($rfi);

        if ($status === 'closed' && $this->escalationService->hasActiveEscalation($rfi->id)) {
            throw new RfiLifecycleTransitionException('Cannot close an RFI while it has an active escalation — resolve the escalation first.');
        }

        $rfi->update([
            'response' => $response,
            'status' => $status,
            'responded_by' => $userId,
            'responded_at' => now(),
        ]);

        return $rfi->fresh();
    }

    public function assertCanClose(Rfi $rfi): void
    {
        if ($rfi->status !== 'answered') {
            throw new RfiLifecycleTransitionException('RFI must be answered before it can be closed.');
        }

        if ($this->escalationService->hasActiveEscalation($rfi->id)) {
            throw new RfiLifecycleTransitionException('Cannot close an RFI while it has an active escalation — resolve the escalation first.');
        }
    }

    public function close(Rfi $rfi, string $userId): Rfi
    {
        $this->assertCanClose($rfi);

        $rfi->update([
            'status' => 'closed',
            'closed_by' => $userId,
            'closed_at' => now(),
        ]);

        return $rfi->fresh();
    }

    public function assertCanCancel(Rfi $rfi): void
    {
        if ($this->isTerminal($rfi)) {
            throw new RfiLifecycleTransitionException('RFI is already closed or cancelled.');
        }
    }

    public function cancel(Rfi $rfi, string $userId, string $reason): Rfi
    {
        $this->assertCanCancel($rfi);

        return DB::transaction(function () use ($rfi, $userId, $reason) {
            if ($this->escalationService->hasActiveEscalation($rfi->id)) {
                $this->escalationService->resolveEscalation(
                    $rfi,
                    $userId,
                    'RFI cancelled: ' . $reason,
                    RfiEscalation::RESOLUTION_TYPE_RFI_CANCELLED,
                );
            }

            $rfi->fresh()->update(['status' => 'cancelled']);

            return $rfi->fresh();
        });
    }
}
