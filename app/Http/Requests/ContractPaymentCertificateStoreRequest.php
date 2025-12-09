<?php declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\BaseApiRequest;

/**
 * ContractPaymentCertificateStoreRequest
 * 
 * Round 221: Payment Certificates & Payments (Actual Cost)
 */
class ContractPaymentCertificateStoreRequest extends BaseApiRequest
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
            'code' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'status' => 'required|string|in:draft,submitted,approved,rejected,cancelled',
            'amount_before_retention' => 'required|numeric',
            'retention_percent_override' => 'nullable|numeric|min:0',
            'retention_amount' => 'required|numeric',
            'amount_payable' => 'required|numeric',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date',
            'metadata' => 'nullable|array',
        ];
    }
}
