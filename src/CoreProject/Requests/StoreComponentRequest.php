<?php declare(strict_types=1);

namespace Src\CoreProject\Requests;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request để xác thực dữ liệu khi tạo Component mới
 * 
 * @package zenamanage\CoreProject\Requests
 */
class StoreComponentRequest extends BaseApiRequest
{
    /**
     * Xác định user có quyền thực hiện request này không
     */
    public function authorize(): bool
    {
        return true; // Authorization được xử lý bởi RBAC middleware
    }

    /**
     * Các quy tắc validation cho request
     */
    public function rules(): array
    {
        return [
            'project_id' => [
                'required',
                'integer',
                'exists:projects,id'
            ],
            'parent_component_id' => [
                'nullable',
                'integer',
                'exists:components,id',
                function ($attribute, $value, $fail) {
                    // Kiểm tra parent component phải thuộc cùng project
                    if ($value && $this->project_id) {
                        $parentComponent = \zenamanage\CoreProject\Models\Component::find($value);
                        if ($parentComponent && $parentComponent->project_id !== (int)$this->project_id) {
                            $fail('Parent component phải thuộc cùng dự án.');
                        }
                    }
                }
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('components')
                    ->where('project_id', $this->project_id)
                    ->where('parent_component_id', $this->parent_component_id)
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000'
            ],
            'progress_percent' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100'
            ],
            'planned_cost' => [
                'nullable',
                'numeric',
                'min:0'
            ],
            'actual_cost' => [
                'nullable',
                'numeric',
                'min:0'
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
     * Chuẩn bị dữ liệu trước khi validation
     */
    protected function prepareForValidation(): void
    {
        // Đặt giá trị mặc định
        $this->merge([
            'progress_percent' => $this->progress_percent ?? 0,
            'planned_cost' => $this->planned_cost ?? 0,
            'actual_cost' => $this->actual_cost ?? 0,
            'visibility' => $this->visibility ?? 'internal',
            'client_approved' => $this->client_approved ?? false
        ]);
    }
}