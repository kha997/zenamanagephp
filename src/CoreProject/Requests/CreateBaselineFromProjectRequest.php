<?php declare(strict_types=1);

namespace Src\CoreProject\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\CoreProject\Models\Baseline;

/**
 * Form Request để xác thực dữ liệu khi tạo baseline từ project hiện tại
 * 
 * @package Src\CoreProject\Requests
 */
class CreateBaselineFromProjectRequest extends FormRequest
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
            'type' => [
                'required',
                'string',
                'in:' . implode(',', [Baseline::TYPE_CONTRACT, Baseline::TYPE_EXECUTION])
            ],
            'note' => [
                'nullable',
                'string',
                'max:2000'
            ],
            'include_actual_costs' => [
                'nullable',
                'boolean'
            ],
            'include_actual_dates' => [
                'nullable',
                'boolean'
            ]
        ];
    }

    /**
     * Thông báo lỗi tùy chỉnh
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Loại baseline là bắt buộc.',
            'type.in' => 'Loại baseline không hợp lệ.',
            'note.max' => 'Ghi chú không được vượt quá 2000 ký tự.'
        ];
    }

    /**
     * Chuẩn bị dữ liệu trước khi validation
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'include_actual_costs' => $this->include_actual_costs ?? true,
            'include_actual_dates' => $this->include_actual_dates ?? true
        ]);
    }
}