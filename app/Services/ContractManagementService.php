<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractLine;
use App\Models\Project;
use App\Traits\ServiceBaseTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ContractManagementService
 * 
 * Round 219: Core Contracts & Budget (Backend-first)
 * 
 * Handles tenant- and project-scoped CRUD operations for contracts and their lines
 */
class ContractManagementService
{
    use ServiceBaseTrait;

    /**
     * List contracts for a project
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @return Collection
     */
    public function listContractsForProject(string $tenantId, Project $project): Collection
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        return Contract::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->with('lines')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Create contract for a project
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @param array $data Contract data
     * @param array $lines Contract lines data (optional)
     * @return Contract
     */
    public function createContractForProject(string $tenantId, Project $project, array $data, array $lines = []): Contract
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        return DB::transaction(function () use ($tenantId, $project, $data, $lines) {
            $data['tenant_id'] = $tenantId;
            $data['project_id'] = $project->id;
            $data['created_by_id'] = Auth::id();

            $contract = Contract::create($data);

            // Create contract lines if provided
            if (!empty($lines)) {
                foreach ($lines as $lineData) {
                    $lineData['tenant_id'] = $tenantId;
                    $lineData['contract_id'] = $contract->id;
                    $lineData['project_id'] = $project->id;
                    $lineData['created_by'] = Auth::id();

                    ContractLine::create($lineData);
                }
            }

            $this->logCrudOperation('created', $contract, $data);

            return $contract->load('lines');
        });
    }

    /**
     * Update contract for a project
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @param string $contractId Contract ID
     * @param array $data Update data
     * @param array|null $lines Contract lines data (null = no change, [] = delete all, [...] = replace all)
     * @return Contract
     */
    public function updateContractForProject(string $tenantId, Project $project, string $contractId, array $data, ?array $lines = null): Contract
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        return DB::transaction(function () use ($tenantId, $project, $contractId, $data, $lines) {
            $contract = Contract::where('tenant_id', $tenantId)
                ->where('project_id', $project->id)
                ->where('id', $contractId)
                ->firstOrFail();

            $data['updated_by_id'] = Auth::id();

            $contract->update($data);

            // Handle lines update if provided
            if ($lines !== null) {
                // Delete existing lines
                ContractLine::where('contract_id', $contract->id)->delete();

                // Create new lines
                if (!empty($lines)) {
                    foreach ($lines as $lineData) {
                        $lineData['tenant_id'] = $tenantId;
                        $lineData['contract_id'] = $contract->id;
                        $lineData['project_id'] = $project->id;
                        $lineData['created_by'] = Auth::id();

                        ContractLine::create($lineData);
                    }
                }
            }

            $this->logCrudOperation('updated', $contract, $data);

            return $contract->load('lines');
        });
    }

    /**
     * Delete contract for a project
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @param string $contractId Contract ID
     * @return void
     */
    public function deleteContractForProject(string $tenantId, Project $project, string $contractId): void
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        $contract = Contract::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->where('id', $contractId)
            ->firstOrFail();

        $this->logCrudOperation('deleted', $contract, ['id' => $contractId]);

        // Soft delete contract (lines will be cascade deleted or handled by DB constraints)
        $contract->delete();
    }
}
