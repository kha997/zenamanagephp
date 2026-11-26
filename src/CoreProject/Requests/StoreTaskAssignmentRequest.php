<?php declare(strict_types=1);

namespace Src\CoreProject\Requests;

use App\Http\Requests\BaseApiRequest;
use Src\CoreProject\Models\TaskAssignment;

class StoreTaskAssignmentRequest extends BaseApiRequest
{
    /**
     * Xác định user có quyền thực hiện request này không
     */
    public function authorize(): bool
    {
        return true; // RBAC middleware sẽ xử lý authorization
    }

    /**
     * Các quy tắc validation
     */
    public function rules(): array
    {
        return [
            // UAT-RECOVERY-04b: primary keys are ULID strings (the former `integer` rule rejected every real id).
            'task_id' => [
                'required',
                'string',
                'exists:tasks,id'
            ],
            'user_id' => [
                'required',
                'string',
                'exists:users,id'
            ],
            'split_percent' => [
                'required',
                'numeric',
                'min:0.01',
                'max:100',
                'decimal:0,2'
            ],
            'role' => [
                'required',
                'string',
                'in:' . implode(',', $this->validRoles())
            ]
        ];
    }

    /**
     * OWN-2026-024 SPLIT-PERCENT: the duplicate and 100 % checks were removed from this pre-controller validation. They ran
     * BEFORE the controller's O-13 authority check and were not tenant-scoped, so any task.assign holder could read another
     * task's allocation or assignment state through 422 messages. The controller performs both checks after authority, under
     * the task-row lock (TaskAssignmentShares).
     */
    public function withValidator($validator)
    {
    }

    private function validRoles(): array
    {
        if (defined(TaskAssignment::class . '::ROLES')) {
            /** @var array<string, mixed> $roles */
            $roles = TaskAssignment::ROLES;

            return array_keys($roles);
        }

        return ['assignee', 'reviewer', 'watcher'];
    }
}
