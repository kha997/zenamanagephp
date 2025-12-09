<?php declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\BaseApiRequest;

/**
 * ContractUpdateRequest
 * 
 * Round 219: Core Contracts & Budget (Backend-first)
 */
class ContractUpdateRequest extends BaseApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'code' => 'sometimes|required|string|max:100',
            'name' => 'sometimes|required|string|max:255',
            'type' => 'nullable|string|in:main,subcontract,supply,consultant',
            'party_name' => 'sometimes|required|string|max:255',
            'base_amount' => 'sometimes|required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'vat_percent' => 'nullable|numeric|min:0|max:100',
            'total_amount_with_vat' => 'nullable|numeric|min:0',
            'retention_percent' => 'nullable|numeric|min:0|max:100',
            'status' => 'sometimes|required|string|in:draft,active,completed,terminated',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'metadata' => 'nullable|array',
            'notes' => 'nullable|string',
            'lines' => 'nullable|array',
            'lines.*.description' => 'required|string|max:255',
            'lines.*.quantity' => 'required|numeric|min:0',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.amount' => 'required|numeric|min:0',
            'lines.*.item_code' => 'nullable|string|max:100',
            'lines.*.unit' => 'nullable|string|max:50',
            'lines.*.budget_line_id' => 'nullable|string|exists:project_budget_lines,id',
            'lines.*.metadata' => 'nullable|array',
        ];
    }
}
