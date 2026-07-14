<?php declare(strict_types=1);

namespace App\Services;

use App\Models\DesignItem;
use App\Models\DesignItemRevision;
use App\Models\Document;
use App\Models\EventRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Nguồn chân lý duy nhất cho chuyển trạng thái review_status của DesignItem.
 *
 * Controller API và Portal đều delegate qua service này — không chuyển trạng thái
 * ở bất kỳ nơi nào khác.
 */
final class DesignItemStatusService
{
    /**
     * Chuyển trạng thái review_status theo transition graph.
     *
     * @param array{client_feedback_notes?: string|null, approval_evidence?: string|null,
     *        actor_user_id?: string|null, actor_account_id?: string|null} $options
     *
     * @throws ValidationException
     */
    public function transition(DesignItem $item, string $to, array $options = []): DesignItem
    {
        $from = (string) $item->review_status;

        if (!DesignItem::canTransition($from, $to)) {
            throw ValidationException::withMessages([
                'review_status' => ["Cannot transition from {$from} to {$to}."],
            ]);
        }

        if ($to === DesignItem::STATUS_REVISION_REQUESTED && empty($options['client_feedback_notes'])) {
            throw ValidationException::withMessages([
                'client_feedback_notes' => ['Required when requesting a revision.'],
            ]);
        }

        if ($to === DesignItem::STATUS_SENT_TO_CLIENT) {
            if (!$item->due_to_client_at) {
                throw ValidationException::withMessages([
                    'due_to_client_at' => ['Must be set before sending to client.'],
                ]);
            }

            $hasAttachment = Document::query()
                ->forEntity(Document::ENTITY_TYPE_DESIGN_ITEM, (string) $item->id)
                ->exists();

            if (!$hasAttachment) {
                throw ValidationException::withMessages([
                    'review_status' => ['At least one attached document is required before sending to client.'],
                ]);
            }
        }

        if ($to === DesignItem::STATUS_APPROVED && empty($options['approval_evidence'])) {
            throw ValidationException::withMessages([
                'approval_evidence' => ['Required when approving — record how the client confirmed (phone/email/zalo/client_portal).'],
            ]);
        }

        $item->review_status = $to;

        if ($to === DesignItem::STATUS_REVISION_REQUESTED) {
            $item->client_feedback_notes = (string) ($options['client_feedback_notes'] ?? '');
        }

        if ($to === DesignItem::STATUS_APPROVED) {
            $item->approval_evidence = (string) ($options['approval_evidence'] ?? '');
        }

        $tenantId = (string) $item->tenant_id;
        $actorUserId = $options['actor_user_id'] ?? null;

        DB::transaction(function () use ($item, $tenantId, $from, $to, $actorUserId): void {
            $item->save();

            if ($to === DesignItem::STATUS_REVISION_REQUESTED) {
                $revisionNo = ((int) $item->revision_count) + 1;

                DesignItemRevision::query()->create([
                    'tenant_id' => $tenantId,
                    'design_item_id' => (string) $item->id,
                    'revision_no' => $revisionNo,
                    'client_feedback' => (string) $item->client_feedback_notes,
                    'requested_by' => $actorUserId,
                    'requested_at' => now(),
                ]);

                $item->forceFill(['revision_count' => $revisionNo])->save();
            }

            if ($from === DesignItem::STATUS_REVISION_REQUESTED) {
                $item->revisions()
                    ->whereNull('resolved_at')
                    ->latest('revision_no')
                    ->first()?->update(['resolved_at' => now()]);
            }
        });

        EventRecord::query()->create([
            'tenant_id' => $tenantId,
            'project_id' => (string) $item->project_id,
            'aggregate_type' => 'design_item',
            'aggregate_id' => (string) $item->id,
            'event_key' => 'design_item.status_changed',
            'actor_user_id' => $options['actor_user_id'] ?? null,
            'payload' => [
                'from' => $from,
                'to' => $to,
                'actor_account_id' => $options['actor_account_id'] ?? null,
            ],
            'occurred_at' => now(),
        ]);

        return $item->fresh() ?? $item;
    }
}
