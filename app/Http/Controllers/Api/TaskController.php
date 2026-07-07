<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ZenaContractResponseTrait;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\EventRecord;
use App\Models\Notification;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Services\ZenaAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TaskController extends BaseApiController
{
    use ZenaContractResponseTrait;

    private const CAPA_CONTEXT_TAG = 'inspection-ncr-capa';
    private const ESCALATION_ACTION = 'zena.task.escalate_overdue';
    private const ESCALATION_EVENT_KEY = 'zena.task.overdue_escalated';
    private const ESCALATION_NOTIFICATION_TYPE = 'task_overdue_escalated';

    public function __construct(private ZenaAuditLogger $auditLogger)
    {
    }

    private function tenantId(Request $request): string
    {
        $authTenantId = data_get(Auth::user(), 'tenant_id');
        $tenantId = $request->attributes->get('tenant_id')
            ?? app('current_tenant_id')
            ?? $authTenantId;

        return $tenantId ? (string) $tenantId : '';
    }

    protected function error(string $message, int $statusCode = 400, $data = null): JsonResponse
    {
        return $this->errorResponse($message, $statusCode, $data);
    }

    private function taskForTenant(Request $request, string $id, array $relations = []): ?Task
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return null;
        }

        $query = Task::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($id);

        if ($relations !== []) {
            $query->with($relations);
        }

        return $query->first();
    }

    private function isInspectionNcrCapaTask(Task $task): bool
    {
        return in_array(self::CAPA_CONTEXT_TAG, (array) $task->tags, true);
    }

    private function hasTerminalStatus(Task $task): bool
    {
        return in_array((string) $task->status, [
            Task::STATUS_COMPLETED,
            Task::STATUS_CANCELLED,
            'done',
        ], true);
    }

    private function createOverdueEscalationNotification(Task $task, string $recipientUserId): Notification
    {
        $title = (string) ($task->title ?? $task->name ?? 'Overdue CAPA task');
        $dueDate = $task->end_date?->toDateString();
        $linkUrl = route('api.zena.tasks.show', ['id' => (string) $task->id], false);

        return Notification::create([
            'tenant_id' => (string) $task->tenant_id,
            'user_id' => $recipientUserId,
            'type' => self::ESCALATION_NOTIFICATION_TYPE,
            'priority' => Notification::PRIORITY_NORMAL,
            'title' => 'Overdue CAPA escalation',
            'body' => trim(sprintf(
                'CAPA task "%s" is overdue%s.',
                $title,
                $dueDate ? ' since ' . $dueDate : ''
            )),
            'channel' => Notification::CHANNEL_INAPP,
            'event_key' => self::ESCALATION_EVENT_KEY,
            'project_id' => $task->project_id,
            'link_url' => $linkUrl,
            'data' => [
                'task_id' => (string) $task->id,
                'task_status' => (string) $task->status,
                'overdue_basis' => 'end_date',
                'context_tag' => self::CAPA_CONTEXT_TAG,
            ],
        ]);
    }

    private function createOverdueEscalationEventRecord(Task $task, Notification $notification, string $actorUserId): EventRecord
    {
        return EventRecord::create([
            'tenant_id' => (string) $task->tenant_id,
            'project_id' => $task->project_id ? (string) $task->project_id : null,
            'aggregate_type' => 'task',
            'aggregate_id' => (string) $task->id,
            'event_key' => self::ESCALATION_EVENT_KEY,
            'actor_user_id' => $actorUserId,
            'occurred_at' => now(),
            'payload' => [
                'notification' => [
                    'type' => self::ESCALATION_NOTIFICATION_TYPE,
                    'channel' => Notification::CHANNEL_INAPP,
                    'recipient_user_id' => (string) $notification->user_id,
                ],
                'task' => [
                    'id' => (string) $task->id,
                    'status' => (string) $task->status,
                ],
                'context' => [
                    'overdue_basis' => 'end_date',
                    'context_tag' => self::CAPA_CONTEXT_TAG,
                    'link_url' => (string) $notification->link_url,
                ],
                'actor' => [
                    'user_id' => $actorUserId,
                    'action' => self::ESCALATION_ACTION,
                ],
            ],
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, ?string $projectId = null): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
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
            $effectiveProjectId = $projectId ?: $request->input('project_id');
            if (!empty($effectiveProjectId)) {
                $query->where('project_id', $effectiveProjectId);
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
            return $this->error('Failed to retrieve tasks', 500);
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
                return $this->error('Unauthorized', 401);
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
                        return $this->error('Task cannot depend on its parent task', 400);
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
                'dependencies_json' => $dependencies,
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
            return $this->failResponse($e->errors(), 'Validation failed', 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Task store error: ' . $e->getMessage());
            return $this->error('Failed to create task', 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->error('Tenant context missing', 400);
        }

        $task = Task::with(['project', 'component', 'assignments.user', 'dependencies'])
            ->where('tenant_id', $tenantId)
            ->whereKey($id)
            ->first();

        if (!$task) {
            return $this->error('Task not found', 404);
        }

        return $this->zenaSuccessResponse($task);
    }

    public function escalateOverdue(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->error('Tenant context missing', 400);
        }

        $task = $this->taskForTenant($request, $id, ['assignee:id,name,email']);

        if (!$task) {
            return $this->error('Task not found', 404);
        }

        if (!$this->isInspectionNcrCapaTask($task)) {
            return $this->error('Task is not eligible for overdue CAPA escalation', 422, [
                'task_id' => (string) $task->id,
                'required_context_tag' => self::CAPA_CONTEXT_TAG,
            ]);
        }

        if ($this->hasTerminalStatus($task)) {
            return $this->error('Completed CAPA task cannot be escalated', 422, [
                'task_id' => (string) $task->id,
                'status' => (string) $task->status,
            ]);
        }

        if ($task->end_date === null || !$task->end_date->isPast()) {
            return $this->error('Task is not overdue for escalation', 422, [
                'task_id' => (string) $task->id,
                'overdue_basis' => 'end_date',
                'end_date' => $task->end_date?->toISOString(),
            ]);
        }

        if (!$task->assigned_to) {
            return $this->error('Overdue CAPA task has no assignee for escalation', 422, [
                'task_id' => (string) $task->id,
                'recipient_basis' => 'assigned_to',
            ]);
        }

        try {
            $result = DB::transaction(function () use ($task, $user): array {
                $notification = $this->createOverdueEscalationNotification($task, (string) $task->assigned_to);
                $eventRecord = $this->createOverdueEscalationEventRecord($task, $notification, (string) $user->id);

                return [
                    'notification' => $notification,
                    'event_record' => $eventRecord,
                ];
            });

            /** @var Notification $notification */
            $notification = $result['notification'];

            $this->auditLogger->log(
                $request,
                self::ESCALATION_ACTION,
                'task',
                (string) $task->id,
                200,
                (string) $task->project_id,
                (string) $task->tenant_id,
                (string) $user->id
            );

            return $this->zenaSuccessResponse([
                'task_id' => (string) $task->id,
                'notification_id' => (string) $notification->id,
                'event_record_id' => (string) $result['event_record']->id,
                'recipient_user_id' => (string) $task->assigned_to,
                'overdue_basis' => 'end_date',
                'context_tag' => self::CAPA_CONTEXT_TAG,
            ], 'Overdue CAPA escalation notification sent successfully');
        } catch (\Exception $e) {
            Log::error('Task overdue escalation error: ' . $e->getMessage());

            return $this->error('Failed to escalate overdue CAPA task', 500);
        }
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

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->error('Tenant context missing', 400);
        }

        $task = Task::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($id)
            ->first();

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
            $requestedDependencies = $updateData['dependencies'] ?? null;

            // Check for circular dependency if updating dependencies
            if (is_array($requestedDependencies)) {
                foreach ($requestedDependencies as $depId) {
                    if ($depId === $id) {
                        return $this->error('Task cannot depend on itself', 400);
                    }
                    if ($this->wouldCreateCircularDependency($id, $depId, $task->project_id)) {
                        return $this->error('Updating dependencies would create a circular dependency', 400);
                    }
                }
            }

            if (array_key_exists('dependencies', $updateData)) {
                $updateData['dependencies_json'] = $updateData['dependencies'];
                unset($updateData['dependencies']);
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
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->error('Tenant context missing', 400);
        }

        $task = Task::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($id)
            ->first();

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

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->error('Tenant context missing', 400);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:todo,in_progress,done,pending',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $task = Task::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($id)
            ->first();

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
    public function getDependencies(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->error('Tenant context missing', 400);
        }

        $task = Task::with(['dependencies'])
            ->where('tenant_id', $tenantId)
            ->whereKey($id)
            ->first();

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
                return $this->error('Unauthorized', 401);
            }

            $tenantId = $this->tenantId($request);
            if ($tenantId === '') {
                return $this->error('Tenant context missing', 400);
            }

            $request->validate([
                'user_id' => ['required', 'string', 'exists:users,id'],
                'role' => ['nullable', 'string', 'in:assignee,reviewer,watcher'],
                'assigned_hours' => ['nullable', 'numeric', 'min:0'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ]);

            $task = Task::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($id)
                ->first();

            if (!$task) {
                return $this->error('Task not found', 404);
            }

            // Check if user is already assigned
            if ($task->assignee_id === $request->input('user_id')) {
                return $this->error('User is already assigned to this task', 400);
            }

            $task->update([
                'assignee_id' => $request->input('user_id'),
                'updated_by' => $user->id,
            ]);

            return $this->zenaSuccessResponse($task->load(['assignee', 'project']), 'User assigned to task successfully');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->failResponse($e->errors(), 'Validation failed', 422);
        } catch (\Exception $e) {
            Log::error('Task assign user error: ' . $e->getMessage());
            return $this->error('Failed to assign user to task', 500);
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
                return $this->error('Unauthorized', 401);
            }

            $tenantId = $this->tenantId($request);
            if ($tenantId === '') {
                return $this->error('Tenant context missing', 400);
            }

            $request->validate([
                'team_id' => ['required', 'string', 'exists:teams,id'],
                'role' => ['nullable', 'string', 'in:assignee,reviewer,watcher'],
                'assigned_hours' => ['nullable', 'numeric', 'min:0'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ]);

            $task = Task::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($id)
                ->first();

            if (!$task) {
                return $this->error('Task not found', 404);
            }

            // Check if team is already assigned
            $existingAssignment = TaskAssignment::where('task_id', $id)
                ->where('team_id', $request->input('team_id'))
                ->where('assignment_type', TaskAssignment::TYPE_TEAM)
                ->first();

            if ($existingAssignment) {
                return $this->error('Team is already assigned to this task', 400);
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

            return $this->zenaSuccessResponse($assignment->load(['team', 'task']), 'Team assigned to task successfully');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->failResponse($e->errors(), 'Validation failed', 422);
        } catch (\Exception $e) {
            Log::error('Task assign team error: ' . $e->getMessage());
            return $this->error('Failed to assign team to task', 500);
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
                return $this->error('Unauthorized', 401);
            }

            $tenantId = $this->tenantId($request);
            if ($tenantId === '') {
                return $this->error('Tenant context missing', 400);
            }

            $request->validate([
                'dependencies' => ['required', 'array'],
                'dependencies.*' => ['string', 'exists:tasks,id'],
            ]);

            $task = Task::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($id)
                ->first();
            if (!$task) {
                return $this->error('Task not found', 404);
            }
            $dependencies = $request->input('dependencies', []);

            foreach ($dependencies as $depId) {
                $dependencyTask = Task::query()
                    ->where('tenant_id', $tenantId)
                    ->whereKey($depId)
                    ->first();
                if (!$dependencyTask) {
                    return $this->error('Task not found', 404);
                }
            }

            // Check for circular dependencies
            foreach ($dependencies as $depId) {
                if ($depId === $task->id) {
                    return $this->error('Task cannot depend on itself', 400);
                }

                if ($task->hasCircularDependency($depId)) {
                    return $this->error('Circular dependency detected', 400);
                }
            }

            $task->dependencies()->sync($dependencies);
            $task->update(['updated_by' => $user->id]);

            return $this->zenaSuccessResponse($task->load(['dependencies']), 'Task dependencies updated successfully');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->failResponse($e->errors(), 'Validation failed', 422);
        } catch (\Exception $e) {
            Log::error('Task update dependencies error: ' . $e->getMessage());
            return $this->error('Failed to update task dependencies', 500);
        }
    }

    /**
     * Get task watchers
     */
    public function getWatchers(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $tenantId = $this->tenantId($request);
            if ($tenantId === '') {
                return $this->error('Tenant context missing', 400);
            }

            $task = Task::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($id)
                ->first();
            if (!$task) {
                return $this->error('Task not found', 404);
            }
            $watchers = $task->watchers()->select('id', 'name', 'email')->get();

            return $this->zenaSuccessResponse($watchers, 'Task watchers retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Task get watchers error: ' . $e->getMessage());
            return $this->error('Failed to retrieve task watchers', 500);
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
                return $this->error('Unauthorized', 401);
            }

            $tenantId = $this->tenantId($request);
            if ($tenantId === '') {
                return $this->error('Tenant context missing', 400);
            }

            $request->validate([
                'user_id' => ['required', 'string', 'exists:users,id'],
            ]);

            $task = Task::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($id)
                ->first();
            if (!$task) {
                return $this->error('Task not found', 404);
            }
            $userId = $request->input('user_id');

            if ($task->addWatcher($userId)) {
                $task->update(['updated_by' => $user->id]);

                return $this->zenaSuccessResponse($task->load(['watchers']), 'Watcher added to task successfully');
            } else {
                return $this->error('User is already watching this task', 400);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->failResponse($e->errors(), 'Validation failed', 422);
        } catch (\Exception $e) {
            Log::error('Task add watcher error: ' . $e->getMessage());
            return $this->error('Failed to add watcher to task', 500);
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
                return $this->error('Unauthorized', 401);
            }

            $tenantId = $this->tenantId($request);
            if ($tenantId === '') {
                return $this->error('Tenant context missing', 400);
            }

            $request->validate([
                'user_id' => ['required', 'string', 'exists:users,id'],
            ]);

            $task = Task::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($id)
                ->first();
            if (!$task) {
                return $this->error('Task not found', 404);
            }
            $userId = $request->input('user_id');

            if ($task->removeWatcher($userId)) {
                $task->update(['updated_by' => $user->id]);

                return $this->zenaSuccessResponse($task->load(['watchers']), 'Watcher removed from task successfully');
            } else {
                return $this->error('User is not watching this task', 400);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->failResponse($e->errors(), 'Validation failed', 422);
        } catch (\Exception $e) {
            Log::error('Task remove watcher error: ' . $e->getMessage());
            return $this->error('Failed to remove watcher from task', 500);
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
                return $this->error('Unauthorized', 401);
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

            return $this->zenaSuccessResponse($stats, 'Task statistics retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Task statistics error: ' . $e->getMessage());
            return $this->error('Failed to retrieve task statistics', 500);
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
                return $this->error('Unauthorized', 401);
            }

            $tenantId = $this->tenantId($request);
            if ($tenantId === '') {
                return $this->error('Tenant context missing', 400);
            }

            $request->validate([
                'dependency_id' => ['required', 'string', 'exists:tasks,id'],
            ]);

            $task = Task::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($id)
                ->first();
            if (!$task) {
                return $this->error('Task not found', 404);
            }
            $dependencyId = $request->input('dependency_id');

            $dependencyTask = Task::query()
                ->where('tenant_id', $tenantId)
                ->whereKey((string) $dependencyId)
                ->first();
            if (!$dependencyTask) {
                return $this->error('Task not found', 404);
            }

            // Check for self-dependency
            if ($dependencyId === $id) {
                return $this->error('Task cannot depend on itself', 400);
            }

            // Check for circular dependency
            if ($task->hasCircularDependency($dependencyId)) {
                return $this->error('Circular dependency detected', 400);
            }

            if ($task->addDependency($dependencyId)) {
                $task->update(['updated_by' => $user->id]);

                return $this->zenaSuccessResponse(
                    $task->load(['dependencies']),
                    'Dependency added successfully'
                );
            } else {
                return $this->error('Dependency already exists', 400);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->failResponse($e->errors(), 'Validation failed', 422);
        } catch (\Exception $e) {
            Log::error('Task add dependency error: ' . $e->getMessage());
            return $this->error('Failed to add dependency', 500);
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
                return $this->error('Unauthorized', 401);
            }

            $tenantId = $this->tenantId($request);
            if ($tenantId === '') {
                return $this->error('Tenant context missing', 400);
            }

            $request->validate([
                'dependency_id' => ['required', 'string', 'exists:tasks,id'],
            ]);

            $task = Task::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($id)
                ->first();
            if (!$task) {
                return $this->error('Task not found', 404);
            }
            $dependencyId = $request->input('dependency_id') ?? $request->route('dependencyId');

            $dependencyTask = Task::query()
                ->where('tenant_id', $tenantId)
                ->whereKey((string) $dependencyId)
                ->first();
            if (!$dependencyTask) {
                return $this->error('Task not found', 404);
            }

            if ($task->removeDependency($dependencyId)) {
                $task->update(['updated_by' => $user->id]);

                return $this->zenaSuccessResponse(
                    $task->load(['dependencies']),
                    'Dependency removed successfully'
                );
            } else {
                return $this->error('Dependency not found', 400);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->failResponse($e->errors(), 'Validation failed', 422);
        } catch (\Exception $e) {
            Log::error('Task remove dependency error: ' . $e->getMessage());
            return $this->error('Failed to remove dependency', 500);
        }
    }

}
