<?php declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\BaseApiRequest;

/**
 * ContractPaymentCertificateUpdateRequest
 * 
 * Round 221: Payment Certificates & Payments (Actual Cost)
 */
class ContractPaymentCertificateUpdateRequest extends BaseApiRequest
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
            'title' => 'nullable|string|max:255',
            'status' => 'sometimes|string|in:draft,submitted,approved,rejected,cancelled',
            'amount_before_retention' => 'sometimes|numeric',
            'retention_percent_override' => 'nullable|numeric|min:0',
            'retention_amount' => 'sometimes|numeric',
            'amount_payable' => 'sometimes|numeric',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date',
            'metadata' => 'nullable|array',
        ];
    }
}
