<?php declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\BaseApiRequest;
use Src\CoreProject\Models\Baseline;

/**
 * Form Request để xác thực dữ liệu khi cập nhật Baseline
 * 
 * @package Src\CoreProject\Requests
 */
class UpdateBaselineRequest extends BaseApiRequest
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
                'sometimes',
                'string',
                'in:' . implode(',', [Baseline::TYPE_CONTRACT, Baseline::TYPE_EXECUTION])
            ],
            'start_date' => [
                'sometimes',
                'date'
            ],
            'end_date' => [
                'sometimes',
                'date',
                'after:start_date'
            ],
            'cost' => [
                'sometimes',
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
            // Lấy baseline hiện tại để kiểm tra
            $baseline = $this->route('baseline');
            
            if ($baseline) {
                $startDate = $this->start_date ? \Carbon\Carbon::parse($this->start_date) : $baseline->start_date;
                $endDate = $this->end_date ? \Carbon\Carbon::parse($this->end_date) : $baseline->end_date;
                
                // Kiểm tra thời gian hợp lý (không quá 10 năm)
                if ($startDate && $endDate && $startDate->diffInYears($endDate) > 10) {
                    $validator->errors()->add('end_date', 'Thời gian dự án không được vượt quá 10 năm.');
                }
            }
        });
    }
}