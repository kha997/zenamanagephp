<?php declare(strict_types=1);

namespace App\Http\Requests;
use Illuminate\Support\Facades\Auth;


use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request cho ChangeRequest validation
 * 
 * @package App\Http\Requests
 */
class ChangeRequestFormRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:50', Rule::unique('change_requests')->ignore($this->route('change_request'))],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::in(['draft', 'awaiting_approval', 'approved', 'rejected'])],
            'impact_days' => ['nullable', 'integer', 'min:0'],
            'impact_cost' => ['nullable', 'numeric', 'min:0'],
            'impact_kpi' => ['nullable', 'array'],
            'decision_note' => ['nullable', 'string', 'max:2000'],
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
            'code.required' => 'Mã CR là bắt buộc.',
            'code.unique' => 'Mã CR đã tồn tại.',
            'title.required' => 'Tiêu đề là bắt buộc.',
            'title.max' => 'Tiêu đề không được vượt quá 255 ký tự.',
            'description.required' => 'Mô tả là bắt buộc.',
            'description.max' => 'Mô tả không được vượt quá 5000 ký tự.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'impact_days.integer' => 'Số ngày ảnh hưởng phải là số nguyên.',
            'impact_days.min' => 'Số ngày ảnh hưởng không được âm.',
            'impact_cost.numeric' => 'Chi phí ảnh hưởng phải là số.',
            'impact_cost.min' => 'Chi phí ảnh hưởng không được âm.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Thêm created_by từ authenticated user
        if (Auth::check() && $this->isMethod('POST')) {
            $this->merge([
                'created_by' => Auth::id(),
            ]);
        }

        // Convert impact_kpi JSON string to array if needed
        if ($this->has('impact_kpi') && is_string($this->impact_kpi)) {
            $this->merge([
                'impact_kpi' => json_decode($this->impact_kpi, true) ?: null,
            ]);
        }
    }
}