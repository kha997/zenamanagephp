<?php declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\BaseApiRequest;

/**
 * ContractPaymentUpdateRequest
 * 
 * Round 221: Payment Certificates & Payments (Actual Cost)
 */
class ContractPaymentUpdateRequest extends BaseApiRequest
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
            'paid_date' => 'sometimes|date',
            'amount_paid' => 'sometimes|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'payment_method' => 'nullable|string|max:50',
            'reference_no' => 'nullable|string|max:255',
            'certificate_id' => 'nullable|string',
            'metadata' => 'nullable|array',
        ];
    }
}
