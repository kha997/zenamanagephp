<?php declare(strict_types=1);

namespace Src\CoreProject\Requests;

use Src\CoreProject\Models\Baseline;
use Src\Shared\Requests\BaseApiRequest;

class CreateBaselineFromProjectRequest extends BaseApiRequest
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
            'type' => [
                'required',
                'string',
                'in:' . Baseline::TYPE_CONTRACT . ',' . Baseline::TYPE_EXECUTION
            ],
            'note' => [
                'nullable',
                'string',
                'max:2000'
            ],
            'include_actual_costs' => [
                'nullable',
                'boolean'
            ],
            'include_actual_dates' => [
                'nullable',
                'boolean'
            ]
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'include_actual_costs' => $this->input('include_actual_costs', true),
            'include_actual_dates' => $this->input('include_actual_dates', true)
        ]);
    }
}