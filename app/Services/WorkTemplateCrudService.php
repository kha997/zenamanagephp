<?php declare(strict_types=1);

namespace App\Services;

use App\Events\WorkTemplateCreated;
use App\Events\WorkTemplateDeleted;
use App\Events\WorkTemplatePublished;
use App\Events\WorkTemplateUpdated;
use App\Models\WorkTemplate;
use App\Models\WorkTemplateChecklistItem;
use App\Models\WorkTemplateField;
use App\Models\WorkTemplatePhase;
use App\Models\WorkTemplateRequiredDocument;
use App\Models\WorkTemplateStep;
use App\Models\WorkTemplateTask;
use App\Models\WorkTemplateTaskAssignment;
use App\Models\WorkTemplateTrigger;
use App\Models\WorkTemplateVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkTemplateCrudService
{
    public function createTemplate(Request $request, string $tenantId, string $userId): WorkTemplate
    {
        $content = $this->buildContentPayload($request);
        $schemaVersion = $this->resolveSchemaVersion($request, $content);

        return DB::transaction(function () use ($request, $tenantId, $userId, $content, $schemaVersion): WorkTemplate {
            $template = WorkTemplate::create([
                'tenant_id' => $tenantId,
                'code' => $request->string('code')->toString(),
                'name' => $request->string('name')->toString(),
                'description' => $request->input('description'),
                'status' => $request->input('status', 'draft'),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $version = WorkTemplateVersion::create([
                'tenant_id' => $tenantId,
                'work_template_id' => $template->id,
                'semver' => $this->nextDraftSemver($template),
                'schema_version' => $schemaVersion,
                'content_json' => $content,
                'is_immutable' => false,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->syncVersionStructure($version, $content, $userId);

            $created = $this->freshTemplate($template);
            WorkTemplateCreated::dispatch($created, $created->versions->first());

            return $created;
        });
    }

    public function updateTemplate(Request $request, WorkTemplate $template, string $tenantId, string $userId): WorkTemplate
    {
        return DB::transaction(function () use ($request, $template, $tenantId, $userId): WorkTemplate {
            $template->fill($request->only(['name', 'description', 'status']));
            $template->updated_by = $userId;
            $template->save();

            $draft = WorkTemplateVersion::query()
                ->where('tenant_id', $tenantId)
                ->where('work_template_id', $template->id)
                ->whereNull('published_at')
                ->orderByDesc('created_at')
                ->first();

            if (!$draft) {
                $draft = $this->createDraftFromLatestPublished($template, $tenantId, $userId);
            }

            $content = $this->buildContentPayload($request, $draft->content_json ?? []);
            $schemaVersion = $this->resolveSchemaVersion($request, $content, $draft);

            $draft->fill([
                'schema_version' => $schemaVersion,
                'content_json' => $content,
                'updated_by' => $userId,
            ]);
            $draft->save();

            $this->syncVersionStructure($draft, $content, $userId);

            $updated = $this->freshTemplate($template);
            WorkTemplateUpdated::dispatch($updated, $draft->fresh());

            return $updated;
        });
    }

    public function publishTemplate(WorkTemplate $template, string $tenantId, string $userId): WorkTemplateVersion
    {
        return DB::transaction(function () use ($template, $tenantId, $userId): WorkTemplateVersion {
            $draft = WorkTemplateVersion::query()
                ->where('tenant_id', $tenantId)
                ->where('work_template_id', $template->id)
                ->whereNull('published_at')
                ->orderByDesc('created_at')
                ->first();

            if (!$draft) {
                throw new \RuntimeException('No draft version available to publish');
            }

            $published = WorkTemplateVersion::create([
                'tenant_id' => $tenantId,
                'work_template_id' => $template->id,
                'semver' => $this->nextPublishedSemver($template),
                'schema_version' => (int) ($draft->schema_version ?? 1),
                'content_json' => $draft->content_json,
                'is_immutable' => true,
                'published_at' => now(),
                'published_by' => $userId,
                'source_version_id' => $draft->id,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->cloneVersionStructure($draft, $published, $userId);

            $template->status = 'published';
            $template->updated_by = $userId;
            $template->save();

            $freshPublished = $this->freshVersion($published);
            WorkTemplatePublished::dispatch($template->fresh(), $freshPublished);

            return $freshPublished;
        });
    }

    public function deleteTemplate(WorkTemplate $template, string $userId): void
    {
        $template->deleted_by = $userId;
        $template->status = 'archived';
        $template->save();
        $template->delete();

        WorkTemplateDeleted::dispatch($template);
    }

    public function createDraftFromLatestPublished(WorkTemplate $template, string $tenantId, string $userId): WorkTemplateVersion
    {
        $latestPublished = WorkTemplateVersion::query()
            ->where('tenant_id', $tenantId)
            ->where('work_template_id', $template->id)
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->first();

        $draft = WorkTemplateVersion::create([
            'tenant_id' => $tenantId,
            'work_template_id' => $template->id,
            'semver' => $this->nextDraftSemver($template),
            'schema_version' => (int) ($latestPublished?->schema_version ?? 1),
            'content_json' => $latestPublished?->content_json ?? ['steps' => [], 'approvals' => [], 'rules' => []],
            'is_immutable' => false,
            'source_version_id' => $latestPublished?->id,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        if ($latestPublished) {
            $this->cloneVersionStructure($latestPublished, $draft, $userId);
        }

        return $draft;
    }

    public function buildContentPayload(Request $request, array $base = []): array
    {
        $content = $request->input('content_json', $base);

        if (!is_array($content)) {
            $content = [];
        }

        foreach (['steps', 'approvals', 'rules', 'phases'] as $key) {
            if ($request->has($key)) {
                $content[$key] = $request->input($key, []);
            }
        }

        $content['steps'] = is_array($content['steps'] ?? null) ? $content['steps'] : [];
        $content['approvals'] = is_array($content['approvals'] ?? null) ? $content['approvals'] : [];
        $content['rules'] = is_array($content['rules'] ?? null) ? $content['rules'] : [];
        $content['phases'] = is_array($content['phases'] ?? null) ? $content['phases'] : [];

        return $content;
    }

    public function resolveSchemaVersion(Request $request, array $content, ?WorkTemplateVersion $existingVersion = null): int
    {
        if ($request->has('phases') || !empty($content['phases'])) {
            return 2;
        }

        if ($request->has('steps') || !empty($content['steps'])) {
            return 1;
        }

        return (int) ($existingVersion?->schema_version ?? 2);
    }

    public function freshTemplate(WorkTemplate $template): WorkTemplate
    {
        return $template->fresh([
            'versions' => fn ($query) => $query->orderByDesc('created_at'),
            'versions.steps.fields',
            'versions.phases.tasks.checklistItems',
            'versions.phases.tasks.requiredDocuments',
            'versions.phases.tasks.assignments',
            'versions.phases.tasks.triggers',
        ]);
    }

    public function freshVersion(WorkTemplateVersion $version): WorkTemplateVersion
    {
        return $version->fresh([
            'steps.fields',
            'phases.tasks.checklistItems',
            'phases.tasks.requiredDocuments',
            'phases.tasks.assignments',
            'phases.tasks.triggers',
        ]);
    }

    public function serializeTemplate(WorkTemplate $template, bool $includeAllVersions = true): array
    {
        $versions = $template->versions->sortByDesc('created_at')->values();
        $draftVersion = $versions->first(fn (WorkTemplateVersion $version): bool => $version->published_at === null);
        $publishedVersions = $versions->filter(fn (WorkTemplateVersion $version): bool => $version->published_at !== null)->values();

        $payload = [
            'id' => (string) $template->id,
            'tenant_id' => (string) $template->tenant_id,
            'code' => $template->code,
            'name' => $template->name,
            'description' => $template->description,
            'status' => $template->status,
            'created_by' => $template->created_by,
            'updated_by' => $template->updated_by,
            'created_at' => $template->created_at,
            'updated_at' => $template->updated_at,
            'draft_version' => $draftVersion ? $this->serializeVersion($draftVersion) : null,
            'published_versions' => $publishedVersions->map(fn (WorkTemplateVersion $version): array => $this->serializeVersion($version))->all(),
        ];

        if ($includeAllVersions) {
            $payload['versions'] = $versions->map(fn (WorkTemplateVersion $version): array => $this->serializeVersion($version))->all();
        }

        return $payload;
    }

    public function serializeVersion(WorkTemplateVersion $version): array
    {
        $payload = [
            'id' => (string) $version->id,
            'work_template_id' => (string) $version->work_template_id,
            'semver' => $version->semver,
            'schema_version' => (int) ($version->schema_version ?? 1),
            'content_json' => $version->content_json ?? [],
            'is_immutable' => (bool) $version->is_immutable,
            'published_at' => $version->published_at,
            'published_by' => $version->published_by,
            'source_version_id' => $version->source_version_id,
            'created_at' => $version->created_at,
            'updated_at' => $version->updated_at,
        ];

        if ((int) ($version->schema_version ?? 1) === 2) {
            $payload['phases'] = $version->phases->map(fn (WorkTemplatePhase $phase): array => [
                'id' => (string) $phase->id,
                'key' => $phase->phase_key,
                'name' => $phase->name,
                'description' => $phase->description,
                'order' => (int) $phase->phase_order,
                'default_offset_days' => $phase->default_offset_days,
                'config' => $phase->config_json,
                'tasks' => $phase->tasks->map(fn (WorkTemplateTask $task): array => [
                    'id' => (string) $task->id,
                    'key' => $task->task_key,
                    'name' => $task->name,
                    'description' => $task->description,
                    'task_type' => $task->task_type,
                    'order' => (int) $task->task_order,
                    'default_duration_days' => $task->default_duration_days,
                    'is_required' => (bool) $task->is_required,
                    'config' => $task->config_json,
                    'checklist_items' => $task->checklistItems->map(fn (WorkTemplateChecklistItem $item): array => [
                        'id' => (string) $item->id,
                        'key' => $item->checklist_key,
                        'label' => $item->label,
                        'help_text' => $item->help_text,
                        'order' => (int) $item->item_order,
                        'is_required' => (bool) $item->is_required,
                        'validation' => $item->validation_json,
                    ])->all(),
                    'required_documents' => $task->requiredDocuments->map(fn (WorkTemplateRequiredDocument $document): array => [
                        'id' => (string) $document->id,
                        'key' => $document->doc_key,
                        'document_type' => $document->document_type,
                        'name' => $document->name,
                        'description' => $document->description,
                        'order' => (int) $document->doc_order,
                        'is_required' => (bool) $document->is_required,
                        'checklist_item_id' => $document->work_template_checklist_item_id,
                        'rules' => $document->rules_json,
                    ])->all(),
                    'assignments' => $task->assignments->map(fn (WorkTemplateTaskAssignment $assignment): array => [
                        'id' => (string) $assignment->id,
                        'key' => $assignment->assignment_key,
                        'assignment_type' => $assignment->assignment_type,
                        'role_code' => $assignment->role_code,
                        'approval_order' => $assignment->approval_order,
                        'is_required' => (bool) $assignment->is_required,
                        'conditions' => $assignment->conditions_json,
                    ])->all(),
                    'triggers' => $task->triggers->map(fn (WorkTemplateTrigger $trigger): array => [
                        'id' => (string) $trigger->id,
                        'key' => $trigger->trigger_key,
                        'event' => $trigger->event,
                        'action' => $trigger->action,
                        'trigger_order' => (int) $trigger->trigger_order,
                        'is_active' => (bool) $trigger->is_active,
                        'conditions' => $trigger->conditions_json,
                        'payload' => $trigger->payload_json,
                    ])->all(),
                ])->all(),
            ])->all();

            return $payload;
        }

        $payload['steps'] = $version->steps->map(fn (WorkTemplateStep $step): array => [
            'id' => (string) $step->id,
            'key' => $step->step_key,
            'name' => $step->name,
            'type' => $step->type,
            'order' => (int) $step->step_order,
            'depends_on' => $step->depends_on ?? [],
            'assignee_rule' => $step->assignee_rule_json,
            'sla_hours' => $step->sla_hours,
            'config' => $step->config_json,
            'fields' => $step->fields->map(fn (WorkTemplateField $field): array => [
                'id' => (string) $field->id,
                'key' => $field->field_key,
                'label' => $field->label,
                'type' => $field->type,
                'required' => (bool) $field->is_required,
                'default' => $field->default_value,
                'validation' => $field->validation_json,
                'enum_options' => $field->enum_options_json,
                'visibility_rule' => $field->visibility_rule_json,
            ])->all(),
        ])->all();

        return $payload;
    }

    private function syncVersionStructure(WorkTemplateVersion $version, array $content, string $userId): void
    {
        if ((int) ($version->schema_version ?? 1) === 2) {
            $this->deleteLegacyStructure($version);
            $this->syncV2Hierarchy($version, $content['phases'] ?? [], $userId);

            return;
        }

        $this->deleteV2Hierarchy($version);
        $this->syncLegacyStepsAndFields($version, $content['steps'] ?? []);
    }

    private function cloneVersionStructure(WorkTemplateVersion $from, WorkTemplateVersion $to, string $userId): void
    {
        if ((int) ($from->schema_version ?? 1) === 2) {
            $this->deleteLegacyStructure($to);
            $this->cloneV2Hierarchy($from, $to, $userId);

            return;
        }

        $this->deleteV2Hierarchy($to);
        $this->cloneStepsAndFields($from, $to);
    }

    private function syncLegacyStepsAndFields(WorkTemplateVersion $version, array $steps): void
    {
        WorkTemplateField::query()
            ->where('tenant_id', $version->tenant_id)
            ->whereIn('work_template_step_id', WorkTemplateStep::query()
                ->where('tenant_id', $version->tenant_id)
                ->where('work_template_version_id', $version->id)
                ->pluck('id'))
            ->delete();

        WorkTemplateStep::query()
            ->where('tenant_id', $version->tenant_id)
            ->where('work_template_version_id', $version->id)
            ->delete();

        foreach ($steps as $stepData) {
            $step = WorkTemplateStep::create([
                'tenant_id' => $version->tenant_id,
                'work_template_version_id' => $version->id,
                'step_key' => (string) ($stepData['key'] ?? ''),
                'name' => $stepData['name'] ?? null,
                'type' => (string) ($stepData['type'] ?? 'task'),
                'step_order' => (int) ($stepData['order'] ?? 1),
                'depends_on' => is_array($stepData['depends_on'] ?? null) ? $stepData['depends_on'] : [],
                'assignee_rule_json' => is_array($stepData['assignee_rule'] ?? null) ? $stepData['assignee_rule'] : null,
                'sla_hours' => isset($stepData['sla_hours']) ? (int) $stepData['sla_hours'] : null,
                'config_json' => is_array($stepData['config'] ?? null) ? $stepData['config'] : null,
                'required_document_types' => is_array($stepData['required_document_types'] ?? null) ? $stepData['required_document_types'] : null,
            ]);

            foreach (($stepData['fields'] ?? []) as $fieldData) {
                WorkTemplateField::create([
                    'tenant_id' => $version->tenant_id,
                    'work_template_step_id' => $step->id,
                    'field_key' => (string) ($fieldData['key'] ?? ''),
                    'label' => (string) ($fieldData['label'] ?? ($fieldData['key'] ?? 'Field')),
                    'type' => (string) ($fieldData['type'] ?? 'string'),
                    'is_required' => (bool) ($fieldData['required'] ?? false),
                    'default_value' => isset($fieldData['default'])
                        ? (is_scalar($fieldData['default']) ? (string) $fieldData['default'] : json_encode($fieldData['default']))
                        : null,
                    'validation_json' => is_array($fieldData['validation'] ?? null) ? $fieldData['validation'] : null,
                    'enum_options_json' => is_array($fieldData['enum_options'] ?? null) ? $fieldData['enum_options'] : null,
                    'visibility_rule_json' => is_array($fieldData['visibility_rule'] ?? null) ? $fieldData['visibility_rule'] : null,
                ]);
            }
        }
    }

    private function cloneStepsAndFields(WorkTemplateVersion $from, WorkTemplateVersion $to): void
    {
        $sourceSteps = WorkTemplateStep::query()
            ->with('fields')
            ->where('tenant_id', $from->tenant_id)
            ->where('work_template_version_id', $from->id)
            ->orderBy('step_order')
            ->get();

        foreach ($sourceSteps as $sourceStep) {
            $step = WorkTemplateStep::create([
                'tenant_id' => $to->tenant_id,
                'work_template_version_id' => $to->id,
                'step_key' => $sourceStep->step_key,
                'name' => $sourceStep->name,
                'type' => $sourceStep->type,
                'step_order' => $sourceStep->step_order,
                'depends_on' => $sourceStep->depends_on,
                'assignee_rule_json' => $sourceStep->assignee_rule_json,
                'sla_hours' => $sourceStep->sla_hours,
                'config_json' => $sourceStep->config_json,
                'required_document_types' => $sourceStep->required_document_types,
            ]);

            foreach ($sourceStep->fields as $sourceField) {
                WorkTemplateField::create([
                    'tenant_id' => $to->tenant_id,
                    'work_template_step_id' => $step->id,
                    'field_key' => $sourceField->field_key,
                    'label' => $sourceField->label,
                    'type' => $sourceField->type,
                    'is_required' => $sourceField->is_required,
                    'default_value' => $sourceField->default_value,
                    'validation_json' => $sourceField->validation_json,
                    'enum_options_json' => $sourceField->enum_options_json,
                    'visibility_rule_json' => $sourceField->visibility_rule_json,
                ]);
            }
        }
    }

    private function syncV2Hierarchy(WorkTemplateVersion $version, array $phases, string $userId): void
    {
        $this->deleteV2Hierarchy($version);

        foreach ($phases as $phaseData) {
            $phase = WorkTemplatePhase::create([
                'tenant_id' => $version->tenant_id,
                'work_template_version_id' => $version->id,
                'phase_key' => (string) ($phaseData['key'] ?? ''),
                'name' => (string) ($phaseData['name'] ?? ''),
                'description' => $phaseData['description'] ?? null,
                'phase_order' => (int) ($phaseData['order'] ?? 1),
                'default_offset_days' => isset($phaseData['default_offset_days']) ? (int) $phaseData['default_offset_days'] : null,
                'config_json' => is_array($phaseData['config'] ?? null) ? $phaseData['config'] : null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach (($phaseData['tasks'] ?? []) as $taskData) {
                $task = WorkTemplateTask::create([
                    'tenant_id' => $version->tenant_id,
                    'work_template_phase_id' => $phase->id,
                    'task_key' => (string) ($taskData['key'] ?? ''),
                    'name' => (string) ($taskData['name'] ?? ''),
                    'description' => $taskData['description'] ?? null,
                    'task_type' => (string) ($taskData['task_type'] ?? 'standard'),
                    'task_order' => (int) ($taskData['order'] ?? 1),
                    'default_duration_days' => isset($taskData['default_duration_days']) ? (int) $taskData['default_duration_days'] : null,
                    'is_required' => (bool) ($taskData['is_required'] ?? true),
                    'config_json' => is_array($taskData['config'] ?? null) ? $taskData['config'] : null,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $checklistKeyMap = [];

                foreach (($taskData['checklist_items'] ?? []) as $itemData) {
                    $item = WorkTemplateChecklistItem::create([
                        'tenant_id' => $version->tenant_id,
                        'work_template_task_id' => $task->id,
                        'checklist_key' => (string) ($itemData['key'] ?? ''),
                        'label' => (string) ($itemData['label'] ?? ''),
                        'help_text' => $itemData['help_text'] ?? null,
                        'item_order' => (int) ($itemData['order'] ?? 1),
                        'is_required' => (bool) ($itemData['is_required'] ?? true),
                        'validation_json' => is_array($itemData['validation'] ?? null) ? $itemData['validation'] : null,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);

                    $checklistKeyMap[$item->checklist_key] = $item->id;
                }

                foreach (($taskData['required_documents'] ?? []) as $documentData) {
                    $checklistItemId = $documentData['checklist_item_id'] ?? null;
                    if (!$checklistItemId && isset($documentData['checklist_item_key']) && is_string($documentData['checklist_item_key'])) {
                        $checklistItemId = $checklistKeyMap[$documentData['checklist_item_key']] ?? null;
                    }

                    WorkTemplateRequiredDocument::create([
                        'tenant_id' => $version->tenant_id,
                        'work_template_task_id' => $task->id,
                        'work_template_checklist_item_id' => $checklistItemId,
                        'doc_key' => (string) ($documentData['key'] ?? ''),
                        'document_type' => (string) ($documentData['document_type'] ?? ''),
                        'name' => (string) ($documentData['name'] ?? ''),
                        'description' => $documentData['description'] ?? null,
                        'doc_order' => (int) ($documentData['order'] ?? 1),
                        'is_required' => (bool) ($documentData['is_required'] ?? true),
                        'rules_json' => is_array($documentData['rules'] ?? null) ? $documentData['rules'] : null,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                }

                foreach (($taskData['assignments'] ?? []) as $assignmentData) {
                    WorkTemplateTaskAssignment::create([
                        'tenant_id' => $version->tenant_id,
                        'work_template_task_id' => $task->id,
                        'assignment_key' => (string) ($assignmentData['key'] ?? ''),
                        'assignment_type' => (string) ($assignmentData['assignment_type'] ?? 'assignee'),
                        'role_code' => (string) ($assignmentData['role_code'] ?? ''),
                        'approval_order' => isset($assignmentData['approval_order']) ? (int) $assignmentData['approval_order'] : null,
                        'is_required' => (bool) ($assignmentData['is_required'] ?? true),
                        'conditions_json' => is_array($assignmentData['conditions'] ?? null) ? $assignmentData['conditions'] : null,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                }

                foreach (($taskData['triggers'] ?? []) as $triggerData) {
                    WorkTemplateTrigger::create([
                        'tenant_id' => $version->tenant_id,
                        'work_template_task_id' => $task->id,
                        'trigger_key' => (string) ($triggerData['key'] ?? ''),
                        'event' => (string) ($triggerData['event'] ?? ''),
                        'action' => (string) ($triggerData['action'] ?? ''),
                        'trigger_order' => (int) ($triggerData['trigger_order'] ?? 1),
                        'is_active' => (bool) ($triggerData['is_active'] ?? true),
                        'conditions_json' => is_array($triggerData['conditions'] ?? null) ? $triggerData['conditions'] : null,
                        'payload_json' => is_array($triggerData['payload'] ?? null) ? $triggerData['payload'] : null,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                }
            }
        }
    }

    private function cloneV2Hierarchy(WorkTemplateVersion $from, WorkTemplateVersion $to, string $userId): void
    {
        $sourcePhases = WorkTemplatePhase::query()
            ->with(['tasks.checklistItems', 'tasks.requiredDocuments', 'tasks.assignments', 'tasks.triggers'])
            ->where('tenant_id', $from->tenant_id)
            ->where('work_template_version_id', $from->id)
            ->orderBy('phase_order')
            ->get();

        foreach ($sourcePhases as $sourcePhase) {
            $phase = WorkTemplatePhase::create([
                'tenant_id' => $to->tenant_id,
                'work_template_version_id' => $to->id,
                'phase_key' => $sourcePhase->phase_key,
                'name' => $sourcePhase->name,
                'description' => $sourcePhase->description,
                'phase_order' => $sourcePhase->phase_order,
                'default_offset_days' => $sourcePhase->default_offset_days,
                'config_json' => $sourcePhase->config_json,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($sourcePhase->tasks as $sourceTask) {
                $task = WorkTemplateTask::create([
                    'tenant_id' => $to->tenant_id,
                    'work_template_phase_id' => $phase->id,
                    'task_key' => $sourceTask->task_key,
                    'name' => $sourceTask->name,
                    'description' => $sourceTask->description,
                    'task_type' => $sourceTask->task_type,
                    'task_order' => $sourceTask->task_order,
                    'default_duration_days' => $sourceTask->default_duration_days,
                    'is_required' => $sourceTask->is_required,
                    'config_json' => $sourceTask->config_json,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $checklistIdMap = [];

                foreach ($sourceTask->checklistItems as $sourceChecklistItem) {
                    $item = WorkTemplateChecklistItem::create([
                        'tenant_id' => $to->tenant_id,
                        'work_template_task_id' => $task->id,
                        'checklist_key' => $sourceChecklistItem->checklist_key,
                        'label' => $sourceChecklistItem->label,
                        'help_text' => $sourceChecklistItem->help_text,
                        'item_order' => $sourceChecklistItem->item_order,
                        'is_required' => $sourceChecklistItem->is_required,
                        'validation_json' => $sourceChecklistItem->validation_json,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);

                    $checklistIdMap[$sourceChecklistItem->id] = $item->id;
                }

                foreach ($sourceTask->requiredDocuments as $sourceDocument) {
                    WorkTemplateRequiredDocument::create([
                        'tenant_id' => $to->tenant_id,
                        'work_template_task_id' => $task->id,
                        'work_template_checklist_item_id' => $sourceDocument->work_template_checklist_item_id
                            ? ($checklistIdMap[$sourceDocument->work_template_checklist_item_id] ?? null)
                            : null,
                        'doc_key' => $sourceDocument->doc_key,
                        'document_type' => $sourceDocument->document_type,
                        'name' => $sourceDocument->name,
                        'description' => $sourceDocument->description,
                        'doc_order' => $sourceDocument->doc_order,
                        'is_required' => $sourceDocument->is_required,
                        'rules_json' => $sourceDocument->rules_json,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                }

                foreach ($sourceTask->assignments as $sourceAssignment) {
                    WorkTemplateTaskAssignment::create([
                        'tenant_id' => $to->tenant_id,
                        'work_template_task_id' => $task->id,
                        'assignment_key' => $sourceAssignment->assignment_key,
                        'assignment_type' => $sourceAssignment->assignment_type,
                        'role_code' => $sourceAssignment->role_code,
                        'approval_order' => $sourceAssignment->approval_order,
                        'is_required' => $sourceAssignment->is_required,
                        'conditions_json' => $sourceAssignment->conditions_json,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                }

                foreach ($sourceTask->triggers as $sourceTrigger) {
                    WorkTemplateTrigger::create([
                        'tenant_id' => $to->tenant_id,
                        'work_template_task_id' => $task->id,
                        'trigger_key' => $sourceTrigger->trigger_key,
                        'event' => $sourceTrigger->event,
                        'action' => $sourceTrigger->action,
                        'trigger_order' => $sourceTrigger->trigger_order,
                        'is_active' => $sourceTrigger->is_active,
                        'conditions_json' => $sourceTrigger->conditions_json,
                        'payload_json' => $sourceTrigger->payload_json,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                }
            }
        }
    }

    private function deleteLegacyStructure(WorkTemplateVersion $version): void
    {
        WorkTemplateField::query()
            ->where('tenant_id', $version->tenant_id)
            ->whereIn('work_template_step_id', WorkTemplateStep::query()
                ->where('tenant_id', $version->tenant_id)
                ->where('work_template_version_id', $version->id)
                ->pluck('id'))
            ->delete();

        WorkTemplateStep::query()
            ->where('tenant_id', $version->tenant_id)
            ->where('work_template_version_id', $version->id)
            ->delete();
    }

    private function deleteV2Hierarchy(WorkTemplateVersion $version): void
    {
        $phaseIds = WorkTemplatePhase::query()
            ->where('tenant_id', $version->tenant_id)
            ->where('work_template_version_id', $version->id)
            ->pluck('id');

        if ($phaseIds->isEmpty()) {
            return;
        }

        $taskIds = WorkTemplateTask::query()
            ->where('tenant_id', $version->tenant_id)
            ->whereIn('work_template_phase_id', $phaseIds)
            ->pluck('id');

        WorkTemplateRequiredDocument::query()
            ->where('tenant_id', $version->tenant_id)
            ->whereIn('work_template_task_id', $taskIds)
            ->delete();

        WorkTemplateChecklistItem::query()
            ->where('tenant_id', $version->tenant_id)
            ->whereIn('work_template_task_id', $taskIds)
            ->delete();

        WorkTemplateTaskAssignment::query()
            ->where('tenant_id', $version->tenant_id)
            ->whereIn('work_template_task_id', $taskIds)
            ->delete();

        WorkTemplateTrigger::query()
            ->where('tenant_id', $version->tenant_id)
            ->whereIn('work_template_task_id', $taskIds)
            ->delete();

        WorkTemplateTask::query()
            ->where('tenant_id', $version->tenant_id)
            ->whereIn('work_template_phase_id', $phaseIds)
            ->delete();

        WorkTemplatePhase::query()
            ->where('tenant_id', $version->tenant_id)
            ->where('work_template_version_id', $version->id)
            ->delete();
    }

    private function nextPublishedSemver(WorkTemplate $template): string
    {
        $lastPublished = WorkTemplateVersion::query()
            ->where('tenant_id', $template->tenant_id)
            ->where('work_template_id', $template->id)
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->first();

        if (!$lastPublished) {
            return '1.0.0';
        }

        $parts = array_map('intval', explode('.', preg_replace('/[^0-9.]/', '', $lastPublished->semver)));
        $major = $parts[0] ?? 1;
        $minor = $parts[1] ?? 0;
        $patch = ($parts[2] ?? 0) + 1;

        return sprintf('%d.%d.%d', $major, $minor, $patch);
    }

    private function nextDraftSemver(WorkTemplate $template): string
    {
        return sprintf('draft-%s-%s', $template->id, now()->format('YmdHisv'));
    }
}
