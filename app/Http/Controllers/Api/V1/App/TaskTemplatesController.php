<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Models\TemplateSet;
use App\Models\TemplatePreset;
use App\Models\Project;
use App\Services\TaskTemplateService;
use App\Services\TemplateApplyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * TaskTemplatesController
 * 
 * API controller for managing WBS-style task template sets.
 * 
 * Round 95: Task Template Library – Backend v1
 */
class TaskTemplatesController extends BaseApiV1Controller
{
    public function __construct(
        private TaskTemplateService $templateService,
        private TemplateApplyService $applyService
    ) {}

    /**
     * Get list of template sets
     * 
     * GET /api/v1/app/task-templates
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Check authorization via policy
            $this->authorize('viewAny', TemplateSet::class);
            
            $tenantId = $this->getTenantId();
            
            $filters = $request->only(['search', 'is_active']);
            $perPage = (int) $request->get('per_page', 15);
            
            $templates = $this->templateService->getTemplateSets($tenantId, $filters, $perPage);

            if (method_exists($templates, 'items')) {
                return $this->paginatedResponse(
                    $templates->items(),
                    [
                        'current_page' => $templates->currentPage(),
                        'per_page' => $templates->perPage(),
                        'total' => $templates->total(),
                        'last_page' => $templates->lastPage(),
                        'from' => $templates->firstItem(),
                        'to' => $templates->lastItem(),
                    ],
                    'Template sets retrieved successfully',
                    [
                        'first' => $templates->url(1),
                        'last' => $templates->url($templates->lastPage()),
                        'prev' => $templates->previousPageUrl(),
                        'next' => $templates->nextPageUrl(),
                    ]
                );
            }

            return $this->successResponse($templates, 'Template sets retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'index']);
            return $this->errorResponse('Failed to retrieve template sets', 500);
        }
    }

    /**
     * Get a template set with full tree
     * 
     * GET /api/v1/app/task-templates/{set}
     * 
     * @param string $set Template set ID
     * @return JsonResponse
     */
    public function show(string $set): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            $templateSet = $this->templateService->getTemplateSetWithTree($set, $tenantId);
            
            if (!$templateSet) {
                return $this->errorResponse('Template set not found', 404, null, 'TEMPLATE_SET_NOT_FOUND');
            }

            // Check authorization via policy
            $this->authorize('view', $templateSet);

            // Format response with tree structure
            $response = [
                'id' => $templateSet->id,
                'code' => $templateSet->code,
                'name' => $templateSet->name,
                'description' => $templateSet->description,
                'version' => $templateSet->version,
                'is_active' => $templateSet->is_active,
                'is_global' => $templateSet->is_global,
                'created_by' => $templateSet->created_by,
                'created_at' => $templateSet->created_at?->toISOString(),
                'updated_at' => $templateSet->updated_at?->toISOString(),
                'phases' => $templateSet->phases->map(function ($phase) use ($templateSet) {
                    return [
                        'id' => $phase->id,
                        'code' => $phase->code,
                        'name' => $phase->name,
                        'order_index' => $phase->order_index,
                        'metadata' => $phase->metadata,
                        'disciplines' => $templateSet->disciplines->map(function ($discipline) use ($phase, $templateSet) {
                            $tasks = $templateSet->tasks
                                ->where('phase_id', $phase->id)
                                ->where('discipline_id', $discipline->id)
                                ->map(function ($task) {
                                    return [
                                        'id' => $task->id,
                                        'code' => $task->code,
                                        'name' => $task->name,
                                        'description' => $task->description,
                                        'est_duration_days' => $task->est_duration_days,
                                        'role_key' => $task->role_key,
                                        'deliverable_type' => $task->deliverable_type,
                                        'order_index' => $task->order_index,
                                        'is_optional' => $task->is_optional,
                                        'metadata' => $task->metadata,
                                        'dependencies' => $task->dependencies->map(function ($dep) {
                                            return [
                                                'id' => $dep->depends_on_task_id,
                                                'code' => $dep->dependsOn->code ?? null,
                                                'name' => $dep->dependsOn->name ?? null,
                                            ];
                                        })->values(),
                                    ];
                                })->values();
                            
                            return [
                                'id' => $discipline->id,
                                'code' => $discipline->code,
                                'name' => $discipline->name,
                                'color_hex' => $discipline->color_hex,
                                'order_index' => $discipline->order_index,
                                'metadata' => $discipline->metadata,
                                'tasks' => $tasks,
                            ];
                        })->values(),
                    ];
                })->values(),
            ];

