<?php declare(strict_types=1);

namespace Src\CoreProject\Requests;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;
use Src\CoreProject\Models\Baseline;

/**
 * Form Request để xác thực dữ liệu khi tạo Baseline mới
 * 
 * @package Src\CoreProject\Requests
 */
class StoreBaselineRequest extends BaseApiRequest
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
            'type' => [
                'required',
                'string',
                'in:' . implode(',', [Baseline::TYPE_CONTRACT, Baseline::TYPE_EXECUTION])
            ],
            'start_date' => [
                'required',
                'date'
            ],
            'end_date' => [
                'required',
                'date',
                'after:start_date'
            ],
            'cost' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ],
            'note' => [
                'nullable',
                'string',
                'max:2000'
            ]
        ];
    }

    /**
     * Validation tùy chỉnh
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Kiểm tra thời gian hợp lý (không quá 10 năm)
            if ($this->start_date && $this->end_date) {
                $startDate = \Carbon\Carbon::parse($this->start_date);
                $endDate = \Carbon\Carbon::parse($this->end_date);
                
                if ($startDate->diffInYears($endDate) > 10) {
                    $validator->errors()->add('end_date', 'Thời gian dự án không được vượt quá 10 năm.');
                }
            }
        });
    }
}