<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ZenaContractResponseTrait;
use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    use ZenaContractResponseTrait;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $query = Task::with([
                'project:id,name,status',
                'assignee:id,name,email',
                'creator:id,name,email',
                'tenant:id,name'
            ]);

            // Apply tenant filter
            if ($user->tenant_id) {
                $query->forTenant($user->tenant_id);
            }

            // Apply filters
            if ($request->filled('project_id')) {
                $query->byProject($request->input('project_id'));
            }

            if ($request->filled('status')) {
                $query->byStatus($request->input('status'));
            }

            if ($request->filled('priority')) {
                $query->byPriority($request->input('priority'));
            }

            if ($request->filled('assignee_id')) {
                $query->byAssignee($request->input('assignee_id'));
            }

            if ($request->filled('watcher_id')) {
                $query->byWatcher($request->input('watcher_id'));
            }

            if ($request->filled('overdue')) {
                $query->overdue();
            }

            if ($request->filled('due_soon')) {
                $query->dueSoon($request->input('due_soon_days', 3));
            }

            if ($request->filled('search')) {
                $query->search($request->input('search'));
            }

            // Pagination
            $perPage = min($request->input('per_page', 15), 100);
            $tasks = $query->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return $this->zenaSuccessResponse($tasks, 'Tasks retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Task index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tasks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $request->validate([
                'project_id' => ['required', 'string', 'exists:projects,id'],
                'name' => ['required', 'string', 'max:255'],
                'title' => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:2000'],
                'status' => ['sometimes', Rule::in(Task::VALID_STATUSES)],
                'priority' => ['sometimes', Rule::in(Task::VALID_PRIORITIES)],
                'assignee_id' => ['nullable', 'string', 'exists:users,id'],
                'start_date' => ['nullable', 'date'],
                'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
                'estimated_hours' => ['nullable', 'numeric', 'min:0'],
                'actual_hours' => ['nullable', 'numeric', 'min:0'],
                'spent_hours' => ['nullable', 'numeric', 'min:0'],
                'parent_id' => ['nullable', 'string', 'exists:tasks,id'],
                'order' => ['nullable', 'integer', 'min:0'],
                'dependencies' => ['nullable', 'array'],
                'dependencies.*' => ['string', 'exists:tasks,id'],
                'watchers' => ['nullable', 'array'],
                'watchers.*' => ['string', 'exists:users,id'],
                'tags' => ['nullable', 'array'],
                'tags.*' => ['string', 'max:50'],
                'is_hidden' => ['nullable', 'boolean'],
                'visibility' => ['nullable', 'string', 'in:public,private,team'],
                'client_approved' => ['nullable', 'boolean'],
            ]);

            // Check for circular dependency
            $dependencies = $request->input('dependencies', []);
            if (!empty($dependencies)) {
                foreach ($dependencies as $depId) {
                    if ($depId === $request->input('parent_id')) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Task cannot depend on its parent task'
                        ], 400);
                    }
                }
            }

            DB::beginTransaction();

            $task = Task::create([
                'tenant_id' => $user->tenant_id,
                'project_id' => $request->input('project_id'),
                'name' => $request->input('name'),
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'status' => $request->input('status', Task::STATUS_TODO),
                'priority' => $request->input('priority', Task::PRIORITY_MEDIUM),
                'assignee_id' => $request->input('assignee_id'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'estimated_hours' => $request->input('estimated_hours'),
                'actual_hours' => $request->input('actual_hours'),
                'spent_hours' => $request->input('spent_hours'),
                'parent_id' => $request->input('parent_id'),
                'order' => $request->input('order', 0),
                'dependencies' => $dependencies,
                'watchers' => $request->input('watchers', []),
                'tags' => $request->input('tags', []),
                'is_hidden' => $request->input('is_hidden', false),
                'visibility' => $request->input('visibility', 'team'),
                'client_approved' => $request->input('client_approved', false),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Add watchers if provided
            if ($request->filled('watchers')) {
                $task->watchers()->sync($request->input('watchers'));
            }

            // Add dependencies if provided
            if ($request->filled('dependencies')) {
                $task->dependencies()->sync($request->input('dependencies'));
            }

            DB::commit();

            return $this->zenaSuccessResponse(
                $task->load(['project', 'assignee', 'creator', 'tenant']),
                'Task created successfully',
                201
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Task store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create task',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $task = Task::with(['project', 'component', 'assignments.user', 'dependencies'])
            ->find($id);

        if (!$task) {
            return $this->error('Task not found', 404);
        }

        return $this->zenaSuccessResponse($task);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $task = Task::find($id);

        if (!$task) {
            return $this->error('Task not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|in:todo,in_progress,done,pending',
            'priority' => 'sometimes|required|in:low,medium,high,urgent',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'estimated_hours' => 'nullable|numeric|min:0',
            'actual_hours' => 'nullable|numeric|min:0',
            'dependencies' => 'nullable|array',
            'dependencies.*' => 'exists:tasks,id',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        try {
            $updateData = $request->only([
                'name', 'description', 'status', 'priority', 'start_date', 'end_date',
                'estimated_hours', 'actual_hours', 'dependencies'
            ]);

            // Check for circular dependency if updating dependencies
            if (isset($updateData['dependencies']) && is_array($updateData['dependencies'])) {
                foreach ($updateData['dependencies'] as $depId) {
                    if ($depId === $id) {
                        return $this->error('Task cannot depend on itself', 400);
                    }
                    if ($this->wouldCreateCircularDependency($id, $depId, $task->project_id)) {
                        return $this->error('Updating dependencies would create a circular dependency', 400);
                    }
                }
            }

            $task->update($updateData);

            return $this->zenaSuccessResponse(
                $task->load(['project', 'component', 'assignments.user']),
                'Task updated successfully'
            );

        } catch (\Exception $e) {
            return $this->error('Failed to update task: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $task = Task::find($id);

        if (!$task) {
            return $this->error('Task not found', 404);
        }

        try {
            $task->delete();

            return $this->zenaSuccessResponse(null, 'Task deleted successfully');

        } catch (\Exception $e) {
            return $this->error('Failed to delete task: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update task status
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:todo,in_progress,done,pending',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $task = Task::find($id);

        if (!$task) {
            return $this->error('Task not found', 404);
        }

        try {
            $task->update(['status' => $request->input('status')]);

            return $this->zenaSuccessResponse(
                $task->load(['project', 'component', 'assignments.user']),
                'Task status updated successfully'
            );

        } catch (\Exception $e) {
            return $this->error('Failed to update task status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get task dependencies
     */
    public function getDependencies(string $id): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $task = Task::with(['dependencies'])->find($id);

        if (!$task) {
            return $this->error('Task not found', 404);
        }

        return $this->zenaSuccessResponse($task->dependencies);
    }

    /**
     * Assign user to task
     */
    public function assignUser(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $request->validate([
                'user_id' => ['required', 'string', 'exists:users,id'],
                'role' => ['nullable', 'string', 'in:assignee,reviewer,watcher'],
                'assigned_hours' => ['nullable', 'numeric', 'min:0'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ]);

            $task = Task::findOrFail($id);

            // Check if user is already assigned
            if ($task->assignee_id === $request->input('user_id')) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is already assigned to this task'
                ], 400);
            }

            $task->update([
                'assignee_id' => $request->input('user_id'),
                'updated_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'data' => $task->load(['assignee', 'project']),
                'message' => 'User assigned to task successfully'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Task assign user error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign user to task',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign task to team
     */
    public function assignTeam(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $request->validate([
                'team_id' => ['required', 'string', 'exists:teams,id'],
                'role' => ['nullable', 'string', 'in:assignee,reviewer,watcher'],
                'assigned_hours' => ['nullable', 'numeric', 'min:0'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ]);

            $task = Task::findOrFail($id);

            // Check if team is already assigned
            $existingAssignment = TaskAssignment::where('task_id', $id)
                ->where('team_id', $request->input('team_id'))
                ->where('assignment_type', TaskAssignment::TYPE_TEAM)
                ->first();

            if ($existingAssignment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team is already assigned to this task'
                ], 400);
            }

            $assignment = TaskAssignment::create([
                'task_id' => $id,
                'team_id' => $request->input('team_id'),
                'assignment_type' => TaskAssignment::TYPE_TEAM,
                'role' => $request->input('role', TaskAssignment::ROLE_ASSIGNEE),
                'assigned_hours' => $request->input('assigned_hours'),
                'status' => TaskAssignment::STATUS_ASSIGNED,
                'assigned_at' => now(),
                'notes' => $request->input('notes'),
                'created_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'data' => $assignment->load(['team', 'task']),
                'message' => 'Team assigned to task successfully'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Task assign team error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign team to task',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update task dependencies
     */
    public function updateDependencies(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $request->validate([
                'dependencies' => ['required', 'array'],
                'dependencies.*' => ['string', 'exists:tasks,id'],
            ]);

            $task = Task::findOrFail($id);
            $dependencies = $request->input('dependencies', []);

            // Check for circular dependencies
            foreach ($dependencies as $depId) {
                if ($depId === $task->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Task cannot depend on itself'
                    ], 400);
                }

                if ($task->hasCircularDependency($depId)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Circular dependency detected'
                    ], 400);
                }
            }

            $task->dependencies()->sync($dependencies);
            $task->update(['updated_by' => $user->id]);

            return response()->json([
                'success' => true,
                'data' => $task->load(['dependencies']),
                'message' => 'Task dependencies updated successfully'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Task update dependencies error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update task dependencies',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get task watchers
     */
    public function getWatchers(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $task = Task::findOrFail($id);
            $watchers = $task->watchers()->select('id', 'name', 'email')->get();

            return response()->json([
                'success' => true,
                'data' => $watchers,
                'message' => 'Task watchers retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Task get watchers error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve task watchers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add watcher to task
     */
    public function addWatcher(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $request->validate([
                'user_id' => ['required', 'string', 'exists:users,id'],
            ]);

            $task = Task::findOrFail($id);
            $userId = $request->input('user_id');

            if ($task->addWatcher($userId)) {
                $task->update(['updated_by' => $user->id]);
                
                return response()->json([
                    'success' => true,
                    'data' => $task->load(['watchers']),
                    'message' => 'Watcher added to task successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'User is already watching this task'
                ], 400);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Task add watcher error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to add watcher to task',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove watcher from task
     */
    public function removeWatcher(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $request->validate([
                'user_id' => ['required', 'string', 'exists:users,id'],
            ]);

            $task = Task::findOrFail($id);
            $userId = $request->input('user_id');

            if ($task->removeWatcher($userId)) {
                $task->update(['updated_by' => $user->id]);
                
                return response()->json([
                    'success' => true,
                    'data' => $task->load(['watchers']),
                    'message' => 'Watcher removed from task successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not watching this task'
                ], 400);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Task remove watcher error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove watcher from task',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get task statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $query = Task::query();
            
            // Apply tenant filter
            if ($user->tenant_id) {
                $query->forTenant($user->tenant_id);
            }

            // Apply project filter if provided
            if ($request->filled('project_id')) {
                $query->byProject($request->input('project_id'));
            }

            $stats = [
                'total' => $query->count(),
                'by_status' => $query->selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status'),
                'by_priority' => $query->selectRaw('priority, COUNT(*) as count')
                    ->groupBy('priority')
                    ->pluck('count', 'priority'),
                'overdue' => $query->overdue()->count(),
                'due_soon' => $query->dueSoon()->count(),
                'completed_this_week' => $query->where('status', Task::STATUS_DONE)
                    ->where('updated_at', '>=', now()->subWeek())
                    ->count(),
                'avg_completion_time' => $query->where('status', Task::STATUS_DONE)
                    ->whereNotNull('start_date')
                    ->whereNotNull('end_date')
                    ->selectRaw('AVG(DATEDIFF(end_date, start_date)) as avg_days')
                    ->value('avg_days'),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Task statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Task statistics error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve task statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add task dependency
     */
    public function addDependency(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $request->validate([
                'dependency_id' => ['required', 'string', 'exists:tasks,id'],
            ]);

            $task = Task::findOrFail($id);
            $dependencyId = $request->input('dependency_id');

            // Check for self-dependency
            if ($dependencyId === $id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task cannot depend on itself'
                ], 400);
            }

            // Check for circular dependency
            if ($task->hasCircularDependency($dependencyId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Circular dependency detected'
                ], 400);
            }

            if ($task->addDependency($dependencyId)) {
                $task->update(['updated_by' => $user->id]);
                
                return $this->zenaSuccessResponse(
                    $task->load(['dependencies']),
                    'Dependency added successfully'
                );
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Dependency already exists'
                ], 400);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Task add dependency error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to add dependency',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove task dependency
     */
    public function removeDependency(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $request->validate([
                'dependency_id' => ['required', 'string', 'exists:tasks,id'],
            ]);

            $task = Task::findOrFail($id);
            $dependencyId = $request->input('dependency_id');

            if ($task->removeDependency($dependencyId)) {
                $task->update(['updated_by' => $user->id]);
                
                return $this->zenaSuccessResponse(
                    $task->load(['dependencies']),
                    'Dependency removed successfully'
                );
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Dependency not found'
                ], 400);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Task remove dependency error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove dependency',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
