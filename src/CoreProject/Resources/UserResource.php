<?php declare(strict_types=1);

namespace Src\CoreProject\Resources;

use App\Http\Resources\BaseApiResource;

/**
 * User API Resource
 * Transform User model data for API responses
 */
class UserResource extends BaseApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->formatUlid($this->ulid),
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->formatDateTime($this->email_verified_at),
            'created_at' => $this->formatDateTime($this->created_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
            
            // Relationships
            'tenant' => $this->includeRelationship('tenant', TenantResource::class),
            'task_assignments' => $this->includeRelationship('taskAssignments', TaskAssignmentResource::class),
            'assigned_tasks' => $this->includeRelationship('assignedTasks', TaskResource::class),
            
            // Computed properties
            'is_verified' => $this->hasVerifiedEmail(),
            'active_tasks_count' => $this->getActiveTasksCount(),
        ];
    }
    
    /**
     * Get count of active tasks assigned to user
     */
    private function getActiveTasksCount(): int
    {
        // OWN-2026-024: User has no assignedTasks() relation, so this crashed every v1 response that embedded an assignment's
        // user (e.g. POST api/v1/tasks with assignments returned 500 after committing). Count the user's assigned tasks
        // through task_assignments instead.
        return \App\Models\Task::query()
            ->whereIn('id', \Illuminate\Support\Facades\DB::table('task_assignments')->select('task_id')->where('user_id', $this->resource->getKey()))
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();
    }
}