<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Approval;
use App\Models\Project;
use App\Models\WorkInstance;
use App\Models\WorkInstanceFieldValue;
use App\Models\WorkInstanceStep;
use App\Models\WorkInstanceStepAttachment;
use App\Services\ZenaAuditLogger;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class WorkInstanceController extends BaseApiController
{
    public function __construct(private ZenaAuditLogger $auditLogger)
    {
    }

    public function listByProject(Request $request, string $project): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $projectRecord = $this->projectForTenant($project, $tenantId);

            $instances = WorkInstance::query()
                ->with(['templateVersion.template'])
                ->withCount('steps')
                ->where('tenant_id', $tenantId)
                ->where('project_id', $projectRecord->id)
                ->orderByDesc('created_at')
                ->paginate(min((int) $request->integer('per_page', 15), $this->maxLimit));

            $instances->getCollection()->transform(static function (WorkInstance $instance): array {
                return [
                    'id' => (string) $instance->id,
                    'project_id' => (string) $instance->project_id,
                    'work_template_version_id' => (string) $instance->work_template_version_id,
                    'status' => (string) $instance->status,
                    'steps_count' => (int) $instance->steps_count,
                    'template' => [
                        'id' => (string) ($instance->templateVersion?->template?->id ?? ''),
                        'name' => (string) ($instance->templateVersion?->template?->name ?? ''),
                        'semver' => (string) ($instance->templateVersion?->semver ?? ''),
                    ],
                    'created_at' => optional($instance->created_at)?->toIso8601String(),
                    'updated_at' => optional($instance->updated_at)?->toIso8601String(),
                ];
            });

            return $this->listSuccessResponse($instances, 'Project work instances retrieved successfully');
        } catch (ModelNotFoundException) {
            return $this->notFound('Project not found');
        }
    }

    public function updateStep(Request $request, string $id, string $stepId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:pending,in_progress,completed,blocked,approved,rejected',
            'assignee_id' => 'nullable|exists:users,id',
            'deadline_at' => 'nullable|date',
            'field_values' => 'nullable|array',
            'attachments' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $tenantId = $this->tenantId();
            $instance = $this->instanceForTenant($id, $tenantId);
            $step = $this->stepForInstance($instance, $stepId, $tenantId);

            DB::transaction(function () use ($request, $step, $tenantId): void {
                $updates = [];

                if ($request->has('status')) {
                    $updates['status'] = $request->input('status');

                    if ($updates['status'] === 'in_progress' && !$step->started_at) {
                        $updates['started_at'] = now();
                    }

                    if (in_array($updates['status'], ['completed', 'approved', 'rejected'], true)) {
                        $updates['completed_at'] = now();
                    }
                }

                if ($request->filled('assignee_id')) {
                    $updates['assignee_id'] = $request->input('assignee_id');
                }

                if ($request->has('deadline_at')) {
                    $updates['deadline_at'] = $request->input('deadline_at');
                }

                if ($updates !== []) {
                    $step->update($updates);
                }

                $this->upsertFieldValues($step, $request->input('field_values', []), $tenantId);
            });

            $step = $step->fresh(['values']);

            $this->auditLogger->log(
                $request,
                'zena.work-instance.step.update',
                'work_instance_step',
                (string) $step->id,
                200,
                $instance->project_id,
                $tenantId,
                (string) Auth::id()
            );

            return $this->successResponse([
                'step' => $step,
                'attachments_hook' => $request->input('attachments', []),
            ], 'Work instance step updated successfully');
        } catch (ModelNotFoundException) {
            return $this->notFound('Work instance or step not found');
        }
    }

    public function approveStep(Request $request, string $id, string $stepId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'decision' => 'required|in:approved,rejected',
            'comment' => 'nullable|string',
            'requested_by' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $tenantId = $this->tenantId();
            $userId = (string) Auth::id();
            $instance = $this->instanceForTenant($id, $tenantId);
            $step = $this->stepForInstance($instance, $stepId, $tenantId);

            $approval = DB::transaction(function () use ($request, $step, $tenantId, $userId): Approval {
                $approval = Approval::create([
                    'tenant_id' => $tenantId,
                    'work_instance_step_id' => $step->id,
                    'decision' => $request->input('decision'),
                    'comment' => $request->input('comment'),
                    'requested_by' => $request->input('requested_by', $step->assignee_id),
                    'approved_by' => $userId,
                    'approved_at' => now(),
                ]);

                $step->update([
                    'status' => $request->input('decision'),
                    'completed_at' => now(),
                ]);

                return $approval;
            });

            $this->auditLogger->log(
                $request,
                'zena.work-instance.approve',
                'approval',
                (string) $approval->id,
                201,
                $instance->project_id,
                $tenantId,
                $userId
            );

            return $this->successResponse($approval, 'Work instance step approval recorded successfully', 201);
        } catch (ModelNotFoundException) {
            return $this->notFound('Work instance or step not found');
        }
    }

    public function listStepAttachments(Request $request, string $id, string $stepId): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $instance = $this->instanceForTenant($id, $tenantId);
            $step = $this->stepForInstance($instance, $stepId, $tenantId);
            $attachments = $step->attachments()->get();

            $this->auditLogger->log(
                $request,
                'zena.work-instance.step.attachment.list',
                'work_instance_step',
                (string) $step->id,
                200,
                $instance->project_id,
                $tenantId,
                (string) Auth::id()
            );

            return $this->successResponse([
                'attachments' => $attachments->map(fn (WorkInstanceStepAttachment $attachment): array => $this->transformAttachment($attachment))->values(),
            ], 'Work instance step attachments retrieved successfully');
        } catch (ModelNotFoundException) {
            return $this->notFound('Work instance or step not found');
        }
    }

    public function uploadStepAttachment(Request $request, string $id, string $stepId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,jpg,jpeg,png,gif,zip,rar,7z,csv',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $tenantId = $this->tenantId();
            $userId = (string) Auth::id();
            $instance = $this->instanceForTenant($id, $tenantId);
            $step = $this->stepForInstance($instance, $stepId, $tenantId);
            /** @var UploadedFile $file */
            $file = $request->file('file');

            $directory = sprintf('work-instances/%s/steps/%s/attachments', $instance->id, $step->id);
            $storedFilename = (string) \Illuminate\Support\Str::ulid() . '.' . $file->getClientOriginalExtension();
            $storedPath = Storage::disk('local')->putFileAs($directory, $file, $storedFilename);

            if ($storedPath === false) {
                return $this->serverError('Failed to store attachment');
            }

            $attachment = WorkInstanceStepAttachment::create([
                'tenant_id' => $tenantId,
                'work_instance_step_id' => (string) $step->id,
                'file_name' => (string) $file->getClientOriginalName(),
                'file_path' => $storedPath,
                'mime_type' => $file->getMimeType(),
                'file_size' => (int) $file->getSize(),
                'uploaded_by' => $userId,
            ]);

            $this->auditLogger->log(
                $request,
                'zena.work-instance.step.attachment.upload',
                'work_instance_step_attachment',
                (string) $attachment->id,
                201,
                $instance->project_id,
                $tenantId,
                $userId
            );

            return $this->successResponse([
                'attachment' => $this->transformAttachment($attachment),
            ], 'Work instance step attachment uploaded successfully', 201);
        } catch (ModelNotFoundException) {
            return $this->notFound('Work instance or step not found');
        }
    }

    public function deleteStepAttachment(Request $request, string $id, string $stepId, string $attachmentId): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $userId = (string) Auth::id();
            $instance = $this->instanceForTenant($id, $tenantId);
            $step = $this->stepForInstance($instance, $stepId, $tenantId);
            $attachment = $this->attachmentForStep($step, $attachmentId, $tenantId);

            if ($attachment->file_path !== '' && Storage::disk('local')->exists($attachment->file_path)) {
                Storage::disk('local')->delete($attachment->file_path);
            }

            $attachment->delete();

            $this->auditLogger->log(
                $request,
                'zena.work-instance.step.attachment.delete',
                'work_instance_step_attachment',
                (string) $attachmentId,
                200,
                $instance->project_id,
                $tenantId,
                $userId
            );

            return $this->successResponse([], 'Work instance step attachment deleted successfully');
        } catch (ModelNotFoundException) {
            return $this->notFound('Work instance, step, or attachment not found');
        }
    }

    public function exportDeliverable(Request $request, string $id): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $instance = WorkInstance::query()
                ->with(['steps.values'])
                ->where('tenant_id', $tenantId)
                ->whereKey($id)
                ->firstOrFail();

            $payload = [
                'format' => 'json-stub',
                'generated_at' => now()->toIso8601String(),
                'work_instance_id' => (string) $instance->id,
                'work_template_version_id' => (string) $instance->work_template_version_id,
                'project_id' => (string) $instance->project_id,
                'steps' => $instance->steps->map(static fn (WorkInstanceStep $step): array => [
                    'id' => (string) $step->id,
                    'step_key' => $step->step_key,
                    'status' => $step->status,
                    'values' => $step->values->map(static fn (WorkInstanceFieldValue $value): array => [
                        'field_key' => $value->field_key,
                        'value_string' => $value->value_string,
                        'value_number' => $value->value_number,
                        'value_date' => $value->value_date?->toDateString(),
                        'value_datetime' => $value->value_datetime?->toIso8601String(),
                        'value_json' => $value->value_json,
                    ])->values(),
                ])->values(),
            ];

            $this->auditLogger->log(
                $request,
                'zena.work-instance.export',
                'work_instance',
                (string) $instance->id,
                200,
                $instance->project_id,
                $tenantId,
                (string) Auth::id()
            );

            return $this->successResponse($payload, 'Deliverable export generated successfully');
        } catch (ModelNotFoundException) {
            return $this->notFound('Work instance not found');
        }
    }

    private function tenantId(): string
    {
        $tenantId = request()->attributes->get('tenant_id');

        if (!$tenantId && app()->bound('current_tenant_id')) {
            $tenantId = app('current_tenant_id');
        }

        if (!$tenantId) {
            throw new \RuntimeException('Tenant context missing');
        }

        return (string) $tenantId;
    }

    private function instanceForTenant(string $id, string $tenantId): WorkInstance
    {
        return WorkInstance::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($id)
            ->firstOrFail();
    }

    private function stepForInstance(WorkInstance $instance, string $stepId, string $tenantId): WorkInstanceStep
    {
        return WorkInstanceStep::query()
            ->where('tenant_id', $tenantId)
            ->where('work_instance_id', $instance->id)
            ->whereKey($stepId)
            ->firstOrFail();
    }

    private function projectForTenant(string $projectId, string $tenantId): Project
    {
        return Project::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($projectId)
            ->firstOrFail();
    }

    private function attachmentForStep(WorkInstanceStep $step, string $attachmentId, string $tenantId): WorkInstanceStepAttachment
    {
        return WorkInstanceStepAttachment::query()
            ->where('tenant_id', $tenantId)
            ->where('work_instance_step_id', $step->id)
            ->whereKey($attachmentId)
            ->firstOrFail();
    }

    private function upsertFieldValues(WorkInstanceStep $step, array $fieldValues, string $tenantId): void
    {
        if ($fieldValues === []) {
            return;
        }

        $fieldsByKey = collect($step->snapshot_fields_json ?? [])->keyBy('field_key');

        foreach ($fieldValues as $fieldKey => $value) {
            $fieldType = (string) ($fieldsByKey->get((string) $fieldKey)['type'] ?? 'string');

            $record = WorkInstanceFieldValue::query()->firstOrNew([
                'tenant_id' => $tenantId,
                'work_instance_step_id' => $step->id,
                'field_key' => (string) $fieldKey,
            ]);

            $record->fill([
                'value_string' => null,
                'value_number' => null,
                'value_date' => null,
                'value_datetime' => null,
                'value_json' => null,
            ]);

            switch ($fieldType) {
                case 'number':
                    $record->value_number = is_numeric($value) ? (float) $value : null;
                    break;
                case 'date':
                    $record->value_date = is_scalar($value) ? (string) $value : null;
                    break;
                case 'datetime':
                    $record->value_datetime = is_scalar($value) ? (string) $value : null;
                    break;
                case 'json':
                case 'object':
                case 'array':
                    $record->value_json = is_array($value) ? $value : ['value' => $value];
                    break;
                default:
                    $record->value_string = is_scalar($value) ? (string) $value : json_encode($value);
                    break;
            }

            $record->save();
        }
    }

    private function transformAttachment(WorkInstanceStepAttachment $attachment): array
    {
        return [
            'id' => (string) $attachment->id,
            'file_name' => (string) $attachment->file_name,
            'mime_type' => (string) ($attachment->mime_type ?? ''),
            'file_size' => (int) ($attachment->file_size ?? 0),
            'uploaded_by' => (string) ($attachment->uploaded_by ?? ''),
            'created_at' => optional($attachment->created_at)?->toIso8601String(),
        ];
    }
}
