<?php declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\BaseApiRequest;

/**
 * ProjectBudgetLineStoreRequest
 * 
 * Round 219: Core Contracts & Budget (Backend-first)
 */
class ProjectBudgetLineStoreRequest extends BaseApiRequest
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
            'description' => 'required|string|max:255',
            'amount_budget' => 'required|numeric|min:0',
            'cost_category' => 'nullable|string|max:100',
            'cost_code' => 'nullable|string|max:100',
            'unit' => 'nullable|string|max:50',
            'quantity' => 'nullable|numeric|min:0',
            'unit_price_budget' => 'nullable|numeric|min:0',
            'metadata' => 'nullable|array',
        ];
    }
}
