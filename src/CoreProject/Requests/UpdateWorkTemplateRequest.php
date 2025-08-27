<?php declare(strict_types=1);

namespace Src\CoreProject\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\CoreProject\Models\WorkTemplate;

class UpdateWorkTemplateRequest extends FormRequest
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
        $templateId = $this->route('workTemplate');
        
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('work_templates', 'name')->ignore($templateId)
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000'
            ],
            'category' => [
                'sometimes',
                'required',
                'string',
                'in:' . implode(',', array_keys(WorkTemplate::CATEGORIES))
            ],
            'template_data' => [
                'sometimes',
                'required',
                'array'
            ],
            'template_data.tasks' => [
                'nullable',
                'array'
            ],
            'template_data.tasks.*.name' => [
                'required_with:template_data.tasks',
                'string',
                'max:255'
            ],
            'template_data.tasks.*.description' => [
                'nullable',
                'string'
            ],
            'template_data.tasks.*.estimated_hours' => [
                'nullable',
                'numeric',
                'min:0'
            ],
            'template_data.tasks.*.dependencies' => [
                'nullable',
                'array'
            ],
            'template_data.metadata' => [
                'nullable',
                'array'
            ],
            'is_active' => [
                'nullable',
                'boolean'
            ],
            'tags' => [
                'nullable',
                'array'
            ],
            'tags.*' => [
                'string',
                'max:50'
            ]
        ];
    }

    /**
     * Thông báo lỗi tùy chỉnh
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên template là bắt buộc.',
            'name.unique' => 'Tên template đã tồn tại.',
            'category.required' => 'Loại template là bắt buộc.',
            'category.in' => 'Loại template không hợp lệ.',
            'template_data.required' => 'Dữ liệu template là bắt buộc.',
            'template_data.tasks.*.name.required_with' => 'Tên task là bắt buộc.',
            'template_data.tasks.*.estimated_hours.min' => 'Số giờ ước tính phải lớn hơn hoặc bằng 0.'
        ];
    }

    /**
     * Validation bổ sung sau khi validation cơ bản
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Kiểm tra cấu trúc template_data nếu có
            if ($this->has('template_data')) {
                $templateData = $this->input('template_data', []);
                
                if (isset($templateData['tasks']) && is_array($templateData['tasks'])) {
                    $taskNames = [];
                    foreach ($templateData['tasks'] as $index => $task) {
                        // Kiểm tra tên task trùng lặp
                        if (isset($task['name'])) {
                            if (in_array($task['name'], $taskNames)) {
                                $validator->errors()->add(
                                    "template_data.tasks.{$index}.name",
                                    'Tên task bị trùng lặp trong template.'
                                );
                            }
                            $taskNames[] = $task['name'];
                        }
                        
                        // Kiểm tra dependencies hợp lệ
                        if (isset($task['dependencies']) && is_array($task['dependencies'])) {
                            foreach ($task['dependencies'] as $depIndex => $dependency) {
                                if (!is_string($dependency)) {
                                    $validator->errors()->add(
                                        "template_data.tasks.{$index}.dependencies.{$depIndex}",
                                        'Dependency phải là chuỗi tên task.'
                                    );
                                }
                            }
                        }
                    }
                }
            }
            
            // Cảnh báo khi thay đổi category của template đang được sử dụng
            if ($this->has('category')) {
                $template = WorkTemplate::find($this->route('workTemplate'));
                if ($template && $template->category !== $this->input('category')) {
                    // Có thể thêm logic kiểm tra xem template có đang được sử dụng không
                    $validator->warnings()->add(
                        'category',
                        'Thay đổi loại template có thể ảnh hưởng đến các project đang sử dụng template này.'
                    );
                }
            }
        });
    }
}