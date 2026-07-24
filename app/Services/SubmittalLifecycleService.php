<?php declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SubmittalTransitionConflictException;
use App\Exceptions\SubmittalTransitionNotAllowedException;
use App\Models\EventRecord;
use App\Models\Notification;
use App\Models\Submittal;
use App\Models\SubmittalRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubmittalLifecycleService
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     */
    public function updateContent(Submittal $submittal, array $data, array $context): Submittal
    {
        DB::transaction(function () use ($submittal, $data, $context) {
            $locked = Submittal::query()
                ->where('id', $submittal->id)
                ->where('tenant_id', $submittal->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array($locked->status, [Submittal::STATUS_DRAFT, Submittal::STATUS_REVISING], true)) {
                throw new SubmittalTransitionNotAllowedException(
                    'Chỉ có thể sửa nội dung khi ở trạng thái draft hoặc revising.'
                );
            }

            $allowedFields = array_flip([
                'title',
                'description',
                'submittal_type',
                'specification_section',
                'due_date',
                'contractor',
                'manufacturer',
                'file_url',
                'attachments',
            ]);
            $safeData = array_intersect_key($data, $allowedFields);

            $locked->update($safeData);

            EventRecord::query()->create([
                'tenant_id' => (string) $locked->tenant_id,
                'project_id' => $locked->project_id,
                'aggregate_type' => 'submittal',
                'aggregate_id' => (string) $locked->id,
                'event_key' => 'submittal.content_updated',
                'actor_user_id' => $context['actor_user_id'] ?? null,
                'payload' => ['fields' => array_keys($safeData)],
                'occurred_at' => now(),
            ]);
        });

        return $submittal->fresh();
    }

    /** @param array<string, mixed> $context */
    public function submit(Submittal $submittal, array $context): Submittal
    {
        $isResubmit = false;

        DB::transaction(function () use ($submittal, $context, &$isResubmit) {
            $locked = Submittal::query()
                ->where('id', $submittal->id)
                ->where('tenant_id', $submittal->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!Submittal::canTransition($locked->status, Submittal::STATUS_SUBMITTED)) {
                throw new SubmittalTransitionNotAllowedException(
                    'Chỉ draft hoặc revising mới submit được.'
                );
            }

            $nextRevisionNo = (int) (SubmittalRevision::query()
                ->where('submittal_id', $locked->id)
                ->max('revision_no')) + 1;

            SubmittalRevision::query()->create([
                'tenant_id' => (string) $locked->tenant_id,
                'submittal_id' => (string) $locked->id,
                'revision_no' => $nextRevisionNo,
                'revision_summary' => $context['revision_summary'] ?? null,
                'title' => $locked->title,
                'description' => $locked->description,
                'file_url' => $locked->file_url,
                'attachment_manifest' => $locked->attachments,
                'submitted_by' => $context['actor_user_id'] ?? null,
                'submitted_at' => now(),
                'created_at' => now(),
            ]);

            $locked->update([
                'status' => Submittal::STATUS_SUBMITTED,
                'current_revision_no' => $nextRevisionNo,
                'submitted_by' => $context['actor_user_id'] ?? $locked->submitted_by,
                'submitted_at' => now(),
            ]);

            EventRecord::query()->create([
                'tenant_id' => (string) $locked->tenant_id,
                'project_id' => $locked->project_id,
                'aggregate_type' => 'submittal',
                'aggregate_id' => (string) $locked->id,
                'event_key' => $nextRevisionNo > 1 ? 'submittal.resubmitted' : 'submittal.submitted',
                'actor_user_id' => $context['actor_user_id'] ?? null,
                'payload' => ['revision_no' => $nextRevisionNo],
                'occurred_at' => now(),
            ]);

            $isResubmit = $nextRevisionNo > 1;
        });

        $fresh = $submittal->fresh();

        if ($isResubmit) {
            $this->notifyLastRejector($fresh);
        }

        return $fresh;
    }

    /** @param array<string, mixed> $context */
    public function approve(Submittal $submittal, array $context): Submittal
    {
        return $this->decide(
            $submittal,
            $context,
            Submittal::STATUS_APPROVED,
            'approved',
            $context['approval_comments'] ?? null
        );
    }

    /** @param array<string, mixed> $context */
    public function reject(Submittal $submittal, array $context): Submittal
    {
        return $this->decide(
            $submittal,
            $context,
            Submittal::STATUS_REJECTED,
            'rejected',
            $context['rejection_reason'] ?? null,
            $context['rejection_comments'] ?? null
        );
    }

    /** @param array<string, mixed> $context */
    private function decide(
        Submittal $submittal,
        array $context,
        string $targetStatus,
        string $decision,
        ?string $comments,
        ?string $decisionComments = null
    ): Submittal {
        DB::transaction(function () use ($submittal, $context, $targetStatus, $decision, $comments, $decisionComments) {
            $locked = Submittal::query()
                ->where('id', $submittal->id)
                ->where('tenant_id', $submittal->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!Submittal::canTransition($locked->status, $targetStatus)) {
                throw new SubmittalTransitionNotAllowedException(
                    "Chỉ submitted mới có thể chuyển sang {$targetStatus}."
                );
            }

            $revision = SubmittalRevision::query()
                ->where('submittal_id', $locked->id)
                ->where('revision_no', $locked->current_revision_no)
                ->lockForUpdate()
                ->first();

            if (!$revision) {
                throw new SubmittalTransitionConflictException('Không tìm thấy revision đang chờ quyết định.');
            }

            $affected = SubmittalRevision::query()
                ->where('id', $revision->id)
                ->whereNull('decision')
                ->update([
                    'decision' => $decision,
                    'decided_by' => $context['actor_user_id'] ?? null,
                    'decided_at' => now(),
                    'decision_comments' => $decisionComments ?? $comments,
                ]);

            if ($affected === 0) {
                throw new SubmittalTransitionConflictException('Revision đã có quyết định trước đó.');
            }

            $mirror = ['status' => $targetStatus];

            if ($decision === 'approved') {
                $mirror['approved_by'] = $context['actor_user_id'] ?? null;
                $mirror['approved_at'] = now();
                $mirror['approval_comments'] = $comments;
            } else {
                $mirror['rejected_by'] = $context['actor_user_id'] ?? null;
                $mirror['rejected_at'] = now();
                $mirror['rejection_reason'] = $comments;
                $mirror['rejection_comments'] = $decisionComments;
            }

            $locked->update($mirror);

            EventRecord::query()->create([
                'tenant_id' => (string) $locked->tenant_id,
                'project_id' => $locked->project_id,
                'aggregate_type' => 'submittal',
                'aggregate_id' => (string) $locked->id,
                'event_key' => "submittal.{$decision}",
                'actor_user_id' => $context['actor_user_id'] ?? null,
                'payload' => ['revision_no' => $locked->current_revision_no],
                'occurred_at' => now(),
            ]);
        });

        return $submittal->fresh();
    }

    /** @param array<string, mixed> $context */
    public function startRevision(Submittal $submittal, array $context): Submittal
    {
        DB::transaction(function () use ($submittal, $context) {
            $locked = Submittal::query()
                ->where('id', $submittal->id)
                ->where('tenant_id', $submittal->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!Submittal::canTransition($locked->status, Submittal::STATUS_REVISING)) {
                throw new SubmittalTransitionNotAllowedException(
                    'Chỉ rejected mới mở lại để sửa được.'
                );
            }

            $lastRevision = SubmittalRevision::query()
                ->where('submittal_id', $locked->id)
                ->orderByDesc('revision_no')
                ->first();

            $locked->update([
                'status' => Submittal::STATUS_REVISING,
                'title' => $lastRevision->title ?? $locked->title,
                'description' => $lastRevision->description ?? $locked->description,
                'file_url' => $lastRevision->file_url ?? $locked->file_url,
                'attachments' => $lastRevision->attachment_manifest ?? $locked->attachments,
            ]);

            EventRecord::query()->create([
                'tenant_id' => (string) $locked->tenant_id,
                'project_id' => $locked->project_id,
                'aggregate_type' => 'submittal',
                'aggregate_id' => (string) $locked->id,
                'event_key' => 'submittal.revision_started',
                'actor_user_id' => $context['actor_user_id'] ?? null,
                'payload' => [],
                'occurred_at' => now(),
            ]);
        });

        return $submittal->fresh();
    }

    private function notifyLastRejector(Submittal $submittal): void
    {
        try {
            $recipient = SubmittalRevision::query()
                ->where('submittal_id', $submittal->id)
                ->where('decision', 'rejected')
                ->orderByDesc('revision_no')
                ->value('decided_by');

            if (!$recipient) {
                return;
            }

            Notification::query()->create([
                'tenant_id' => (string) $submittal->tenant_id,
                'user_id' => $recipient,
                'type' => 'submittal_resubmitted',
                'title' => 'Submittal đã được nộp lại: ' . $submittal->submittal_number,
                'body' => $submittal->title,
                'project_id' => $submittal->project_id,
            ]);
        } catch (\Throwable $e) {
            Log::error('submittal.notification_failed', [
                'submittal_id' => $submittal->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
