<?php declare(strict_types=1);

namespace App\Services\Crm;

use App\Models\EventRecord;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OpportunityStageTransitionService
{
    /**
     * @throws \Illuminate\Auth\Access\AuthorizationException nếu $actor không có quyền update $opportunity
     * @throws ValidationException nếu $toStage không hợp lệ, opportunity đã terminal, hoặc thiếu lost_reason khi chuyển sang lost
     */
    public function transition(User $actor, Opportunity $opportunity, string $toStage, ?string $lostReason): Opportunity
    {
        Gate::forUser($actor)->authorize('update', $opportunity);

        if (!in_array($toStage, Opportunity::VALID_STAGES, true)) {
            throw ValidationException::withMessages(['pipeline_stage' => ['Giai đoạn không hợp lệ.']]);
        }

        if ($opportunity->isTerminal()) {
            throw ValidationException::withMessages([
                'pipeline_stage' => ['Won/lost/no-bid opportunities can no longer change stage.'],
            ]);
        }

        if ($toStage === Opportunity::STAGE_LOST && trim((string) $lostReason) === '') {
            throw ValidationException::withMessages(['lost_reason' => ['Vui lòng nhập lý do khi chuyển sang Thua.']]);
        }

        $from = (string) $opportunity->pipeline_stage;
        $opportunity->pipeline_stage = $toStage;
        $opportunity->lost_reason = $toStage === Opportunity::STAGE_LOST ? (string) $lostReason : null;

        if ($toStage === Opportunity::STAGE_WON) {
            $opportunity->forecast_category = 'closed_won';
        } elseif (in_array($toStage, [Opportunity::STAGE_LOST, Opportunity::STAGE_NO_BID], true)) {
            $opportunity->forecast_category = 'closed_lost';
        }

        $opportunity->save();

        EventRecord::query()->create([
            'tenant_id' => (string) $opportunity->tenant_id,
            'project_id' => $opportunity->converted_project_id,
            'aggregate_type' => 'opportunity',
            'aggregate_id' => (string) $opportunity->id,
            'event_key' => 'crm.opportunity.stage_changed',
            'actor_user_id' => (string) $actor->id,
            'payload' => ['from' => $from, 'to' => $toStage],
            'occurred_at' => now(),
        ]);

        return $opportunity->fresh() ?? $opportunity;
    }
}
