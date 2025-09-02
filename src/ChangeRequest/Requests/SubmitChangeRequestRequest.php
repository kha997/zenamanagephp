<?php declare(strict_types=1);

namespace Src\ChangeRequest\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request để xác thực khi submit Change Request để chờ phê duyệt
 * 
 * @package Src\ChangeRequest\Requests
 */
class SubmitChangeRequestRequest extends FormRequest
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
            'confirmation' => [
                'required',
                'boolean',
                'accepted' // Phải là true
            ]
        ];
    }

    /**
     * Thông báo lỗi tùy chỉnh
     */
    public function messages(): array
    {
        return [
            'confirmation.required' => 'Bạn phải xác nhận việc submit change request',
            'confirmation.accepted' => 'Bạn phải xác nhận việc submit change request'
        ];
    }
}