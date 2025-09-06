<?php declare(strict_types=1);

namespace Src\CoreProject\Requests;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;
use Src\CoreProject\Models\TaskAssignment;
use Src\CoreProject\Models\Task;

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
            'task_id' => [
                'required',
                'integer',
                'exists:tasks,id'
            ],
            'user_id' => [
                'required',
                'integer',
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
            $taskId = $this->input('task_id');
            $userId = $this->input('user_id');
            $splitPercentage = $this->input('split_percent', 0);
            
            // Kiểm tra user đã được assign cho task này chưa
            $existingAssignment = TaskAssignment::where('task_id', $taskId)
                ->where('user_id', $userId)
                ->first();
                
            if ($existingAssignment) {
                $validator->errors()->add(
                    'user_id',
                    'User đã được phân công cho task này.'
                );
            }
            
            // Kiểm tra tổng phần trăm không vượt quá 100%
            if ($taskId) {
                $currentTotal = TaskAssignment::where('task_id', $taskId)
                    ->sum('split_percent');
                    
                if (($currentTotal + $splitPercentage) > 100) {
                    $validator->errors()->add(
                        'split_percent',
                        'Tổng phần trăm phân chia cho task này sẽ vượt quá 100%. ' .
                        'Hiện tại: ' . $currentTotal . '%, thêm: ' . $splitPercentage . '%'
                    );
                }
            }
            
            // Kiểm tra user có quyền truy cập project của task không
            if ($taskId && $userId) {
                $task = Task::find($taskId);
                if ($task) {
                    // Có thể thêm logic kiểm tra quyền truy cập project
                    // Ví dụ: kiểm tra user có trong project team không
                }
            }
        });
    }
}