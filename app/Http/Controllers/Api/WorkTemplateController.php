<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Project;
use App\Models\WorkInstance;
use App\Models\WorkInstanceStep;
use App\Models\WorkTemplate;
use App\Models\WorkTemplateField;
use App\Models\WorkTemplateStep;
use App\Models\WorkTemplateVersion;
use App\Services\WorkTemplatePackageService;
use App\Services\ZenaAuditLogger;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        $validator = Validator::make($request->all(), [
            'work_template_id' => 'nullable|string',
            'work_template_version_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $tenantId = $this->tenantId();
        $userId = (string) Auth::id();

        $project = Project::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($projectId)
            ->first();

        if (!$project) {
            return $this->notFound('Project not found');
        }

        $version = $this->resolveVersionForApply($request, $tenantId);
        if (!$version) {
            return $this->notFound('Published work template version not found');
        }

        $instance = DB::transaction(function () use ($tenantId, $project, $version, $userId): WorkInstance {
            $instance = WorkInstance::create([
                'tenant_id' => $tenantId,
                'project_id' => $project->id,
                'work_template_version_id' => $version->id,
                'status' => 'pending',
                'created_by' => $userId,
            ]);

            $steps = WorkTemplateStep::query()
                ->with('fields')
                ->where('tenant_id', $tenantId)
                ->where('work_template_version_id', $version->id)
                ->orderBy('step_order')
                ->get();

            foreach ($steps as $step) {
                WorkInstanceStep::create([
                    'tenant_id' => $tenantId,
                    'work_instance_id' => $instance->id,
                    'work_template_step_id' => $step->id,
                    'step_key' => $step->step_key,
                    'name' => $step->name,
                    'type' => $step->type,
                    'step_order' => $step->step_order,
                    'depends_on' => $step->depends_on,
                    'assignee_rule_json' => $step->assignee_rule_json,
                    'sla_hours' => $step->sla_hours,
                    'snapshot_fields_json' => $step->fields->map(static fn (WorkTemplateField $field): array => [
                        'field_key' => $field->field_key,
                        'label' => $field->label,
                        'type' => $field->type,
                        'required' => (bool) $field->is_required,
                        'default' => $field->default_value,
                        'validation' => $field->validation_json,
                        'enum_options' => $field->enum_options_json,
                        'visibility_rule' => $field->visibility_rule_json,
                    ])->values()->all(),
                    'status' => 'pending',
                    'deadline_at' => $step->sla_hours ? now()->addHours((int) $step->sla_hours) : null,
                ]);
            }

            return $instance->fresh(['steps']);
        });

        $this->auditLogger->log(
            $request,
            'zena.work-template.apply',
            'work_instance',
            (string) $instance->id,
            201,
            (string) $project->id,
            $tenantId,
            $userId,
            ['entity_id' => (string) $version->id]
        );

        return $this->successResponse($instance, 'Work template applied successfully', 201);
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

            $package = $this->templatePackageService->buildExportPayload($template, $versions);

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

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $tenantId = $this->tenantId();
        $userId = (string) Auth::id();
        $payload = $request->input('template');

        try {
            $this->templatePackageService->assertSupportedSchemaVersion(
                $request->string('schema_version')->toString()
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        }

        $template = DB::transaction(
            fn (): WorkTemplate => $this->templatePackageService->importTemplate($tenantId, $userId, $payload)
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
        if (is_string($versionId) && $versionId !== '') {
            return WorkTemplateVersion::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($versionId)
                ->whereNotNull('published_at')
                ->first();
        }

        $templateId = $request->input('work_template_id');
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
