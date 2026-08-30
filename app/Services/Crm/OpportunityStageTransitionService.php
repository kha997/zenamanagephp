<?php

declare(strict_types=1);

namespace App\Services\Crm;

use App\Models\EventRecord;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OpportunityStageTransitionService
{
    /**
     * GAP-048 §11 — stages that require >=1 CONFIRMED canonical
     * Service-Line before entry. lost/no_bid/nurture are deliberately
     * absent (always-allowed exits, §17).
     *
     * @var list<string>
     */
    private const CLASSIFICATION_GATED_STAGES = [
        Opportunity::STAGE_SCOPE_DEFINED,
        Opportunity::STAGE_PROPOSAL_DRAFT,
        Opportunity::STAGE_PROPOSAL_SENT,
        Opportunity::STAGE_NEGOTIATION,
        Opportunity::STAGE_CONTRACTING,
        Opportunity::STAGE_WON,
    ];

    /**
     * @throws AuthorizationException nếu $actor không có quyền update $opportunity
     * @throws ValidationException nếu $toStage không hợp lệ, opportunity đã terminal, thiếu lost_reason khi chuyển sang lost, hoặc chưa có Service Line CONFIRMED khi chuyển vào giai đoạn bị chặn
     */
    public function transition(User $actor, Opportunity $opportunity, string $toStage, ?string $lostReason): Opportunity
    {
        Gate::forUser($actor)->authorize('update', $opportunity);

        if (! in_array($toStage, Opportunity::VALID_STAGES, true)) {
            throw ValidationException::withMessages(['pipeline_stage' => ['Giai đoạn không hợp lệ.']]);
        }

        return DB::transaction(function () use ($actor, $opportunity, $toStage, $lostReason): Opportunity {
            // Canonical lock order: Opportunity row first (GAP-048 §19).
            // Re-read state AFTER the lock — never validate against a
            // model instance loaded before lock acquisition.
            $locked = Opportunity::query()
                ->whereKey($opportunity->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isTerminal()) {
                throw ValidationException::withMessages([
                    'pipeline_stage' => ['Won/lost/no-bid opportunities can no longer change stage.'],
                ]);
            }

            if ($toStage === Opportunity::STAGE_LOST && trim((string) $lostReason) === '') {
                throw ValidationException::withMessages(['lost_reason' => ['Vui lòng nhập lý do khi chuyển sang Thua.']]);
            }

            if (in_array($toStage, self::CLASSIFICATION_GATED_STAGES, true) && ! $locked->hasConfirmedServiceLine()) {
                throw ValidationException::withMessages([
                    'service_line' => ['At least one confirmed Service Line is required before entering this stage.'],
                ]);
            }

            $from = (string) $locked->pipeline_stage;
            $locked->pipeline_stage = $toStage;
            $locked->lost_reason = $toStage === Opportunity::STAGE_LOST ? (string) $lostReason : null;

            if ($toStage === Opportunity::STAGE_WON) {
                $locked->forecast_category = 'closed_won';
            } elseif (in_array($toStage, [Opportunity::STAGE_LOST, Opportunity::STAGE_NO_BID], true)) {
                $locked->forecast_category = 'closed_lost';
            }

            $locked->save();

            EventRecord::query()->create([
                'tenant_id' => (string) $locked->tenant_id,
                'project_id' => $locked->converted_project_id,
                'aggregate_type' => 'opportunity',
                'aggregate_id' => (string) $locked->id,
                'event_key' => 'crm.opportunity.stage_changed',
                'actor_user_id' => (string) $actor->id,
                'payload' => ['from' => $from, 'to' => $toStage],
                'occurred_at' => now(),
            ]);

            return $locked->fresh() ?? $locked;
        });
    }
}
