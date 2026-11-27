<?php declare(strict_types=1);

namespace Src\CoreProject\Requests;

use App\Http\Requests\BaseApiRequest;
use Src\CoreProject\Models\TaskAssignment;

class UpdateTaskAssignmentRequest extends BaseApiRequest
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
            'split_percent' => [
                'sometimes',
                'required',
                'numeric',
                'min:0.01',
                'max:100',
                'decimal:0,2'
            ],
            'role' => [
                'sometimes',
                'required',
                'string',
                'in:' . implode(',', $this->validRoles())
            ]
        ];
    }

    /**
     * Validation bổ sung sau khi validation cơ bản
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $assignmentId = $this->route('taskAssignment');
            $newSplitPercentage = $this->input('split_percent');
            
            // OWN-2026-024 SPLIT-PERCENT: the 100 % check moved out of pre-controller validation (it ran before the O-13 authority
            // check and leaked another task's allocation); the controller enforces it after authority, under the task-row lock.

            // Cảnh báo khi thay đổi role của primary assignee
            if ($this->has('role') && $assignmentId) {
                $assignment = TaskAssignment::find($assignmentId);
                if (
                    $assignment
                    && method_exists($assignment, 'isPrimaryAssignee')
                    && $assignment->isPrimaryAssignee()
                    && $this->input('role') !== 'assignee'
                    && method_exists($validator, 'warnings')
                ) {
                    $validator->warnings()->add(
                        'role',
                        'Bạn đang thay đổi vai trò của assignee chính. ' .
                        'Điều này có thể ảnh hưởng đến quyền chỉnh sửa task.'
                    );
                }
            }
        });
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
