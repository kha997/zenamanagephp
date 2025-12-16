<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractActualPayment;
use App\Models\ContractPaymentCertificate;
use App\Models\Project;
use App\Traits\ServiceBaseTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ContractPaymentService
 * 
 * Round 221: Payment Certificates & Payments (Actual Cost)
 * 
 * Handles tenant-, project-, and contract-scoped CRUD operations for payment certificates and actual payments
 */
class ContractPaymentService
{
    use ServiceBaseTrait;

    // ==================== Payment Certificates ====================

    /**
     * List payment certificates for a contract
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @param Contract $contract Contract model
     * @return Collection
     */
    public function listPaymentCertificatesForContract(string $tenantId, Project $project, Contract $contract): Collection
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        // Verify contract belongs to project and tenant
        if ($contract->tenant_id !== $tenantId || $contract->project_id !== $project->id) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Contract not found');
        }

        return ContractPaymentCertificate::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->where('contract_id', $contract->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Create payment certificate for a contract
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @param Contract $contract Contract model
     * @param array $data Certificate data
     * @return ContractPaymentCertificate
     */
    public function createPaymentCertificateForContract(string $tenantId, Project $project, Contract $contract, array $data): ContractPaymentCertificate
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        // Verify contract belongs to project and tenant
        if ($contract->tenant_id !== $tenantId || $contract->project_id !== $project->id) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Contract not found');
        }

        return DB::transaction(function () use ($tenantId, $project, $contract, $data) {
            $data['tenant_id'] = $tenantId;
            $data['project_id'] = $project->id;
            $data['contract_id'] = $contract->id;
            $data['created_by'] = Auth::id();

            $certificate = ContractPaymentCertificate::create($data);

            $this->logCrudOperation('created', $certificate, $data);

            return $certificate;
        });
    }

    /**
     * Update payment certificate for a contract
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @param Contract $contract Contract model
     * @param string $certificateId Certificate ID
     * @param array $data Update data
     * @return ContractPaymentCertificate
     */
    public function updatePaymentCertificateForContract(string $tenantId, Project $project, Contract $contract, string $certificateId, array $data): ContractPaymentCertificate
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        // Verify contract belongs to project and tenant
        if ($contract->tenant_id !== $tenantId || $contract->project_id !== $project->id) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Contract not found');
        }

        return DB::transaction(function () use ($tenantId, $project, $contract, $certificateId, $data) {
            $certificate = ContractPaymentCertificate::where('tenant_id', $tenantId)
                ->where('project_id', $project->id)
                ->where('contract_id', $contract->id)
                ->where('id', $certificateId)
                ->firstOrFail();

            $data['updated_by'] = Auth::id();

            $certificate->update($data);

            $this->logCrudOperation('updated', $certificate, $data);

            return $certificate;
        });
    }

    /**
     * Delete payment certificate for a contract
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @param Contract $contract Contract model
     * @param string $certificateId Certificate ID
     * @return void
     */
    public function deletePaymentCertificateForContract(string $tenantId, Project $project, Contract $contract, string $certificateId): void
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        // Verify contract belongs to project and tenant
        if ($contract->tenant_id !== $tenantId || $contract->project_id !== $project->id) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Contract not found');
        }

        $certificate = ContractPaymentCertificate::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->where('contract_id', $contract->id)
            ->where('id', $certificateId)
            ->firstOrFail();

        $this->logCrudOperation('deleted', $certificate, ['id' => $certificateId]);

        // Soft delete certificate
        $certificate->delete();
    }

    /**
     * Find payment certificate for contract or fail
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @param Contract $contract Contract model
     * @param string $certificateId Certificate ID
     * @return ContractPaymentCertificate
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findPaymentCertificateForContractOrFail(string $tenantId, Project $project, Contract $contract, string $certificateId): ContractPaymentCertificate
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        // Verify contract belongs to project and tenant
        if ($contract->tenant_id !== $tenantId || $contract->project_id !== $project->id) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Contract not found');
        }

        return ContractPaymentCertificate::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->where('contract_id', $contract->id)
            ->where('id', $certificateId)
            ->firstOrFail();
    }

    // ==================== Actual Payments ====================

    /**
     * List payments for a contract
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @param Contract $contract Contract model
     * @return Collection
     */
    public function listPaymentsForContract(string $tenantId, Project $project, Contract $contract): Collection
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        // Verify contract belongs to project and tenant
        if ($contract->tenant_id !== $tenantId || $contract->project_id !== $project->id) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Contract not found');
        }

        return ContractActualPayment::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->where('contract_id', $contract->id)
            ->orderBy('paid_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Create payment for a contract
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @param Contract $contract Contract model
     * @param array $data Payment data
     * @return ContractActualPayment
     */
    public function createPaymentForContract(string $tenantId, Project $project, Contract $contract, array $data): ContractActualPayment
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        // Verify contract belongs to project and tenant
        if ($contract->tenant_id !== $tenantId || $contract->project_id !== $project->id) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Contract not found');
        }

        // If certificate_id is provided, verify it belongs to the same contract
        if (isset($data['certificate_id']) && $data['certificate_id']) {
            $certificate = ContractPaymentCertificate::where('tenant_id', $tenantId)
                ->where('project_id', $project->id)
                ->where('contract_id', $contract->id)
                ->where('id', $data['certificate_id'])
                ->first();

            if (!$certificate) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Certificate not found or does not belong to this contract');
            }
        }

        return DB::transaction(function () use ($tenantId, $project, $contract, $data) {
            $data['tenant_id'] = $tenantId;
            $data['project_id'] = $project->id;
            $data['contract_id'] = $contract->id;
            $data['created_by'] = Auth::id();

            $payment = ContractActualPayment::create($data);

            $this->logCrudOperation('created', $payment, $data);

            return $payment;
        });
    }

    /**
     * Update payment for a contract
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @param Contract $contract Contract model
     * @param string $paymentId Payment ID
     * @param array $data Update data
     * @return ContractActualPayment
     */
    public function updatePaymentForContract(string $tenantId, Project $project, Contract $contract, string $paymentId, array $data): ContractActualPayment
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        // Verify contract belongs to project and tenant
        if ($contract->tenant_id !== $tenantId || $contract->project_id !== $project->id) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Contract not found');
        }

        // If certificate_id is being updated, verify it belongs to the same contract
        if (isset($data['certificate_id']) && $data['certificate_id']) {
            $certificate = ContractPaymentCertificate::where('tenant_id', $tenantId)
                ->where('project_id', $project->id)
                ->where('contract_id', $contract->id)
                ->where('id', $data['certificate_id'])
                ->first();

            if (!$certificate) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Certificate not found or does not belong to this contract');
            }
        }

        return DB::transaction(function () use ($tenantId, $project, $contract, $paymentId, $data) {
            $payment = ContractActualPayment::where('tenant_id', $tenantId)
                ->where('project_id', $project->id)
                ->where('contract_id', $contract->id)
                ->where('id', $paymentId)
                ->firstOrFail();

            $data['updated_by'] = Auth::id();

            $payment->update($data);

            $this->logCrudOperation('updated', $payment, $data);

            return $payment;
        });
    }

    /**
     * Delete payment for a contract
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @param Contract $contract Contract model
     * @param string $paymentId Payment ID
     * @return void
     */
    public function deletePaymentForContract(string $tenantId, Project $project, Contract $contract, string $paymentId): void
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        // Verify contract belongs to project and tenant
        if ($contract->tenant_id !== $tenantId || $contract->project_id !== $project->id) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Contract not found');
        }

        $payment = ContractActualPayment::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->where('contract_id', $contract->id)
            ->where('id', $paymentId)
            ->firstOrFail();

        $this->logCrudOperation('deleted', $payment, ['id' => $paymentId]);

        // Soft delete payment
        $payment->delete();
    }

    /**
     * Find payment for contract or fail
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @param Contract $contract Contract model
     * @param string $paymentId Payment ID
     * @return ContractActualPayment
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findPaymentForContractOrFail(string $tenantId, Project $project, Contract $contract, string $paymentId): ContractActualPayment
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        // Verify contract belongs to project and tenant
        if ($contract->tenant_id !== $tenantId || $contract->project_id !== $project->id) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Contract not found');
        }

        return ContractActualPayment::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->where('contract_id', $contract->id)
            ->where('id', $paymentId)
            ->firstOrFail();
    }
}
