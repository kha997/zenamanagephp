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
    public function updateContent(Submittal $submittal, array $data, array $context): Submittal
    {
        return DB::transaction(function () use ($submittal, $data, $context) {
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

            return $locked->fresh();
        });
    }

    public function submit(Submittal $submittal, array $context): Submittal
    {
        $isResubmit = false;

        $submittal = DB::transaction(function () use ($submittal, $context, &$isResubmit) {
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

            return $locked->fresh();
        });

        if ($isResubmit) {
            $this->notifyLastRejector($submittal);
        }

        return $submittal;
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
