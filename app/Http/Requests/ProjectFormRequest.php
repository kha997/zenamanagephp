<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request cho Project validation
 * 
 * @package App\Http\Requests
 */
class ProjectFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization sẽ được handle bởi middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $projectId = $this->route('project');
        
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'client_id' => ['nullable', 'string', 'exists:users,id'],
            'pm_id' => ['nullable', 'string', 'exists:users,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'status' => ['sometimes', Rule::in(\App\Models\Project::VALID_STATUSES)],
            'progress' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'budget_planned' => ['sometimes', 'numeric', 'min:0'],
            'budget_actual' => ['sometimes', 'numeric', 'min:0'],
            'priority' => ['sometimes', Rule::in(\App\Models\Project::VALID_PRIORITIES)],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],
            'settings' => ['sometimes', 'array'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên dự án là bắt buộc.',
            'name.max' => 'Tên dự án không được vượt quá 255 ký tự.',
            'description.max' => 'Mô tả không được vượt quá 2000 ký tự.',
            'client_id.exists' => 'Khách hàng không tồn tại.',
            'pm_id.exists' => 'Project Manager không tồn tại.',
            'start_date.required' => 'Ngày bắt đầu là bắt buộc.',
            'start_date.date' => 'Ngày bắt đầu không hợp lệ.',
            'end_date.required' => 'Ngày kết thúc là bắt buộc.',
            'end_date.date' => 'Ngày kết thúc không hợp lệ.',
            'end_date.after' => 'Ngày kết thúc phải sau ngày bắt đầu.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'progress.numeric' => 'Tiến độ phải là số.',
            'progress.min' => 'Tiến độ không được nhỏ hơn 0%.',
            'progress.max' => 'Tiến độ không được lớn hơn 100%.',
            'budget_planned.numeric' => 'Ngân sách dự kiến phải là số.',
            'budget_planned.min' => 'Ngân sách dự kiến không được âm.',
            'budget_actual.numeric' => 'Chi phí thực tế phải là số.',
            'budget_actual.min' => 'Chi phí thực tế không được âm.',
            'priority.in' => 'Mức độ ưu tiên không hợp lệ.',
            'tags.array' => 'Tags phải là một mảng.',
            'tags.*.string' => 'Mỗi tag phải là chuỗi.',
            'tags.*.max' => 'Mỗi tag không được vượt quá 50 ký tự.',
            'settings.array' => 'Settings phải là một mảng.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Thêm tenant_id từ authenticated user
        if (auth()->check()) {
            $this->merge([
                'tenant_id' => auth()->user()->tenant_id,
            ]);
        }
    }
}