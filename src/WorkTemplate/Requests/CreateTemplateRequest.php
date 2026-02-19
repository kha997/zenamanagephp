<?php declare(strict_types=1);

namespace Src\WorkTemplate\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * CreateTemplateRequest
 * 
 * Validation cho việc tạo template mới
 * Đảm bảo dữ liệu đầu vào hợp lệ và tuân thủ business rules
 */
class CreateTemplateRequest extends FormRequest
{
    /**
     * Xác định user có quyền thực hiện request này không
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Định nghĩa validation rules
     */
    public function rules(): array
    {
        return [
            // Template basic info
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:templates,name' // Tên template phải unique
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000'
            ],
            'category' => [
                'required',
                'string',
                Rule::in(['design', 'construction', 'qc', 'inspection'])
            ],
            'is_active' => [
                'boolean'
            ],
            
            // Template phases
            'phases' => [
                'required',
                'array',
                'min:1' // Ít nhất phải có 1 phase
            ],
            'phases.*.name' => [
                'required',
                'string',
                'max:255'
            ],
            'phases.*.description' => [
                'nullable',
                'string',
                'max:500'
            ],
            'phases.*.order' => [
                'required',
                'integer',
                'min:1'
            ],
            'phases.*.estimated_duration' => [
                'nullable',
                'integer',
                'min:1' // Thời gian ước tính tính bằng ngày
            ],
            
            // Template tasks
            'phases.*.tasks' => [
                'required',
                'array',
                'min:1' // Mỗi phase phải có ít nhất 1 task
            ],
            'phases.*.tasks.*.name' => [
                'required',
                'string',
                'max:255'
            ],
            'phases.*.tasks.*.description' => [
                'nullable',
                'string',
                'max:1000'
            ],
            'phases.*.tasks.*.order' => [
                'required',
                'integer',
                'min:1'
            ],
            'phases.*.tasks.*.estimated_duration' => [
                'nullable',
                'integer',
                'min:1'
            ],
            'phases.*.tasks.*.dependencies' => [
                'nullable',
                'array'
            ],
            'phases.*.tasks.*.dependencies.*' => [
                'string' // Task IDs trong cùng template
            ],
            'phases.*.tasks.*.conditional_tag' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9_-]+$/' // Chỉ cho phép alphanumeric, underscore, dash
            ],
            'phases.*.tasks.*.metadata' => [
                'nullable',
                'array'
            ],
            
            // Version info
            'version_note' => [
                'nullable',
                'string',
                'max:500'
            ]
        ];
    }

    /**
     * Custom validation messages
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên template là bắt buộc.',
            'name.unique' => 'Tên template đã tồn tại.',
            'category.required' => 'Danh mục template là bắt buộc.',
            'category.in' => 'Danh mục template không hợp lệ.',
            'phases.required' => 'Template phải có ít nhất một giai đoạn.',
            'phases.min' => 'Template phải có ít nhất một giai đoạn.',
            'phases.*.name.required' => 'Tên giai đoạn là bắt buộc.',
            'phases.*.order.required' => 'Thứ tự giai đoạn là bắt buộc.',
            'phases.*.tasks.required' => 'Mỗi giai đoạn phải có ít nhất một công việc.',
            'phases.*.tasks.min' => 'Mỗi giai đoạn phải có ít nhất một công việc.',
            'phases.*.tasks.*.name.required' => 'Tên công việc là bắt buộc.',
            'phases.*.tasks.*.order.required' => 'Thứ tự công việc là bắt buộc.',
            'phases.*.tasks.*.conditional_tag.regex' => 'Thẻ điều kiện chỉ được chứa chữ cái, số, gạch dưới và gạch ngang.'
        ];
    }

    /**
     * Prepare data for validation
     * Chuẩn hóa dữ liệu trước khi validate
     */
    protected function prepareForValidation(): void
    {
        // Đảm bảo is_active có giá trị mặc định
        if (!$this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }
        
        // Chuẩn hóa conditional_tag về lowercase
        if ($this->has('phases')) {
            $phases = $this->input('phases');
            foreach ($phases as $phaseIndex => $phase) {
                if (isset($phase['tasks'])) {
                    foreach ($phase['tasks'] as $taskIndex => $task) {
                        if (isset($task['conditional_tag'])) {
                            $phases[$phaseIndex]['tasks'][$taskIndex]['conditional_tag'] = 
                                strtolower(trim($task['conditional_tag']));
                        }
                    }
                }
            }
            $this->merge(['phases' => $phases]);
        }
    }

    /**
     * Custom validation logic
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Kiểm tra thứ tự phases không trùng lặp
            $this->validatePhaseOrders($validator);
            
            // Kiểm tra thứ tự tasks trong mỗi phase không trùng lặp
            $this->validateTaskOrders($validator);
            
            // Kiểm tra dependencies hợp lệ
            $this->validateTaskDependencies($validator);
        });
    }

    /**
     * Validate phase orders are unique
     */
    private function validatePhaseOrders($validator): void
    {
        $phases = $this->input('phases', []);
        $orders = array_column($phases, 'order');
        
        if (count($orders) !== count(array_unique($orders))) {
            $validator->errors()->add('phases', 'Thứ tự các giai đoạn không được trùng lặp.');
        }
    }

    /**
     * Validate task orders are unique within each phase
     */
    private function validateTaskOrders($validator): void
    {
        $phases = $this->input('phases', []);
        
        foreach ($phases as $phaseIndex => $phase) {
            if (isset($phase['tasks'])) {
                $orders = array_column($phase['tasks'], 'order');
                
                if (count($orders) !== count(array_unique($orders))) {
                    $validator->errors()->add(
                        "phases.{$phaseIndex}.tasks",
                        'Thứ tự các công việc trong giai đoạn không được trùng lặp.'
                    );
                }
            }
        }
    }

    /**
     * Validate task dependencies reference valid tasks
     */
    private function validateTaskDependencies($validator): void
    {
        $phases = $this->input('phases', []);
        $allTaskIds = [];
        
        // Collect all task IDs (sử dụng temporary IDs cho validation)
        foreach ($phases as $phaseIndex => $phase) {
            if (isset($phase['tasks'])) {
                foreach ($phase['tasks'] as $taskIndex => $task) {
                    $allTaskIds[] = "phase_{$phaseIndex}_task_{$taskIndex}";
                }
            }
        }
        
        // Validate dependencies
        foreach ($phases as $phaseIndex => $phase) {
            if (isset($phase['tasks'])) {
                foreach ($phase['tasks'] as $taskIndex => $task) {
                    if (isset($task['dependencies'])) {
                        foreach ($task['dependencies'] as $depIndex => $dependency) {
                            if (!in_array($dependency, $allTaskIds)) {
                                $validator->errors()->add(
                                    "phases.{$phaseIndex}.tasks.{$taskIndex}.dependencies.{$depIndex}",
                                    'Phụ thuộc công việc không hợp lệ.'
                                );
                            }
                        }
                    }
                }
            }
        }
    }
}
