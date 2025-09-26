<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request để xác thực dữ liệu khi đồng bộ task assignments với compensation records
 * 
 * @package Src\Compensation\Requests
 */
class SyncTaskAssignmentsRequest extends FormRequest
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
            'project_id' => [
                'required',
                'integer',
                'exists:projects,id'
            ],
            'force_update' => [
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
            'project_id.required' => 'Project ID là bắt buộc',
            'project_id.exists' => 'Project không tồn tại',
            'force_update.boolean' => 'Force update phải là true hoặc false'
        ];
    }
}