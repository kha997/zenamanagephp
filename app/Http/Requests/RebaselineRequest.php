<?php declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\Carbon;
use Src\Shared\Requests\BaseApiRequest;

class RebaselineRequest extends BaseApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
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
                'nullable',
                'date'
            ],
            'new_end_date' => [
                'nullable',
                'date',
                'after_or_equal:new_start_date'
            ],
            'new_cost' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999999.99'
            ]
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Kiểm tra ít nhất một trường mới phải được cung cấp
            if (!$this->input('new_start_date') && 
                !$this->input('new_end_date') && 
                !$this->input('new_cost')) {
                $validator->errors()->add('general', 'Phải cung cấp ít nhất một thông tin mới để thực hiện re-baseline.');
            }

            // Kiểm tra thời gian dự án không vượt quá 10 năm
            if ($this->input('new_start_date') && $this->input('new_end_date')) {
                $startDate = Carbon::parse($this->input('new_start_date'));
                $endDate = Carbon::parse($this->input('new_end_date'));
                
                if ($startDate->diffInYears($endDate) > 10) {
                    $validator->errors()->add('new_end_date', 'Thời gian dự án không được vượt quá 10 năm.');
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'approval_required' => $this->input('approval_required', false)
        ]);
    }
}