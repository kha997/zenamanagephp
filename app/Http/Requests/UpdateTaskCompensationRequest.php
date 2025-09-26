<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request để xác thực dữ liệu khi cập nhật task compensation
 * 
 * @package Src\Compensation\Requests
 */
class UpdateTaskCompensationRequest extends FormRequest
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
            'efficiency_percent' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'max:200' // Cho phép hiệu quả lên đến 200%
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000'
            ]
        ];
    }

    /**
     * Thông báo lỗi tùy chỉnh
     */
    public function messages(): array
    {
        return [
            'efficiency_percent.required' => 'Phần trăm hiệu quả là bắt buộc',
            'efficiency_percent.numeric' => 'Phần trăm hiệu quả phải là số',
            'efficiency_percent.min' => 'Phần trăm hiệu quả không được nhỏ hơn 0',
            'efficiency_percent.max' => 'Phần trăm hiệu quả không được lớn hơn 200%',
            'notes.max' => 'Ghi chú không được vượt quá 1000 ký tự'
        ];
    }
}