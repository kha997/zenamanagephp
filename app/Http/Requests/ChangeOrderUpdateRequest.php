<?php declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\BaseApiRequest;

/**
 * ChangeOrderUpdateRequest
 * 
 * Round 220: Change Orders for Contracts
 */
class ChangeOrderUpdateRequest extends BaseApiRequest
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
            'code' => 'sometimes|string|max:255',
            'title' => 'sometimes|string|max:255',
            'reason' => 'nullable|string|max:255',
            'status' => 'sometimes|string|in:draft,proposed,approved,rejected,cancelled',
            'amount_delta' => 'sometimes|numeric',
            'effective_date' => 'nullable|date',
            'metadata' => 'nullable|array',
            'lines' => 'nullable|array',
            'lines.*.description' => 'required|string|max:255',
            'lines.*.amount_delta' => 'required|numeric',
            'lines.*.item_code' => 'nullable|string|max:100',
            'lines.*.unit' => 'nullable|string|max:50',
            'lines.*.quantity_delta' => 'nullable|numeric',
            'lines.*.unit_price_delta' => 'nullable|numeric',
            'lines.*.contract_line_id' => 'nullable|string|exists:contract_lines,id',
            'lines.*.budget_line_id' => 'nullable|string|exists:project_budget_lines,id',
            'lines.*.metadata' => 'nullable|array',
        ];
    }
}
