<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Project;

/**
 * Form Request cho Project index với filtering
 * 
 * @package App\Http\Requests
 */
class IndexProjectRequest extends FormRequest
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
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', Rule::in(Project::VALID_STATUSES)],
            'statuses' => ['sometimes', 'array'],
            'statuses.*' => ['string', Rule::in(Project::VALID_STATUSES)],
            'priority' => ['sometimes', 'string', Rule::in(Project::VALID_PRIORITIES)],
            'priorities' => ['sometimes', 'array'],
            'priorities.*' => ['string', Rule::in(Project::VALID_PRIORITIES)],
            'overdue' => ['sometimes', 'boolean'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'client_id' => ['sometimes', 'string', 'exists:users,id'],
            'pm_id' => ['sometimes', 'string', 'exists:users,id'],
            'sort_by' => ['sometimes', 'string', Rule::in(['name', 'created_at', 'start_date', 'end_date', 'status', 'priority', 'progress'])],
            'sort_direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'per_page.max' => 'Số lượng items per page không được vượt quá 100.',
            'search.max' => 'Từ khóa tìm kiếm không được vượt quá 255 ký tự.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'statuses.array' => 'Trạng thái phải là một mảng.',
            'statuses.*.in' => 'Trạng thái không hợp lệ.',
            'priority.in' => 'Mức độ ưu tiên không hợp lệ.',
            'priorities.array' => 'Mức độ ưu tiên phải là một mảng.',
            'priorities.*.in' => 'Mức độ ưu tiên không hợp lệ.',
            'overdue.boolean' => 'Trường overdue phải là true hoặc false.',
            'date_from.date' => 'Ngày bắt đầu không hợp lệ.',
            'date_to.date' => 'Ngày kết thúc không hợp lệ.',
            'date_to.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
            'client_id.exists' => 'Khách hàng không tồn tại.',
            'pm_id.exists' => 'Project Manager không tồn tại.',
            'sort_by.in' => 'Trường sắp xếp không hợp lệ.',
            'sort_direction.in' => 'Hướng sắp xếp phải là asc hoặc desc.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values
        $this->merge([
            'per_page' => $this->input('per_page', 15),
            'sort_by' => $this->input('sort_by', 'created_at'),
            'sort_direction' => $this->input('sort_direction', 'desc'),
        ]);
    }
}