            return $this->successResponse($response, 'Template set retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'show', 'template_set_id' => $set]);
            return $this->errorResponse('Failed to retrieve template set', 500);
        }
    }

    /**
     * Create a new template set
     * 
     * POST /api/v1/app/task-templates
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Check authorization via policy
            $this->authorize('create', TemplateSet::class);
            
            $validated = $request->validate([
                'code' => ['required', 'string', 'max:255'],
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'version' => ['nullable', 'string', 'max:50'],
                'is_active' => ['nullable', 'boolean'],
                'metadata' => ['nullable', 'array'],
                'phases' => ['nullable', 'array'],
                'phases.*.code' => ['required_with:phases', 'string', 'max:255'],
                'phases.*.name' => ['required_with:phases', 'string', 'max:255'],
                'phases.*.order_index' => ['nullable', 'integer'],
                'phases.*.metadata' => ['nullable', 'array'],
                'disciplines' => ['nullable', 'array'],
                'disciplines.*.code' => ['required_with:disciplines', 'string', 'max:255'],
                'disciplines.*.name' => ['required_with:disciplines', 'string', 'max:255'],
                'disciplines.*.color_hex' => ['nullable', 'string', 'max:7'],
                'disciplines.*.order_index' => ['nullable', 'integer'],
                'disciplines.*.metadata' => ['nullable', 'array'],
                'tasks' => ['nullable', 'array'],
                'tasks.*.phase_id' => ['required_with:tasks', 'string', 'ulid'],
                'tasks.*.discipline_id' => ['required_with:tasks', 'string', 'ulid'],
                'tasks.*.code' => ['required_with:tasks', 'string', 'max:255'],
                'tasks.*.name' => ['required_with:tasks', 'string', 'max:255'],
                'tasks.*.description' => ['nullable', 'string'],
                'tasks.*.est_duration_days' => ['nullable', 'integer', 'min:0'],
                'tasks.*.role_key' => ['nullable', 'string', 'max:255'],
                'tasks.*.deliverable_type' => ['nullable', 'string', 'max:255'],
                'tasks.*.order_index' => ['nullable', 'integer'],
                'tasks.*.is_optional' => ['nullable', 'boolean'],
                'tasks.*.metadata' => ['nullable', 'array'],
                'tasks.*.dependencies' => ['nullable', 'array'],
                'tasks.*.dependencies.*' => ['string', 'ulid'],
            ]);

            $tenantId = $this->getTenantId();
            
            $templateSet = $this->templateService->createTemplateSet($validated, $tenantId);
            
            return $this->successResponse(
                $templateSet,
                'Template set created successfully',
                201
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'store']);
            return $this->errorResponse(
                $e->getMessage() ?: 'Failed to create template set',
                $e->getCode() && $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500
            );
        }
    }

    /**
     * Update template set metadata
     * 
     * PUT /api/v1/app/task-templates/{set}
     * 
     * @param Request $request
     * @param string $set Template set ID
     * @return JsonResponse
     */
    public function update(Request $request, string $set): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            $templateSet = $this->templateService->getTemplateSetWithTree($set, $tenantId);
            
            if (!$templateSet) {
                return $this->errorResponse('Template set not found', 404, null, 'TEMPLATE_SET_NOT_FOUND');
            }

            // Check authorization via policy
            $this->authorize('update', $templateSet);
            
            $validated = $request->validate([
                'name' => ['sometimes', 'required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'version' => ['nullable', 'string', 'max:50'],
                'is_active' => ['nullable', 'boolean'],
                'metadata' => ['nullable', 'array'],
            ]);
            
            $templateSet = $this->templateService->updateTemplateSet($set, $validated, $tenantId);
            
            return $this->successResponse($templateSet, 'Template set updated successfully');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'update', 'template_set_id' => $set]);
            return $this->errorResponse(
                $e->getMessage() ?: 'Failed to update template set',
                $e->getCode() && $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500
            );
        }
    }

    /**
     * Duplicate a template set
     * 
     * POST /api/v1/app/task-templates/{set}/duplicate
     * 
     * @param Request $request
     * @param string $set Template set ID to duplicate
     * @return JsonResponse
     */
    public function duplicate(Request $request, string $set): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            $sourceSet = $this->templateService->getTemplateSetWithTree($set, $tenantId);
            
            if (!$sourceSet) {
                return $this->errorResponse('Template set not found', 404, null, 'TEMPLATE_SET_NOT_FOUND');
            }

            // Check authorization: user must be able to view the source and create new templates
            $this->authorize('view', $sourceSet);
            $this->authorize('create', TemplateSet::class);
            
            $validated = $request->validate([
                'code' => ['nullable', 'string', 'max:255'],
                'name' => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'version' => ['nullable', 'string', 'max:50'],
                'is_active' => ['nullable', 'boolean'],
                'metadata' => ['nullable', 'array'],
            ]);
            
            $newSet = $this->templateService->duplicateTemplateSet($set, $validated, $tenantId);
            
            return $this->successResponse(
                $newSet,
                'Template set duplicated successfully',
                201
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'duplicate', 'template_set_id' => $set]);
            return $this->errorResponse(
                $e->getMessage() ?: 'Failed to duplicate template set',
                $e->getCode() && $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500
            );
        }
    }

    /**
     * Apply template set to project
     * 
     * POST /api/v1/app/projects/{project}/task-templates/apply
     * 
     * Round 97: Apply Template Set → Create Project Tasks
     * 
     * @param Request $request
     * @param string $project Project ID
     * @return JsonResponse
     */
    public function applyToProject(Request $request, string $project): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $user = auth()->user();
            
            if (!$user) {
                return $this->errorResponse('Unauthenticated', 401, null, 'UNAUTHENTICATED');
            }

            // Find and authorize project
            $projectModel = Project::where('id', $project)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$projectModel) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            // Check project authorization
            $this->authorize('view', $projectModel);
            $this->authorize('update', $projectModel);

            // Validate request
            $validated = $request->validate([
                'template_set_id' => ['required', 'string', 'ulid'],
                'preset_id' => ['nullable', 'string', 'ulid'],
                'options' => ['nullable', 'array'],
                'options.include_dependencies' => ['nullable', 'boolean'],
            ]);

            // Find template set
            $templateSet = $this->templateService->getTemplateSetWithTree($validated['template_set_id'], $tenantId);
            
            if (!$templateSet) {
                return $this->errorResponse('Template set not found', 404, null, 'TEMPLATE_SET_NOT_FOUND');
            }

            // Check template set authorization
            $this->authorize('view', $templateSet);

            // Find preset if provided
            $preset = null;
            if (!empty($validated['preset_id'])) {
                $preset = TemplatePreset::where('id', $validated['preset_id'])
                    ->where('set_id', $templateSet->id)
                    ->first();

                if (!$preset) {
                    return $this->errorResponse('Preset not found', 404, null, 'PRESET_NOT_FOUND');
                }
            }

            // Apply template
            $options = $validated['options'] ?? [];
            $result = $this->applyService->applyToProject(
                $tenantId,
                $projectModel,
                $templateSet,
                $preset,
                $options,
                $user
            );

            return $this->successResponse(
                $result,
                'Template applied successfully',
                200
            );
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 403, null, 'UNAUTHORIZED');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'applyToProject', 'project_id' => $project]);
            return $this->errorResponse(
                $e->getMessage() ?: 'Failed to apply template',
                $e->getCode() && $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500
            );
        }
    }
}

