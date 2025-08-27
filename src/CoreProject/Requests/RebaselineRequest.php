<?php declare(strict_types=1);

namespace Src\CoreProject\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\CoreProject\Models\Baseline;

/**
 * Form Request để xác thực dữ liệu khi thực hiện re-baseline
 * 
 * @package Src\CoreProject\Requests
 */
class RebaselineRequest extends FormRequest
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
            'reason' => [
                'required',
                'string',
                'max:1000'
            ],
            'new_start_date' => [
                'sometimes',
                'date'
            ],
            'new_end_date' => [
                'sometimes',
                'date',
                'after:new_start_date'
            ],
            'new_cost' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ],
            'approval_required' => [
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
            'reason.required' => 'Lý do re-baseline là bắt buộc.',
            'reason.max' => 'Lý do re-baseline không được vượt quá 1000 ký tự.',
            'new_end_date.after' => 'Ngày kết thúc mới phải sau ngày bắt đầu mới.',
            'new_cost.min' => 'Chi phí mới không thể âm.',
            'new_cost.max' => 'Chi phí mới vượt quá giới hạn cho phép.'
        ];
    }

    /**
     * Validation tùy chỉnh
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Kiểm tra ít nhất một trong các trường mới được cung cấp
            if (!$this->new_start_date && !$this->new_end_date && !$this->new_cost) {
                $validator->errors()->add('general', 'Phải cung cấp ít nhất một thông tin mới để re-baseline.');
            }
            
            // Kiểm tra thời gian hợp lý nếu có cả start_date và end_date
            if ($this->new_start_date && $this->new_end_date) {
                $startDate = \Carbon\Carbon::parse($this->new_start_date);
                $endDate = \Carbon\Carbon::parse($this->new_end_date);
                
                if ($startDate->diffInYears($endDate) > 10) {
                    $validator->errors()->add('new_end_date', 'Thời gian dự án mới không được vượt quá 10 năm.');
                }
            }
        });
    }

    /**
     * Chuẩn bị dữ liệu trước khi validation
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'approval_required' => $this->approval_required ?? false
        ]);
    }
}