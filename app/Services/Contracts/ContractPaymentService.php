<?php declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractPayment;
use App\Support\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing contract payments with business invariants
 * 
 * Round 37: Payment Hardening - Business invariant enforcement
 * 
 * Business Invariant:
 * - Sum of all active (non-soft-deleted) payments for a contract
 *   must not exceed contract.total_value (if total_value is not null)
 */
class ContractPaymentService
{
    /**
     * Create a new payment for a contract
     * 
     * @param Contract $contract The contract to create payment for
     * @param array $data Payment data (name, amount, due_date, etc.)
     * @param string $userId User ID creating the payment
     * @return ContractPayment Created payment
     * @throws HttpResponseException If invariant is violated
     */
    public function createPaymentForContract(Contract $contract, array $data, string $userId): ContractPayment
    {
        // Validate business invariant before creating
        $this->validatePaymentTotalInvariant($contract, $data['amount'], null);

        // Create payment
        $payment = ContractPayment::create([
            'tenant_id' => $contract->tenant_id,
            'contract_id' => $contract->id,
            'code' => $data['code'] ?? null,
            'name' => $data['name'],
            'type' => $data['type'] ?? null,
            'due_date' => $data['due_date'],
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? $contract->currency ?? 'USD',
            'status' => $data['status'] ?? 'planned',
            'paid_at' => $data['paid_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'created_by_id' => $userId,
            'updated_by_id' => $userId,
        ]);

        Log::info('Contract payment created via service', [
            'payment_id' => $payment->id,
            'contract_id' => $contract->id,
            'tenant_id' => $payment->tenant_id,
            'amount' => $payment->amount,
            'created_by' => $userId,
        ]);

        return $payment;
    }

    /**
     * Update an existing payment for a contract
     * 
     * @param Contract $contract The contract the payment belongs to
     * @param ContractPayment $payment The payment to update
     * @param array $data Updated payment data
     * @param string $userId User ID updating the payment
     * @return ContractPayment Updated payment
     * @throws HttpResponseException If invariant is violated
     */
    public function updatePaymentForContract(
        Contract $contract,
        ContractPayment $payment,
        array $data,
        string $userId
    ): ContractPayment {
        // Get the new amount (if provided) or use existing amount
        $newAmount = $data['amount'] ?? $payment->amount;

        // Validate business invariant before updating
        $this->validatePaymentTotalInvariant($contract, $newAmount, $payment);

        // Update payment fields
        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (array_key_exists('code', $data)) {
            $updateData['code'] = $data['code'];
        }
        if (array_key_exists('type', $data)) {
            $updateData['type'] = $data['type'];
        }
        if (isset($data['due_date'])) {
            $updateData['due_date'] = $data['due_date'];
        }
        if (isset($data['amount'])) {
            $updateData['amount'] = $data['amount'];
        }
        if (array_key_exists('currency', $data)) {
            $updateData['currency'] = $data['currency'];
        }
        if (array_key_exists('status', $data)) {
            $updateData['status'] = $data['status'];
        }
        if (array_key_exists('paid_at', $data)) {
            $updateData['paid_at'] = $data['paid_at'];
        }
        if (array_key_exists('notes', $data)) {
            $updateData['notes'] = $data['notes'];
        }
        if (array_key_exists('sort_order', $data)) {
            $updateData['sort_order'] = $data['sort_order'];
        }
        $updateData['updated_by_id'] = $userId;

        $payment->update($updateData);
        $payment->refresh();

        Log::info('Contract payment updated via service', [
            'payment_id' => $payment->id,
            'contract_id' => $contract->id,
            'tenant_id' => $payment->tenant_id,
            'amount' => $payment->amount,
            'updated_by' => $userId,
        ]);

        return $payment;
    }

    /**
     * Validate that payment total does not exceed contract total_value
     * 
     * Business Invariant:
     * - Sum of all active (non-soft-deleted) payments ≤ contract.total_value
     * - If contract.total_value is null, skip validation (contract not finalized)
     * 
     * @param Contract $contract The contract
     * @param float $newAmount The new payment amount being added/updated
     * @param ContractPayment|null $existingPayment The existing payment being updated (null for create)
     * @throws HttpResponseException If invariant is violated
     */
    protected function validatePaymentTotalInvariant(
        Contract $contract,
        float $newAmount,
        ?ContractPayment $existingPayment = null
    ): void {
        // Skip validation if contract total_value is null
        if ($contract->total_value === null) {
            return;
        }

        // Calculate current sum of all active payments (excluding soft-deleted)
        $currentSum = $contract->payments()
            ->whereNull('deleted_at')
            ->sum('amount');

        // For update: subtract existing payment amount, then add new amount
        if ($existingPayment) {
            $currentSum = $currentSum - $existingPayment->amount;
        }

        // Calculate new total
        $newTotal = $currentSum + $newAmount;

        // Check invariant: newTotal must not exceed contract.total_value
        if ($newTotal > $contract->total_value) {
            $excess = $newTotal - $contract->total_value;
            
            Log::warning('Contract payment total invariant violation', [
                'contract_id' => $contract->id,
                'contract_total_value' => $contract->total_value,
                'current_payments_sum' => $currentSum,
                'new_amount' => $newAmount,
                'new_total' => $newTotal,
                'excess' => $excess,
            ]);

            // Throw validation error with standard error envelope
            // Note: ApiResponse::error() wraps errors in ['validation' => $errors] for 422 status
            // Round 38: Add context for easier debugging and analytics
            throw new HttpResponseException(
                ApiResponse::error(
                    'Total payments exceed contract total value',
                    422,
                    [
                        'amount' => [
                            sprintf(
                                'Total payments (%.2f) would exceed contract total value (%.2f) by %.2f',
                                $newTotal,
                                $contract->total_value,
                                $excess
                            )
                        ]
                    ],
                    'PAYMENT_TOTAL_EXCEEDED',
                    [
                        'contract_id' => (string) $contract->id,
                        'current_sum' => $currentSum,
                        'new_amount' => $newAmount,
                        'new_total' => $newTotal,
                        'total_value' => $contract->total_value,
                    ]
                )
            );
        }
    }

    /**
     * Get current sum of all active payments for a contract
     * 
     * @param Contract $contract The contract
     * @return float Sum of all active payment amounts
     */
    public function getCurrentPaymentTotal(Contract $contract): float
    {
        return (float) $contract->payments()
            ->whereNull('deleted_at')
            ->sum('amount');
    }
}

