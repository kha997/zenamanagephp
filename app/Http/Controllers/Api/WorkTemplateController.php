<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Component;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\UserRoleProject;
use App\Models\WorkInstance;
use App\Models\WorkInstanceStep;
use App\Models\WorkTemplate;
use App\Models\WorkTemplateField;
use App\Models\WorkTemplateStep;
use App\Models\WorkTemplateVersion;
use App\Services\WorkTemplatePackageService;
use App\Services\ZenaAuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class WorkTemplateController extends BaseApiController
{
    public function __construct(
        private ZenaAuditLogger $auditLogger,
        private WorkTemplatePackageService $templatePackageService
    )
    {
    }

    public function index(Request $request): JsonResponse
    {
        $templates = WorkTemplate::query()
            ->where('tenant_id', $this->tenantId())
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->integer('per_page', 15), $this->maxLimit));

        return $this->listSuccessResponse($templates, 'Work templates retrieved successfully');
    }

    public function show(string $id): JsonResponse
    {
        try {
            $template = $this->templateForTenant($id)->firstOrFail()
                ->load(['versions' => fn ($q) => $q->orderByDesc('created_at')]);

            return $this->successResponse($template, 'Work template retrieved successfully');
        } catch (ModelNotFoundException) {
            return $this->notFound('Work template not found');
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:100',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:draft,published,archived',
            'content_json' => 'nullable|array',
            'steps' => 'nullable|array',
            'steps.*.key' => 'required_with:steps|string|max:100',
            'steps.*.name' => 'nullable|string|max:255',
            'steps.*.type' => 'required_with:steps|string|max:100',
            'steps.*.order' => 'required_with:steps|integer|min:1',
            'steps.*.depends_on' => 'nullable|array',
            'steps.*.assignee_rule' => 'nullable|array',
            'steps.*.sla_hours' => 'nullable|integer|min:0',
            'steps.*.fields' => 'nullable|array',
            'steps.*.fields.*.key' => 'required_with:steps.*.fields|string|max:100',
            'steps.*.fields.*.label' => 'required_with:steps.*.fields|string|max:255',
            'steps.*.fields.*.type' => 'required_with:steps.*.fields|string|max:100',
            'steps.*.fields.*.required' => 'nullable|boolean',
            'steps.*.fields.*.default' => 'nullable',
            'steps.*.fields.*.validation' => 'nullable|array',
            'steps.*.fields.*.enum_options' => 'nullable|array',
            'steps.*.fields.*.visibility_rule' => 'nullable|array',
            'approvals' => 'nullable|array',
            'rules' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $tenantId = $this->tenantId();
        $userId = (string) Auth::id();

        $exists = WorkTemplate::query()
            ->where('tenant_id', $tenantId)
            ->where('code', $request->string('code')->toString())
            ->exists();

        if ($exists) {
            return $this->errorResponse('Template code already exists in this tenant', 422);
        }

        $content = $this->buildContentPayload($request);

        $template = DB::transaction(function () use ($request, $tenantId, $userId, $content): WorkTemplate {
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
                'content_json' => $content,
                'is_immutable' => false,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->syncStepsAndFields($version, $content['steps'] ?? []);

            return $template->fresh(['versions']);
        });

        $this->auditLogger->log(
            $request,
            'zena.work-template.create',
            'work_template',
            (string) $template->id,
            201,
            null,
            $tenantId,
            $userId
        );

        return $this->successResponse($template, 'Work template created successfully', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:draft,published,archived',
            'content_json' => 'nullable|array',
            'steps' => 'nullable|array',
            'steps.*.key' => 'required_with:steps|string|max:100',
            'steps.*.name' => 'nullable|string|max:255',
            'steps.*.type' => 'required_with:steps|string|max:100',
            'steps.*.order' => 'required_with:steps|integer|min:1',
            'steps.*.depends_on' => 'nullable|array',
            'steps.*.assignee_rule' => 'nullable|array',
            'steps.*.sla_hours' => 'nullable|integer|min:0',
            'steps.*.fields' => 'nullable|array',
            'approvals' => 'nullable|array',
            'rules' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $tenantId = $this->tenantId();
            $userId = (string) Auth::id();
            $template = $this->templateForTenant($id)->firstOrFail();

            $updatedTemplate = DB::transaction(function () use ($request, $template, $tenantId, $userId): WorkTemplate {
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
                $draft->fill([
                    'content_json' => $content,
                    'updated_by' => $userId,
                ]);
                $draft->save();

                $this->syncStepsAndFields($draft, $content['steps'] ?? []);

                return $template->fresh(['versions']);
            });

            $this->auditLogger->log(
                $request,
                'zena.work-template.update',
                'work_template',
                (string) $updatedTemplate->id,
                200,
                null,
                $tenantId,
                $userId
            );

            return $this->successResponse($updatedTemplate, 'Work template updated successfully');
        } catch (ModelNotFoundException) {
            return $this->notFound('Work template not found');
        }
    }

    public function publish(Request $request, string $id): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $userId = (string) Auth::id();

            $template = $this->templateForTenant($id)->firstOrFail();

            $publishedVersion = DB::transaction(function () use ($template, $tenantId, $userId): WorkTemplateVersion {
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
                    'content_json' => $draft->content_json,
                    'is_immutable' => true,
                    'published_at' => now(),
                    'published_by' => $userId,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $this->cloneStepsAndFields($draft, $published);

                $template->status = 'published';
                $template->updated_by = $userId;
                $template->save();

                return $published->load('steps.fields');
            });

            $this->auditLogger->log(
                $request,
                'zena.work-template.publish',
                'work_template',
                (string) $template->id,
                200,
                null,
                $tenantId,
                $userId,
                ['entity_id' => (string) $publishedVersion->id]
            );

            return $this->successResponse($publishedVersion, 'Work template published successfully');
        } catch (ModelNotFoundException) {
            return $this->notFound('Work template not found');
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        }
    }

    public function applyToProject(Request $request, string $projectId): JsonResponse
    {
        $tenantId = $this->tenantId();

        $project = Project::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($projectId)
            ->first();

        if (!$project) {
            return $this->notFound('Project not found');
        }

        return $this->applyTemplateToScope($request, $project, 'project', (string) $project->id, null);
    }

    public function applyToComponent(Request $request, string $componentId): JsonResponse
    {
        $tenantId = $this->tenantId();

        $component = Component::query()
            ->with('project')
            ->whereKey($componentId)
            ->first();

        if (!$component || !$component->project || (string) $component->project->tenant_id !== $tenantId) {
            return $this->notFound('Component not found');
        }

        return $this->applyTemplateToScope(
            $request,
            $component->project,
            'component',
            (string) $component->id,
            $component
        );
    }

    private function applyTemplateToScope(
        Request $request,
        Project $project,
        string $scopeType,
        string $scopeId,
        ?Component $component
    ): JsonResponse {
        $validator = Validator::make($request->all(), [
            'work_template_id' => 'nullable|string|required_without:work_template_version_id',
            'work_template_version_id' => 'nullable|string|required_without:work_template_id',
            'idempotency_key' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        if (!in_array($scopeType, ['project', 'component'], true)) {
            return $this->errorResponse('Unsupported scope_type internal value', 422);
        }

        $tenantId = $this->tenantId();
        $userId = (string) Auth::id();

        try {
            $version = $this->resolveVersionForApply($request, $tenantId);
        } catch (\InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        }

        if (!$version) {
            return $this->notFound('Published work template version not found');
        }

        $fingerprint = $this->buildApplyFingerprint($tenantId, $scopeType, $scopeId, (string) $version->id);
        $existing = WorkInstance::query()
            ->where('tenant_id', $tenantId)
            ->where('apply_fingerprint', $fingerprint)
            ->first();

        if ($existing) {
            $duplicatePayload = $this->buildApplyResponsePayload($existing, true, 0, 0);
            $this->auditLogger->log(
                $request,
                'zena.work-template.apply',
                'work_instance',
                (string) $existing->id,
                200,
                (string) $project->id,
                $tenantId,
                $userId,
                [
                    'entity_id' => (string) $version->id,
                    'scope_type' => $scopeType,
                    'scope_id' => $scopeId,
                    'project_id' => (string) $project->id,
                    'work_template_version_id' => (string) $version->id,
                    'apply_fingerprint' => $fingerprint,
                    'duplicate' => true,
                    'tasks_created' => 0,
                    'assignments_created' => 0,
                    'idempotency_key' => $request->input('idempotency_key'),
                ]
            );

            return $this->successResponse($duplicatePayload, 'Work template already applied for this scope', 200);
        }

        try {
            [$instance, $tasksCreated, $assignmentsCreated] = DB::transaction(
                function () use ($tenantId, $userId, $project, $scopeType, $scopeId, $component, $version, $fingerprint): array {
                    $instance = WorkInstance::create([
                        'tenant_id' => $tenantId,
                        'project_id' => $project->id,
                        'scope_type' => $scopeType,
                        'scope_id' => $scopeId,
                        'work_template_version_id' => $version->id,
                        'status' => 'pending',
                        'apply_fingerprint' => $fingerprint,
                        'created_by' => $userId,
                    ]);

                    $steps = WorkTemplateStep::query()
                        ->with('fields')
                        ->where('tenant_id', $tenantId)
                        ->where('work_template_version_id', $version->id)
                        ->orderBy('step_order')
                        ->get()
                        ->values();

                    $stepsByKey = [];
                    foreach ($steps as $step) {
                        $stepKey = (string) $step->step_key;
                        $this->assertStepConfigValid($step);
                        $stepsByKey[$stepKey] = $step;
                    }

                    $baseAnchor = $this->resolveBaseAnchor($project, $component);
                    $startAtByStepKey = [];
                    $deadlineByStepKey = [];
                    $resolving = [];

                    foreach ($stepsByKey as $stepKey => $step) {
                        $this->resolveTimelineForStep(
                            $stepKey,
                            $stepsByKey,
                            $baseAnchor,
                            $startAtByStepKey,
                            $deadlineByStepKey,
                            $resolving
                        );
                    }

                    $stepInstanceIdByKey = [];
                    foreach ($steps as $step) {
                        $stepKey = (string) $step->step_key;
                        $instanceStep = WorkInstanceStep::create([
                            'tenant_id' => $tenantId,
                            'work_instance_id' => $instance->id,
                            'work_template_step_id' => $step->id,
                            'step_key' => $stepKey,
                            'name' => $step->name,
                            'type' => $step->type,
                            'step_order' => $step->step_order,
                            'depends_on' => $step->depends_on,
                            'assignee_rule_json' => $step->assignee_rule_json,
                            'sla_hours' => $step->sla_hours,
                            'snapshot_fields_json' => $this->buildSnapshotFields($step),
                            'status' => 'pending',
                            'deadline_at' => $deadlineByStepKey[$stepKey] ?? null,
                        ]);
                        $stepInstanceIdByKey[$stepKey] = (string) $instanceStep->id;
                    }

                    $taskIdByStepKey = [];
                    foreach ($steps as $step) {
                        $taskIdByStepKey[(string) $step->step_key] = (string) Str::ulid();
                    }

                    $now = now();
                    $taskRows = [];
                    $assignmentRows = [];

                    foreach ($steps as $step) {
                        $stepKey = (string) $step->step_key;
                        $config = is_array($step->config_json) ? $step->config_json : [];
                        $startAt = $startAtByStepKey[$stepKey] ?? $baseAnchor;
                        $deadlineAt = $deadlineByStepKey[$stepKey] ?? null;

                        $dependsOn = is_array($step->depends_on) ? $step->depends_on : [];
                        $dependencyTaskIds = [];
                        foreach ($dependsOn as $dependencyKey) {
                            if (!is_string($dependencyKey) || !isset($taskIdByStepKey[$dependencyKey])) {
                                throw new \RuntimeException(sprintf('Invalid step dependency for step "%s".', $stepKey));
                            }
                            $dependencyTaskIds[] = $taskIdByStepKey[$dependencyKey];
                        }

                        $primaryAssignees = $this->resolveUsersForSelector(
                            $step->assignee_rule_json,
                            $tenantId,
                            $project
                        );
                        if ($primaryAssignees === []) {
                            throw new \RuntimeException(sprintf('Unresolved assignment rule for step "%s".', $stepKey));
                        }

                        $secondary = $this->resolveSecondaryAssignments($config, $tenantId, $project, $stepKey);
                        $taskId = $taskIdByStepKey[$stepKey];

                        $taskRows[] = [
                            'id' => $taskId,
                            'tenant_id' => $tenantId,
                            'project_id' => (string) $project->id,
                            'component_id' => $scopeType === 'component' ? $scopeId : null,
                            'work_instance_id' => (string) $instance->id,
                            'work_instance_step_id' => $stepInstanceIdByKey[$stepKey] ?? null,
                            'name' => (string) ($step->name ?? $stepKey),
                            'title' => (string) ($step->name ?? $stepKey),
                            'description' => is_string($config['description'] ?? null) ? $config['description'] : null,
                            'status' => Task::STATUS_PENDING,
                            'priority' => is_string($config['priority'] ?? null) ? $config['priority'] : Task::PRIORITY_MEDIUM,
                            'assigned_to' => $primaryAssignees[0],
                            'assignee_id' => $primaryAssignees[0],
                            'start_date' => $startAt->toDateString(),
                            'end_date' => $deadlineAt ? $deadlineAt->toDateString() : null,
                            'order' => (int) $step->step_order,
                            'dependencies_json' => json_encode($dependencyTaskIds),
                            'created_by' => $userId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        $assignmentRows = array_merge(
                            $assignmentRows,
                            $this->buildAssignmentRows($taskId, $tenantId, $userId, $deadlineAt, 'assignee', $primaryAssignees, $now),
                            $this->buildAssignmentRows($taskId, $tenantId, $userId, $deadlineAt, 'reviewer', $secondary['reviewers'], $now),
                            $this->buildAssignmentRows($taskId, $tenantId, $userId, $deadlineAt, 'watcher', $secondary['watchers'], $now)
                        );
                    }

                    if ($taskRows !== []) {
                        DB::table('tasks')->insert($taskRows);
                    }

                    if ($assignmentRows !== []) {
                        DB::table('task_assignments')->insert($assignmentRows);
                    }

                    return [$instance->fresh(['steps']), count($taskRows), count($assignmentRows)];
                }
            );
        } catch (QueryException $queryException) {
            if (str_contains(strtolower($queryException->getMessage()), 'wi_tenant_apply_fingerprint_unique')) {
                $existing = WorkInstance::query()
                    ->where('tenant_id', $tenantId)
                    ->where('apply_fingerprint', $fingerprint)
                    ->first();

                if ($existing) {
                    return $this->successResponse(
                        $this->buildApplyResponsePayload($existing, true, 0, 0),
                        'Work template already applied for this scope',
                        200
                    );
                }
            }

            throw $queryException;
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        }

        $this->auditLogger->log(
            $request,
            'zena.work-template.apply',
            'work_instance',
            (string) $instance->id,
            201,
            (string) $project->id,
            $tenantId,
            $userId,
            [
                'entity_id' => (string) $version->id,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'project_id' => (string) $project->id,
                'work_template_version_id' => (string) $version->id,
                'apply_fingerprint' => $fingerprint,
                'duplicate' => false,
                'tasks_created' => $tasksCreated,
                'assignments_created' => $assignmentsCreated,
                'idempotency_key' => $request->input('idempotency_key'),
            ]
        );

        return $this->successResponse(
            $this->buildApplyResponsePayload($instance, false, $tasksCreated, $assignmentsCreated),
            'Work template applied successfully',
            201
        );
    }

    public function exportTemplatePackage(Request $request, string $wtId): JsonResponse
    {
        if (!$this->currentUserHasPermission('template.view')) {
            return $this->forbidden('You do not have permission to access this resource');
        }

        try {
            $template = $this->templateForTenant($wtId)->firstOrFail();

            $versions = WorkTemplateVersion::query()
                ->with('steps.fields')
                ->where('tenant_id', $this->tenantId())
                ->where('work_template_id', $template->id)
                ->orderByDesc('created_at')
                ->get();

            $schemaVersion = $request->query('schema_version', WorkTemplatePackageService::SCHEMA_VERSION);
            if (!is_string($schemaVersion)) {
                $schemaVersion = WorkTemplatePackageService::SCHEMA_VERSION;
            }

            try {
                $this->templatePackageService->assertSupportedSchemaVersion($schemaVersion);
            } catch (\InvalidArgumentException $exception) {
                return $this->errorResponse($exception->getMessage(), 422);
            }

            $package = $this->templatePackageService->buildExportPayload($template, $versions, $schemaVersion);

            $this->auditLogger->log(
                $request,
                'zena.work-template.export-package',
                'work_template',
                (string) $template->id,
                200,
                null,
                $this->tenantId(),
                (string) Auth::id()
            );

            return $this->successResponse($package, 'Template package exported successfully');
        } catch (ModelNotFoundException) {
            return $this->notFound('Work template not found');
        }
    }

    public function importTemplatePackage(Request $request): JsonResponse
    {
        if (!$this->currentUserHasPermission('template.edit_draft')) {
            return $this->forbidden('You do not have permission to access this resource');
        }

        $validator = Validator::make($request->all(), [
            'manifest' => 'required|array',
            'schema_version' => 'required|string',
            'template' => 'required|array',
            'template.code' => 'required|string|max:100',
            'template.name' => 'required|string|max:255',
            'template.description' => 'nullable|string',
            'template.versions' => 'required|array|min:1',
            'template.versions.*.semver' => 'required|string|max:100',
            'template.versions.*.content_json' => 'nullable|array',
            'template.versions.*.steps' => 'nullable|array',
        ]);

        $schemaVersion = $request->input('schema_version', WorkTemplatePackageService::SCHEMA_VERSION);
        if (!is_string($schemaVersion)) {
            $schemaVersion = WorkTemplatePackageService::SCHEMA_VERSION;
        }

        try {
            $this->templatePackageService->assertSupportedSchemaVersion($schemaVersion);
        } catch (\InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        }

        $validator->after(function ($validator) use ($request, $schemaVersion): void {
            if ($schemaVersion !== WorkTemplatePackageService::SCHEMA_VERSION_V2) {
                return;
            }

            $manifest = $request->input('manifest');
            if (!is_array($manifest) || !is_array($manifest['capabilities'] ?? null)) {
                $validator->errors()->add('manifest.capabilities', 'The manifest.capabilities field must be an array.');
            }

            $template = $request->input('template');
            if (!is_array($template)) {
                return;
            }

            foreach (($template['versions'] ?? []) as $versionIndex => $versionData) {
                if (!is_array($versionData)) {
                    continue;
                }

                $steps = null;
                if (is_array($versionData['steps'] ?? null)) {
                    $steps = $versionData['steps'];
                } elseif (is_array($versionData['content_json'] ?? null) && is_array($versionData['content_json']['steps'] ?? null)) {
                    $steps = $versionData['content_json']['steps'];
                }

                if (!is_array($steps)) {
                    $validator->errors()->add(
                        "template.versions.$versionIndex.steps",
                        'Template version payload requires steps data for schema_version "2.0.0".'
                    );
                    continue;
                }

                foreach ($steps as $stepIndex => $stepData) {
                    if (!is_array($stepData)) {
                        $validator->errors()->add(
                            "template.versions.$versionIndex.steps.$stepIndex",
                            'The step payload must be an object.'
                        );
                        continue;
                    }

                    $config = $stepData['config'] ?? [];
                    if (!is_array($config)) {
                        $validator->errors()->add(
                            "template.versions.$versionIndex.steps.$stepIndex.config",
                            'Invalid checklist config shape.'
                        );
                        continue;
                    }

                    $checklists = $config['checklist_items'] ?? [];
                    if (!is_array($checklists)) {
                        $validator->errors()->add(
                            "template.versions.$versionIndex.steps.$stepIndex.config.checklist_items",
                            'Invalid checklist config shape.'
                        );
                    } else {
                        foreach ($checklists as $itemIndex => $item) {
                            if (!is_array($item)) {
                                $validator->errors()->add(
                                    "template.versions.$versionIndex.steps.$stepIndex.config.checklist_items.$itemIndex",
                                    'Invalid checklist config shape.'
                                );
                            }
                        }
                    }

                    $requiredDocs = $config['required_docs'] ?? [];
                    if (!is_array($requiredDocs)) {
                        $validator->errors()->add(
                            "template.versions.$versionIndex.steps.$stepIndex.config.required_docs",
                            'Invalid checklist config shape.'
                        );
                    }

                    $assignmentRules = $config['assignment_rules'] ?? ['reviewers' => [], 'watchers' => []];
                    if (!is_array($assignmentRules)) {
                        $validator->errors()->add(
                            "template.versions.$versionIndex.steps.$stepIndex.config.assignment_rules",
                            'Invalid assignment selector grammar.'
                        );
                        continue;
                    }

                    foreach (['reviewers', 'watchers'] as $bucket) {
                        if (!is_array($assignmentRules[$bucket] ?? [])) {
                            $validator->errors()->add(
                                "template.versions.$versionIndex.steps.$stepIndex.config.assignment_rules.$bucket",
                                'Invalid assignment selector grammar.'
                            );
                            continue;
                        }

                        foreach (($assignmentRules[$bucket] ?? []) as $selectorIndex => $selector) {
                            if (!is_array($selector) || !$this->isValidSelectorShape($selector)) {
                                $validator->errors()->add(
                                    "template.versions.$versionIndex.steps.$stepIndex.config.assignment_rules.$bucket.$selectorIndex",
                                    'Invalid assignment selector grammar.'
                                );
                            }
                        }
                    }
                }
            }
        });

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $tenantId = $this->tenantId();
        $userId = (string) Auth::id();
        $payload = $request->input('template');

        $template = DB::transaction(
            fn (): WorkTemplate => $this->templatePackageService->importTemplate($tenantId, $userId, $payload, $schemaVersion)
        );

        $this->auditLogger->log(
            $request,
            'zena.work-template.import-package',
            'work_template',
            (string) $template->id,
            201,
            null,
            $tenantId,
            $userId
        );

        return $this->successResponse($template, 'Template package imported successfully', 201);
    }

    private function templateForTenant(string $id)
    {
        return WorkTemplate::query()
            ->where('tenant_id', $this->tenantId())
            ->whereKey($id);
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

    private function buildContentPayload(Request $request, array $base = []): array
    {
        $content = $request->input('content_json', $base);

        if (!is_array($content)) {
            $content = [];
        }

        if ($request->has('steps')) {
            $content['steps'] = $request->input('steps', []);
        }

        if ($request->has('approvals')) {
            $content['approvals'] = $request->input('approvals', []);
        }

        if ($request->has('rules')) {
            $content['rules'] = $request->input('rules', []);
        }

        $content['steps'] = $content['steps'] ?? [];
        $content['approvals'] = $content['approvals'] ?? [];
        $content['rules'] = $content['rules'] ?? [];

        return $content;
    }

    private function buildApplyFingerprint(string $tenantId, string $scopeType, string $scopeId, string $versionId): string
    {
        return sha1(sprintf('%s|%s|%s|%s', $tenantId, $scopeType, $scopeId, $versionId));
    }

    private function buildApplyResponsePayload(
        WorkInstance $instance,
        bool $duplicate,
        int $tasksCreated,
        int $assignmentsCreated
    ): array {
        return [
            'id' => (string) $instance->id,
            'scope_type' => (string) ($instance->scope_type ?? 'project'),
            'scope_id' => (string) ($instance->scope_id ?? $instance->project_id),
            'project_id' => (string) $instance->project_id,
            'work_template_version_id' => (string) $instance->work_template_version_id,
            'status' => (string) $instance->status,
            'duplicate' => $duplicate,
            'tasks_created' => $tasksCreated,
            'assignments_created' => $assignmentsCreated,
        ];
    }

    private function resolveBaseAnchor(Project $project, ?Component $component): CarbonImmutable
    {
        if ($component && $component->start_date) {
            return CarbonImmutable::parse($component->start_date)->startOfDay();
        }

        if ($project->start_date) {
            return CarbonImmutable::parse($project->start_date)->startOfDay();
        }

        return CarbonImmutable::now()->startOfDay();
    }

    /**
     * @param array<string, WorkTemplateStep> $stepsByKey
     * @param array<string, CarbonImmutable> $startAtByStepKey
     * @param array<string, CarbonImmutable|null> $deadlineByStepKey
     * @param array<string, bool> $resolving
     */
    private function resolveTimelineForStep(
        string $stepKey,
        array $stepsByKey,
        CarbonImmutable $baseAnchor,
        array &$startAtByStepKey,
        array &$deadlineByStepKey,
        array &$resolving
    ): void {
        if (array_key_exists($stepKey, $startAtByStepKey)) {
            return;
        }

        if (($resolving[$stepKey] ?? false) === true) {
            throw new \RuntimeException(sprintf('Invalid step dependency for step "%s".', $stepKey));
        }

        $step = $stepsByKey[$stepKey] ?? null;
        if (!$step) {
            throw new \RuntimeException(sprintf('Invalid step dependency for step "%s".', $stepKey));
        }

        $resolving[$stepKey] = true;
        $dependsOn = is_array($step->depends_on) ? $step->depends_on : [];
        $startAt = $baseAnchor;

        if ($dependsOn !== []) {
            $anchors = [];
            foreach ($dependsOn as $dependencyStepKey) {
                if (!is_string($dependencyStepKey) || !isset($stepsByKey[$dependencyStepKey])) {
                    throw new \RuntimeException(sprintf('Invalid step dependency for step "%s".', $stepKey));
                }

                $this->resolveTimelineForStep(
                    $dependencyStepKey,
                    $stepsByKey,
                    $baseAnchor,
                    $startAtByStepKey,
                    $deadlineByStepKey,
                    $resolving
                );

                $anchors[] = $deadlineByStepKey[$dependencyStepKey] ?? $baseAnchor;
            }

            if ($anchors !== []) {
                usort(
                    $anchors,
                    static fn (CarbonImmutable $left, CarbonImmutable $right): int => $left->getTimestamp() <=> $right->getTimestamp()
                );
                $startAt = end($anchors) ?: $baseAnchor;
            }
        }

        $startAtByStepKey[$stepKey] = $startAt;
        $deadlineByStepKey[$stepKey] = $step->sla_hours !== null
            ? $startAt->addHours((int) $step->sla_hours)
            : null;

        $resolving[$stepKey] = false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildSnapshotFields(WorkTemplateStep $step): array
    {
        $fields = $step->fields->map(static fn (WorkTemplateField $field): array => [
            'field_key' => $field->field_key,
            'label' => $field->label,
            'type' => $field->type,
            'required' => (bool) $field->is_required,
            'default' => $field->default_value,
            'validation' => $field->validation_json,
            'enum_options' => $field->enum_options_json,
            'visibility_rule' => $field->visibility_rule_json,
        ])->values()->all();

        $config = is_array($step->config_json) ? $step->config_json : [];
        $checklistItems = $config['checklist_items'] ?? [];
        if (is_array($checklistItems)) {
            foreach ($checklistItems as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $itemKey = (string) ($item['key'] ?? Str::ulid());
                $fields[] = [
                    'field_key' => 'checklist:' . $itemKey,
                    'label' => (string) ($item['label'] ?? $itemKey),
                    'type' => 'checklist_boolean',
                    'required' => (bool) ($item['required'] ?? false),
                    'default' => null,
                    'validation' => null,
                    'enum_options' => null,
                    'visibility_rule' => null,
                ];
            }
        }

        $requiredDocs = $config['required_docs'] ?? [];
        if (is_array($requiredDocs)) {
            foreach ($requiredDocs as $doc) {
                if (!is_array($doc)) {
                    continue;
                }

                $docKey = (string) ($doc['key'] ?? Str::ulid());
                $fields[] = [
                    'field_key' => 'required-doc:' . $docKey,
                    'label' => (string) ($doc['label'] ?? $docKey),
                    'type' => 'required_doc_meta',
                    'required' => (bool) ($doc['required'] ?? false),
                    'default' => null,
                    'validation' => null,
                    'enum_options' => null,
                    'visibility_rule' => null,
                ];
            }
        }

        return $fields;
    }

    private function assertStepConfigValid(WorkTemplateStep $step): void
    {
        $config = $step->config_json;
        if ($config === null) {
            return;
        }

        if (!is_array($config)) {
            throw new \RuntimeException('Invalid checklist config shape.');
        }

        $checklistItems = $config['checklist_items'] ?? [];
        if (!is_array($checklistItems)) {
            throw new \RuntimeException('Invalid checklist config shape.');
        }

        foreach ($checklistItems as $item) {
            if (!is_array($item)) {
                throw new \RuntimeException('Invalid checklist config shape.');
            }
        }

        $assignmentRules = $config['assignment_rules'] ?? ['reviewers' => [], 'watchers' => []];
        if (!is_array($assignmentRules)) {
            throw new \RuntimeException('Invalid assignment selector grammar.');
        }

        foreach (['reviewers', 'watchers'] as $bucket) {
            if (!is_array($assignmentRules[$bucket] ?? [])) {
                throw new \RuntimeException('Invalid assignment selector grammar.');
            }

            foreach ($assignmentRules[$bucket] as $selector) {
                if (!is_array($selector) || !$this->isValidSelectorShape($selector)) {
                    throw new \RuntimeException('Invalid assignment selector grammar.');
                }
            }
        }
    }

    private function isValidSelectorShape(array $selector): bool
    {
        return (
            (isset($selector['role']) && is_string($selector['role']))
            || (isset($selector['project_role']) && is_string($selector['project_role']))
            || (isset($selector['user_id']) && is_string($selector['user_id']))
        );
    }

    /**
     * @return array{reviewers: array<int, string>, watchers: array<int, string>}
     */
    private function resolveSecondaryAssignments(array $config, string $tenantId, Project $project, string $stepKey): array
    {
        $assignmentRules = $config['assignment_rules'] ?? ['reviewers' => [], 'watchers' => []];
        if (!is_array($assignmentRules)) {
            throw new \RuntimeException('Invalid assignment selector grammar.');
        }

        $result = ['reviewers' => [], 'watchers' => []];

        foreach (['reviewers', 'watchers'] as $bucket) {
            $selectors = $assignmentRules[$bucket] ?? [];
            if (!is_array($selectors)) {
                throw new \RuntimeException('Invalid assignment selector grammar.');
            }

            foreach ($selectors as $selector) {
                $resolved = $this->resolveUsersForSelector($selector, $tenantId, $project);
                if ($resolved === []) {
                    throw new \RuntimeException(sprintf('Unresolved assignment rule for step "%s".', $stepKey));
                }

                $result[$bucket] = array_values(array_unique(array_merge($result[$bucket], $resolved)));
            }
        }

        return $result;
    }

    /**
     * @param mixed $selector
     * @return array<int, string>
     */
    private function resolveUsersForSelector($selector, string $tenantId, Project $project): array
    {
        if (!is_array($selector) || !$this->isValidSelectorShape($selector)) {
            return [];
        }

        if (is_string($selector['role'] ?? null)) {
            if ($selector['role'] !== 'project_manager') {
                return [];
            }

            if (!$project->pm_id) {
                return [];
            }

            $exists = User::query()
                ->where('tenant_id', $tenantId)
                ->whereKey((string) $project->pm_id)
                ->exists();

            return $exists ? [(string) $project->pm_id] : [];
        }

        if (is_string($selector['project_role'] ?? null)) {
            return UserRoleProject::query()
                ->join('roles', 'roles.id', '=', 'project_user_roles.role_id')
                ->join('users', 'users.id', '=', 'project_user_roles.user_id')
                ->where('project_user_roles.project_id', (string) $project->id)
                ->where('roles.name', $selector['project_role'])
                ->where('users.tenant_id', $tenantId)
                ->whereNull('project_user_roles.deleted_at')
                ->distinct()
                ->pluck('project_user_roles.user_id')
                ->map(static fn ($id): string => (string) $id)
                ->values()
                ->all();
        }

        if (is_string($selector['user_id'] ?? null)) {
            $exists = User::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($selector['user_id'])
                ->exists();

            return $exists ? [(string) $selector['user_id']] : [];
        }

        return [];
    }

    /**
     * @param array<int, string> $userIds
     * @return array<int, array<string, mixed>>
     */
    private function buildAssignmentRows(
        string $taskId,
        string $tenantId,
        string $userId,
        ?CarbonImmutable $deadlineAt,
        string $role,
        array $userIds,
        \Illuminate\Support\Carbon $now
    ): array {
        $rows = [];

        foreach (array_values(array_unique($userIds)) as $assigneeId) {
            $rows[] = [
                'id' => (string) Str::ulid(),
                'tenant_id' => $tenantId,
                'task_id' => $taskId,
                'user_id' => $assigneeId,
                'assignment_type' => 'user',
                'role' => $role,
                'status' => 'assigned',
                'assigned_at' => $now,
                'assigned_by' => $userId,
                'due_date' => $deadlineAt?->toDateTimeString(),
                'actual_hours' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }

    private function syncStepsAndFields(WorkTemplateVersion $version, array $steps): void
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
                'depends_on' => $stepData['depends_on'] ?? [],
                'assignee_rule_json' => $stepData['assignee_rule'] ?? null,
                'sla_hours' => isset($stepData['sla_hours']) ? (int) $stepData['sla_hours'] : null,
                'config_json' => $stepData['config'] ?? null,
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
                    'validation_json' => $fieldData['validation'] ?? null,
                    'enum_options_json' => $fieldData['enum_options'] ?? null,
                    'visibility_rule_json' => $fieldData['visibility_rule'] ?? null,
                ]);
            }
        }
    }

    private function createDraftFromLatestPublished(WorkTemplate $template, string $tenantId, string $userId): WorkTemplateVersion
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
            'content_json' => $latestPublished?->content_json ?? ['steps' => [], 'approvals' => [], 'rules' => []],
            'is_immutable' => false,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        if ($latestPublished) {
            $this->cloneStepsAndFields($latestPublished, $draft);
        }

        return $draft;
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

    private function resolveVersionForApply(Request $request, string $tenantId): ?WorkTemplateVersion
    {
        $versionId = $request->input('work_template_version_id');
        $templateId = $request->input('work_template_id');

        if ((!is_string($versionId) || $versionId === '') && (!is_string($templateId) || $templateId === '')) {
            return null;
        }

        if (is_string($versionId) && $versionId !== '') {
            $version = WorkTemplateVersion::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($versionId)
                ->whereNotNull('published_at')
                ->first();

            if (!$version) {
                return null;
            }

            if (is_string($templateId) && $templateId !== '' && (string) $version->work_template_id !== $templateId) {
                throw new \InvalidArgumentException(
                    'work_template_id and work_template_version_id must reference the same template lineage.'
                );
            }

            return $version;
        }

        if (!is_string($templateId) || $templateId === '') {
            return null;
        }

        return WorkTemplateVersion::query()
            ->where('tenant_id', $tenantId)
            ->where('work_template_id', $templateId)
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->first();
    }

    private function currentUserHasPermission(string $permission): bool
    {
        $userId = Auth::id();
        if (!$userId) {
            return false;
        }

        return DB::table('user_roles')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'user_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('user_roles.user_id', (string) $userId)
            ->where(function ($query) use ($permission): void {
                $query->where('permissions.name', $permission)
                    ->orWhere('permissions.code', $permission);
            })
            ->exists();
    }
}
