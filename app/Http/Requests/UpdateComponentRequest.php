<?php declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request để xác thực dữ liệu khi cập nhật Component
 * 
 * @package zenamanage\CoreProject\Requests
 */
class UpdateComponentRequest extends BaseApiRequest
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
        $componentId = $this->route('component');
        $component = \zenamanage\CoreProject\Models\Component::find($componentId);
        
        return [
            'parent_component_id' => [
                'nullable',
                'integer',
                'exists:components,id',
                'not_in:' . $componentId, // Không thể set chính nó làm parent
                function ($attribute, $value, $fail) use ($component) {
                    if ($component && $this->wouldCreateCycle($component, $value)) {
                        $fail('Không thể tạo vòng lặp trong cấu trúc component.');
                    }

                    // Kiểm tra parent component phải thuộc cùng project
                    $parentComponent = \zenamanage\CoreProject\Models\Component::find($value);
                    if ($parentComponent && $component && $parentComponent->project_id !== $component->project_id) {
                        $fail('Parent component phải thuộc cùng dự án.');
                    }
                },
            ],
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('components')
                    ->ignore($componentId)
                    ->where(function ($query) {
                        $component = \zenamanage\CoreProject\Models\Component::find($this->route('component'));
                        if ($component) {
                            $query->where('project_id', $component->project_id)
                                  ->where('parent_component_id', $this->parent_component_id ?? $component->parent_component_id);
                        }
                    })
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
     * Kiểm tra xem việc thay đổi parent có tạo vòng lặp không
     */
    private function wouldCreateCycle($component, $newParentId): bool
    {
        $currentId = $newParentId;
        $visited = [];
        
        while ($currentId && !in_array($currentId, $visited)) {
            if ($currentId == $component->id) {
                return true; // Tìm thấy vòng lặp
            }
            
            $visited[] = $currentId;
            $parent = \zenamanage\CoreProject\Models\Component::find($currentId);
            $currentId = $parent ? $parent->parent_component_id : null;
        }
        
        return false;
    }
}
