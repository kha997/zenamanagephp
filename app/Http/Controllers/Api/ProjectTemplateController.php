<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Src\CoreProject\Models\LegacyProjectAdapter as Project;
use App\Models\ProjectTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * ProjectTemplateController - API Controller cho Project Template management
 */
class ProjectTemplateController extends Controller
{
    /**
     * Get all project templates with pagination and filtering
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $query = ProjectTemplate::forTenant($user->tenant_id);
            
            // Apply filters
            if ($request->has('category')) {
                $query->where('category', $request->input('category'));
            }
            
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }
            
            $templates = $query->orderBy('created_at', 'desc')
                             ->paginate($request->input('per_page', 15));
            
            return response()->json([
                'status' => 'success',
                'data' => $templates->items(),
                'meta' => [
                    'total' => $templates->total(),
                    'per_page' => $templates->perPage(),
                    'current_page' => $templates->currentPage(),
                    'last_page' => $templates->lastPage(),
                    'from' => $templates->firstItem(),
                    'to' => $templates->lastItem()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get project templates', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'filters' => $request->all()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve project templates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific project template by ID
     */
    public function show(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $template = ProjectTemplate::forTenant($user->tenant_id)->find($id);
            
            if (!$template) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Project template not found'
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'data' => $template
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get project template', [
                'error' => $e->getMessage(),
                'template_id' => $id,
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve project template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new project template
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Check permission
            if (!$user->hasPermission('project.write')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient permissions to create project templates'
                ], 403);
            }
            
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:2000',
                'category' => 'required|string|max:100',
                'template_data' => 'required|array',
                'template_data.name' => 'required|string|max:255',
                'template_data.description' => 'nullable|string|max:2000',
                'template_data.start_date' => 'nullable|date',
                'template_data.end_date' => 'nullable|date|after:template_data.start_date',
                'template_data.budget_planned' => 'nullable|numeric|min:0',
                'template_data.priority' => ['nullable', Rule::in(Project::VALID_PRIORITIES)],
                'template_data.tags' => 'nullable|array',
                'template_data.tags.*' => 'string|max:50',
                'template_data.settings' => 'nullable|array',
                'milestones' => 'nullable|array',
                'milestones.*.name' => 'required|string|max:255',
                'milestones.*.description' => 'nullable|string|max:2000',
                'milestones.*.target_date' => 'nullable|date',
                'milestones.*.order' => 'nullable|integer|min:0',
                'is_public' => 'nullable|boolean'
            ]);
            
            DB::beginTransaction();
            
            $template = ProjectTemplate::create([
                'tenant_id' => $user->tenant_id,
                'name' => $validated['name'],
                'description' => $validated['description'],
                'category' => $validated['category'],
                'template_data' => $validated['template_data'],
                'milestones' => $validated['milestones'] ?? [],
                'is_public' => $validated['is_public'] ?? false,
                'created_by' => $user->id
            ]);
            
            DB::commit();
            
            Log::info('Project template created successfully', [
                'template_id' => $template->id,
                'user_id' => $user->id,
                'template_name' => $template->name
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Project template created successfully',
                'data' => $template
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to create project template', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'data' => $request->all()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create project template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing project template
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $template = ProjectTemplate::forTenant($user->tenant_id)->find($id);
            
            if (!$template) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Project template not found'
                ], 404);
            }
            
            // Check permission
            if (!$user->hasPermission('project.write')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient permissions to update project templates'
                ], 403);
            }
            
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string|max:2000',
                'category' => 'sometimes|string|max:100',
                'template_data' => 'sometimes|array',
                'template_data.name' => 'required_with:template_data|string|max:255',
                'template_data.description' => 'nullable|string|max:2000',
                'template_data.start_date' => 'nullable|date',
                'template_data.end_date' => 'nullable|date|after:template_data.start_date',
                'template_data.budget_planned' => 'nullable|numeric|min:0',
                'template_data.priority' => ['nullable', Rule::in(Project::VALID_PRIORITIES)],
                'template_data.tags' => 'nullable|array',
                'template_data.tags.*' => 'string|max:50',
                'template_data.settings' => 'nullable|array',
                'milestones' => 'nullable|array',
                'milestones.*.name' => 'required|string|max:255',
                'milestones.*.description' => 'nullable|string|max:2000',
                'milestones.*.target_date' => 'nullable|date',
                'milestones.*.order' => 'nullable|integer|min:0',
                'is_public' => 'nullable|boolean'
            ]);
            
            DB::beginTransaction();
            
            $template->update($validated);
            
            DB::commit();
            
            Log::info('Project template updated successfully', [
                'template_id' => $template->id,
                'user_id' => $user->id,
                'changes' => array_keys($validated)
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Project template updated successfully',
                'data' => $template->fresh()
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to update project template', [
                'error' => $e->getMessage(),
                'template_id' => $id,
                'user_id' => Auth::id(),
                'data' => $request->all()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update project template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a project template
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $template = ProjectTemplate::forTenant($user->tenant_id)->find($id);
            
            if (!$template) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Project template not found'
                ], 404);
            }
            
            // Check permission
            if (!$user->hasPermission('project.write')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient permissions to delete project templates'
                ], 403);
            }
            
            DB::beginTransaction();
            
            $template->delete();
            
            DB::commit();
            
            Log::info('Project template deleted successfully', [
                'template_id' => $id,
                'user_id' => $user->id,
                'template_name' => $template->name
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Project template deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to delete project template', [
                'error' => $e->getMessage(),
                'template_id' => $id,
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete project template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new project from template
     */
    public function createProject(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $template = ProjectTemplate::forTenant($user->tenant_id)->find($id);
            
            if (!$template) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Project template not found'
                ], 404);
            }
            
            // Check permission
            if (!$user->hasPermission('project.write')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient permissions to create projects'
                ], 403);
            }
            
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:2000',
                'client_id' => 'nullable|string|exists:users,id',
                'pm_id' => 'nullable|string|exists:users,id',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after:start_date',
                'budget_planned' => 'nullable|numeric|min:0',
                'priority' => ['nullable', Rule::in(Project::VALID_PRIORITIES)],
                'tags' => 'nullable|array',
                'tags.*' => 'string|max:50',
                'settings' => 'nullable|array'
            ]);
            
            DB::beginTransaction();
            
            // Merge template data with provided data
            $projectData = array_merge($template->template_data, $validated);
            $projectData['tenant_id'] = $user->tenant_id;
            $projectData['status'] = Project::STATUS_DRAFT;
            $projectData['progress'] = 0;
            $projectData['budget_actual'] = 0;
            
            $project = Project::create($projectData);
            
            // Create milestones from template
            if (!empty($template->milestones)) {
                foreach ($template->milestones as $milestoneData) {
                    $project->milestones()->create([
                        'name' => $milestoneData['name'],
                        'description' => $milestoneData['description'] ?? null,
                        'target_date' => $milestoneData['target_date'] ?? null,
                        'order' => $milestoneData['order'] ?? 0,
                        'status' => 'pending',
                        'created_by' => $user->id
                    ]);
                }
            }
            
            // Add creator as team member
            $project->teamMembers()->attach($user->id, [
                'role' => 'project_manager',
                'joined_at' => now()
            ]);
            
            DB::commit();
            
            Log::info('Project created from template successfully', [
                'project_id' => $project->id,
                'template_id' => $template->id,
                'user_id' => $user->id,
                'project_name' => $project->name
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Project created from template successfully',
                'data' => $project->load(['client', 'projectManager', 'teamMembers', 'milestones'])
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to create project from template', [
                'error' => $e->getMessage(),
                'template_id' => $id,
                'user_id' => Auth::id(),
                'data' => $request->all()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create project from template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Duplicate a project template
     */
    public function duplicate(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $template = ProjectTemplate::forTenant($user->tenant_id)->find($id);
            
            if (!$template) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Project template not found'
                ], 404);
            }
            
            // Check permission
            if (!$user->hasPermission('project.write')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient permissions to duplicate project templates'
                ], 403);
            }
            
            $validated = $request->validate([
                'name' => 'required|string|max:255'
            ]);
            
            DB::beginTransaction();
            
            $newTemplate = ProjectTemplate::create([
                'tenant_id' => $user->tenant_id,
                'name' => $validated['name'],
                'description' => $template->description,
                'category' => $template->category,
                'template_data' => $template->template_data,
                'milestones' => $template->milestones,
                'is_public' => false,
                'created_by' => $user->id
            ]);
            
            DB::commit();
            
            Log::info('Project template duplicated successfully', [
                'original_template_id' => $template->id,
                'new_template_id' => $newTemplate->id,
                'user_id' => $user->id,
                'template_name' => $newTemplate->name
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Project template duplicated successfully',
                'data' => $newTemplate
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to duplicate project template', [
                'error' => $e->getMessage(),
                'template_id' => $id,
                'user_id' => Auth::id(),
                'data' => $request->all()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to duplicate project template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get template categories
     */
    public function categories(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $categories = ProjectTemplate::forTenant($user->tenant_id)
                ->select('category')
                ->distinct()
                ->pluck('category')
                ->filter()
                ->values();
            
            return response()->json([
                'status' => 'success',
                'data' => $categories
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get template categories', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve template categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}