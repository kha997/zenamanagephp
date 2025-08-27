<?php declare(strict_types=1);

namespace Src\ChangeRequest\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request để xác thực khi phê duyệt hoặc từ chối Change Request
 * 
 * @package Src\ChangeRequest\Requests
 */
class DecideChangeRequestRequest extends FormRequest
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
            'decision_note' => [
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
            'decision_note.max' => 'Ghi chú quyết định không được vượt quá 1000 ký tự'
        ];
    }
}