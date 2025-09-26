<?php declare(strict_types=1);

namespace App\Http\Requests;

use Src\CoreProject\Models\WorkTemplate;
use Src\Shared\Requests\BaseApiRequest;

class StoreWorkTemplateRequest extends BaseApiRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:work_templates,name'
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000'
            ],
            'category' => [
                'required',
                'string',
                'in:' . implode(',', array_keys(WorkTemplate::CATEGORIES))
            ],
            'template_data' => [
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
            'version' => [
                'nullable',
                'integer',
                'min:1'
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
     * Validation bổ sung sau khi validation cơ bản
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Kiểm tra cấu trúc template_data
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
        });
    }

    /**
     * Chuẩn bị dữ liệu trước khi validation
     */
    protected function prepareForValidation()
    {
        // Đặt version mặc định nếu không có
        if (!$this->has('version')) {
            $this->merge(['version' => 1]);
        }
        
        // Đặt is_active mặc định
        if (!$this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }
    }
}