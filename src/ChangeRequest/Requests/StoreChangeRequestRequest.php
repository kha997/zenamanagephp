<?php declare(strict_types=1);

namespace Src\ChangeRequest\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\ChangeRequest\Models\ChangeRequest;

/**
 * Form Request để xác thực dữ liệu khi tạo Change Request mới
 * 
 * @package Src\ChangeRequest\Requests
 */
class StoreChangeRequestRequest extends FormRequest
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
            'title' => [
                'required',
                'string',
                'max:255'
            ],
            'description' => [
                'required',
                'string',
                'max:5000'
            ],
            'impact_days' => [
                'nullable',
                'integer',
                'min:0',
                'max:365'
            ],
            'impact_cost' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999.99'
            ],
            'impact_kpi' => [
                'nullable',
                'array'
            ],
            'impact_kpi.*.metric' => [
                'required_with:impact_kpi',
                'string',
                'max:100'
            ],
            'impact_kpi.*.value' => [
                'required_with:impact_kpi',
                'numeric'
            ],
            'impact_kpi.*.unit' => [
                'nullable',
                'string',
                'max:50'
            ],
            'attachments' => [
                'nullable',
                'array'
            ],
            'attachments.*' => [
                'string',
                'max:500' // File path hoặc URL
            ],
            'justification' => [
                'nullable',
                'string',
                'max:2000'
            ],
            'priority' => [
                'required',
                Rule::in(ChangeRequest::PRIORITIES)
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
                'required',
                Rule::in(['internal', 'client'])
            ]
        ];
    }

    /**
     * Thông báo lỗi tùy chỉnh
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Tiêu đề change request là bắt buộc',
            'title.max' => 'Tiêu đề không được vượt quá 255 ký tự',
            'description.required' => 'Mô tả change request là bắt buộc',
            'description.max' => 'Mô tả không được vượt quá 5000 ký tự',
            'impact_days.integer' => 'Số ngày ảnh hưởng phải là số nguyên',
            'impact_days.min' => 'Số ngày ảnh hưởng không được âm',
            'impact_days.max' => 'Số ngày ảnh hưởng không được vượt quá 365 ngày',
            'impact_cost.numeric' => 'Chi phí ảnh hưởng phải là số',
            'impact_cost.min' => 'Chi phí ảnh hưởng không được âm',
            'priority.required' => 'Mức độ ưu tiên là bắt buộc',
            'priority.in' => 'Mức độ ưu tiên không hợp lệ',
            'visibility.required' => 'Mức độ hiển thị là bắt buộc',
            'visibility.in' => 'Mức độ hiển thị không hợp lệ'
        ];
    }

    /**
     * Chuẩn bị dữ liệu trước khi validation
     */
    protected function prepareForValidation(): void
    {
        // Chuyển đổi tags thành array nếu là string
        if ($this->has('tags') && is_string($this->tags)) {
            $this->merge([
                'tags' => array_filter(array_map('trim', explode(',', $this->tags)))
            ]);
        }

        // Đảm bảo impact_kpi là array
        if ($this->has('impact_kpi') && !is_array($this->impact_kpi)) {
            $this->merge(['impact_kpi' => []]);
        }
    }
}