<?php declare(strict_types=1);

namespace Src\WorkTemplate\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UpdateTemplateRequest
 * 
 * Validation cho việc cập nhật template
 * Tương tự CreateTemplateRequest nhưng có một số rules khác biệt
 */
class UpdateTemplateRequest extends FormRequest
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
        $templateId = $this->route('id'); // Lấy ID từ route parameter
        
        return [
            // Template basic info - tất cả đều optional cho update
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('templates', 'name')->ignore($templateId)
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000'
            ],
            'category' => [
                'sometimes',
                'string',
                Rule::in(['design', 'construction', 'qc', 'inspection'])
            ],
            'is_active' => [
                'sometimes',
                'boolean'
            ],
            
            // Template phases - optional, nhưng nếu có thì phải đầy đủ
            'phases' => [
                'sometimes',
                'array',
                'min:1'
            ],
            'phases.*.name' => [
                'required_with:phases',
                'string',
                'max:255'
            ],
            'phases.*.description' => [
                'nullable',
                'string',
                'max:500'
            ],
            'phases.*.order' => [
                'required_with:phases',
                'integer',
                'min:1'
            ],
            'phases.*.estimated_duration' => [
                'nullable',
                'integer',
                'min:1'
            ],
            
            // Template tasks
            'phases.*.tasks' => [
                'required_with:phases',
                'array',
                'min:1'
            ],
            'phases.*.tasks.*.name' => [
                'required_with:phases.*.tasks',
                'string',
                'max:255'
            ],
            'phases.*.tasks.*.description' => [
                'nullable',
                'string',
                'max:1000'
            ],
            'phases.*.tasks.*.order' => [
                'required_with:phases.*.tasks',
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
                'string'
            ],
            'phases.*.tasks.*.conditional_tag' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9_-]+$/'
            ],
            'phases.*.tasks.*.metadata' => [
                'nullable',
                'array'
            ],
            
            // Version info
            'version_note' => [
                'sometimes',
                'nullable',
                'string',
                'max:500'
            ],
            'create_new_version' => [
                'sometimes',
                'boolean'
            ]
        ];
    }

    /**
     * Custom validation messages
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Tên template đã tồn tại.',
            'category.in' => 'Danh mục template không hợp lệ.',
            'phases.min' => 'Template phải có ít nhất một giai đoạn.',
            'phases.*.name.required_with' => 'Tên giai đoạn là bắt buộc.',
            'phases.*.order.required_with' => 'Thứ tự giai đoạn là bắt buộc.',
            'phases.*.tasks.required_with' => 'Mỗi giai đoạn phải có ít nhất một công việc.',
            'phases.*.tasks.min' => 'Mỗi giai đoạn phải có ít nhất một công việc.',
            'phases.*.tasks.*.name.required_with' => 'Tên công việc là bắt buộc.',
            'phases.*.tasks.*.order.required_with' => 'Thứ tự công việc là bắt buộc.',
            'phases.*.tasks.*.conditional_tag.regex' => 'Thẻ điều kiện chỉ được chứa chữ cái, số, gạch dưới và gạch ngang.'
        ];
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation(): void
    {
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
        
        // Đặt create_new_version mặc định là false
        if (!$this->has('create_new_version')) {
            $this->merge(['create_new_version' => false]);
        }
    }

    /**
     * Custom validation logic
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('phases')) {
                // Chỉ validate khi có phases data
                $this->validatePhaseOrders($validator);
                $this->validateTaskOrders($validator);
                $this->validateTaskDependencies($validator);
            }
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
        
        // Collect all task IDs
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
