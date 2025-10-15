<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Src\CoreProject\Models\Task;
use Src\Shared\Requests\BaseApiRequest;

class UpdateTaskRequest extends BaseApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $taskId = $this->route('task');
        $task = Task::find($taskId);
        
        return [
            'component_id' => [
                'nullable',
                'integer',
                'exists:components,id',
                function ($attribute, $value, $fail) 
                        if ($component && $component->project_id !== $task->project_id) {
                            $fail('Component phải thuộc cùng dự án với task.');
                        }
                    }
                }
            ],
            'phase_id' => [
                'nullable',
                'integer'
            ],
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('tasks')
                    ->ignore($taskId)
                    ->where('project_id', $task ? $task->project_id : null)
            ],
            'description' => [
                'nullable',
                'string',
                'max:2000'
            ],
            'start_date' => [
                'nullable',
                'date'
            ],
            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date'
            ],
            'status' => [
                'nullable',
                'string',
                Rule::in(array_keys(Task::STATUSES))
            ],
            'priority' => [
                'nullable',
                'string',
                Rule::in(array_keys(Task::PRIORITIES))
            ],
            'dependencies' => [
                'nullable',
                'array'
            ],
            'dependencies.*' => [
                'integer',
                'exists:tasks,id',
                'not_in:' . $taskId, // Không thể phụ thuộc vào chính nó
                function ($attribute, $value, $fail) 
                        if ($dependentTask && $dependentTask->project_id !== $task->project_id) {
                            $fail('Task phụ thuộc phải thuộc cùng dự án.');
                        }
                    }
                }
            ],
            'conditional_tag' => [
                'nullable',
                'string',
                'max:100'
            ],
            'is_hidden' => [
                'nullable',
                'boolean'
            ],
            'estimated_hours' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999.99'
            ],
            'actual_hours' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999.99',
                function ($attribute, $value, $fail) {
                    // Actual hours không được vượt quá estimated hours quá nhiều (cảnh báo)
                    $estimatedHours = $this->input('estimated_hours') ?? ($this->task ? $this->task->estimated_hours : 0);
                    if ($value && $estimatedHours && $value > ($estimatedHours * 1.5)) {
                        // Chỉ warning, không fail validation
                        // Warning: Actual hours exceed estimated hours by more than 50%
                        // This should be handled by the controller, not in validation
                    }
                }
            ],
            'progress_percent' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100'
            ],
            'tags' => [
                'nullable',
                'array'
            ],
            'tags.*' => [
                'string',
                'max:50'
            ],
            'visibility' => [
                'nullable',
                'string',
                'in:internal,client'
            ],
            'client_approved' => [
                'nullable',
                'boolean'
            ]
        ];
    }

    /**
     * Validation sau khi rules cơ bản đã pass
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Kiểm tra logic nghiệp vụ phức tạp
            $this->validateDependencyCycle($validator);
            $this->validateDateLogic($validator);
            $this->validateStatusTransition($validator);
            $this->validateProgressConsistency($validator);
        });
    }

    /**
     * Kiểm tra vòng lặp trong dependencies
     */
    private function validateDependencyCycle($validator): void
    {
        $dependencies = $this->input('dependencies');
        $taskId = $this->route('task');
        
        if (!$dependencies) {
            return;
        }

        // Kiểm tra từng dependency xem có tạo vòng lặp không
        foreach ($dependencies as $depId) {
            if ($this->wouldCreateCycle($taskId, $depId, $dependencies)) {
                $validator->errors()->add('dependencies', 'Không thể tạo vòng lặp trong dependencies.');
                break;
            }
        }
    }

    /**
     * Kiểm tra logic ngày tháng
     */
    private function validateDateLogic($validator): void
    {
        $startDate = $this->input('start_date');
        $endDate = $this->input('end_date');
        $taskId = $this->route('task');
        $task = Task::find($taskId);

        if ($task && ($startDate || $endDate)) {
            $project = $task->project;
            
            if ($project) {
                $finalStartDate = $startDate ?? $task->start_date;
                $finalEndDate = $endDate ?? $task->end_date;
                
                if ($project->start_date && $finalStartDate && $finalStartDate < $project->start_date) {
                    $validator->errors()->add('start_date', 'Ngày bắt đầu task không được trước ngày bắt đầu dự án.');
                }
                if ($project->end_date && $finalEndDate && $finalEndDate > $project->end_date) {
                    $validator->errors()->add('end_date', 'Ngày kết thúc task không được sau ngày kết thúc dự án.');
                }
            }
        }
    }

    /**
     * Kiểm tra chuyển đổi trạng thái hợp lệ
     */
    private function validateStatusTransition($validator): void
    {
        $newStatus = $this->input('status');
        $taskId = $this->route('task');
        $task = Task::find($taskId);
        
        if (!$newStatus || !$task) {
            return;
        }

        $currentStatus = $task->status;
        
        // Định nghĩa các chuyển đổi trạng thái hợp lệ
        $validTransitions = [
            'pending' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'on_hold', 'cancelled'],
            'on_hold' => ['in_progress', 'cancelled'],
            'completed' => ['in_progress'], // Có thể reopen
            'cancelled' => ['pending'] // Có thể reactive
        ];

        if ($currentStatus !== $newStatus && 
            (!isset($validTransitions[$currentStatus]) || 
             !in_array($newStatus, $validTransitions[$currentStatus]))) {
            $validator->errors()->add('status', "Không thể chuyển từ trạng thái '{$currentStatus}' sang '{$newStatus}'.");
        }
    }

    /**
     * Kiểm tra tính nhất quán của progress
     */
    private function validateProgressConsistency($validator): void
    {
        $progress = $this->input('progress_percent');
        $status = $this->input('status');
        $taskId = $this->route('task');
        $task = Task::find($taskId);
        
        if (!$task) {
            return;
        }

        $finalProgress = $progress ?? $task->progress_percent;
        $finalStatus = $status ?? $task->status;

        // Kiểm tra logic nghiệp vụ
        if ($finalStatus === 'completed' && $finalProgress < 100) {
            $validator->errors()->add('progress_percent', 'Task hoàn thành phải có tiến độ 100%.');
        }
        
        if ($finalStatus === 'pending' && $finalProgress > 0) {
            $validator->errors()->add('progress_percent', 'Task chờ thực hiện không thể có tiến độ > 0%.');
        }
        
        if ($finalProgress == 100 && !in_array($finalStatus, ['completed', 'cancelled'])) {
            $validator->errors()->add('status', 'Task có tiến độ 100% phải ở trạng thái hoàn thành.');
        }
    }

    /**
     * Kiểm tra xem dependency có tạo vòng lặp không
     */
    private function wouldCreateCycle(int $taskId, int $dependencyId, array $allDependencies): bool
    {
        // Lấy dependencies của task dependency
        $dependentTask = Task::find($dependencyId);
        if (!$dependentTask || !$dependentTask->dependencies) {
            return false;
        }

        // Kiểm tra đệ quy
        return $this->checkCycleRecursive($dependentTask->dependencies, [$taskId], [$dependencyId]);
    }

    /**
     * Kiểm tra vòng lặp đệ quy
     */
    private function checkCycleRecursive(array $dependencies, array $targetIds, array $visited): bool
    {
        foreach ($dependencies as $depId) {
            if (in_array($depId, $targetIds)) {
                return true; // Tìm thấy vòng lặp
            }
            
            if (in_array($depId, $visited)) {
                continue; // Đã kiểm tra rồi
            }
            
            $visited[] = $depId;
            $task = Task::find($depId);
            if ($task && $task->dependencies) {
                if ($this->checkCycleRecursive($task->dependencies, $targetIds, $visited)) {
                    return true;
                }
            }
        }
        
        return false;
    }
}