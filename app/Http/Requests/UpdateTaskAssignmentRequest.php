<?php declare(strict_types=1);

namespace App\Http\Requests;

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
                'in:' . implode(',', array_keys(TaskAssignment::ROLES))
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
            
            // Kiểm tra tổng phần trăm không vượt quá 100% khi cập nhật
            if ($assignmentId && $newSplitPercentage !== null) {
                $assignment = TaskAssignment::find($assignmentId);
                if ($assignment) {
                    $currentTotal = TaskAssignment::where('task_id', $assignment->task_id)
                        ->where('id', '!=', $assignmentId)
                        ->sum('split_percent');
                        
                    if (($currentTotal + $newSplitPercentage) > 100) {
                        $validator->errors()->add(
                            'split_percent',
                            'Tổng phần trăm phân chia cho task này sẽ vượt quá 100%. ' .
                            'Hiện tại (không tính assignment này): ' . $currentTotal . '%, ' .
                            'cập nhật thành: ' . $newSplitPercentage . '%'
                        );
                    }
                }
            }
            
            // Cảnh báo khi thay đổi role của primary assignee
            if ($this->has('role') && $assignmentId) {
                $assignment = TaskAssignment::find($assignmentId);
                if ($assignment && $assignment->isPrimaryAssignee() && 
                    $this->input('role') !== 'assignee') {
                    $validator->warnings()->add(
                        'role',
                        'Bạn đang thay đổi vai trò của assignee chính. ' .
                        'Điều này có thể ảnh hưởng đến quyền chỉnh sửa task.'
                    );
                }
            }
        });
    }
}