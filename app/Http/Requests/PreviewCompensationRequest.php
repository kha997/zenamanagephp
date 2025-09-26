<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request để xác thực dữ liệu khi preview compensation trước khi áp dụng contract
 * 
 * @package Src\Compensation\Requests
 */
class PreviewCompensationRequest extends FormRequest
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
            'contract_id' => [
                'required',
                'integer',
                'exists:contracts,id'
            ],
            'task_ids' => [
                'nullable',
                'array'
            ],
            'task_ids.*' => [
                'integer',
                'exists:tasks,id'
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
            'contract_id.required' => 'Contract ID là bắt buộc',
            'contract_id.exists' => 'Contract không tồn tại',
            'task_ids.array' => 'Task IDs phải là một mảng',
            'task_ids.*.exists' => 'Task không tồn tại'
        ];
    }
}