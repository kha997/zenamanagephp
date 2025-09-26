<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request cho Component validation
 * 
 * @package App\Http\Requests
 */
class ComponentFormRequest extends FormRequest
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
        return [
            'project_id' => ['required', 'exists:projects,id'],
            'parent_component_id' => ['nullable', 'exists:components,id'],
            'name' => ['required', 'string', 'max:255'],
            'progress_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'planned_cost' => ['required', 'numeric', 'min:0'],
            'actual_cost' => ['sometimes', 'numeric', 'min:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'project_id.required' => 'ID dự án là bắt buộc.',
            'project_id.exists' => 'Dự án không tồn tại.',
            'parent_component_id.exists' => 'Component cha không tồn tại.',
            'name.required' => 'Tên component là bắt buộc.',
            'name.max' => 'Tên component không được vượt quá 255 ký tự.',
            'progress_percent.numeric' => 'Tiến độ phải là số.',
            'progress_percent.min' => 'Tiến độ không được nhỏ hơn 0%.',
            'progress_percent.max' => 'Tiến độ không được lớn hơn 100%.',
            'planned_cost.required' => 'Chi phí dự kiến là bắt buộc.',
            'planned_cost.numeric' => 'Chi phí dự kiến phải là số.',
            'planned_cost.min' => 'Chi phí dự kiến không được âm.',
            'actual_cost.numeric' => 'Chi phí thực tế phải là số.',
            'actual_cost.min' => 'Chi phí thực tế không được âm.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Kiểm tra parent component thuộc cùng project
            if ($this->parent_component_id && $this->project_id) {
                $parentComponent = \App\Models\Component::find($this->parent_component_id);
                if ($parentComponent && $parentComponent->project_id !== (int)$this->project_id) {
                    $validator->errors()->add('parent_component_id', 'Component cha phải thuộc cùng dự án.');
                }
            }
        });
    }
}