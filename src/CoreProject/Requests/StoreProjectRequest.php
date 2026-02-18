<?php declare(strict_types=1);

namespace Src\CoreProject\Requests;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request để xác thực dữ liệu khi tạo Project mới
 * 
 * @package Src\CoreProject\Requests
 */
class StoreProjectRequest extends BaseApiRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('projects')
                    ->where('tenant_id', $this->user()->tenant_id ?? 1)
            ],
            'code' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('projects')
                    ->where('tenant_id', $this->user()->tenant_id ?? 1)
            ],
            'description' => [
                'nullable',
                'string',
                'max:2000'
            ],
            'start_date' => [
                'required',
                'date',
                'after_or_equal:today'
            ],
            'end_date' => [
                'required',
                'date',
                'after:start_date'
            ],
            'status' => [
                'nullable',
                'string',
                'in:' . implode(',', \Src\CoreProject\Models\Project::VALID_STATUSES)
            ],
            'planned_cost' => [
                'nullable',
                'numeric',
                'min:0'
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
                'nullable',
                'string',
                'in:internal,client'
            ],
            'client_approved' => [
                'nullable',
                'boolean'
            ],
            'work_template_id' => [
                'nullable',
                'integer',
                'exists:work_templates,id'
            ]
        ];
    }

    /**
     * Chuẩn bị dữ liệu trước khi validation
     */
    protected function prepareForValidation(): void
    {
        // Đặt giá trị mặc định
        $this->merge([
            'status' => $this->status ?? 'planning',
            'planned_cost' => $this->planned_cost ?? 0,
            'visibility' => $this->visibility ?? 'internal',
            'client_approved' => $this->client_approved ?? false
        ]);
    }
}
