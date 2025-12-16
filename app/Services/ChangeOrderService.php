<?php declare(strict_types=1);

namespace App\Services;

use App\Models\ChangeOrder;
use App\Models\ChangeOrderLine;
use App\Models\Contract;
use App\Models\Project;
use App\Services\AuditLogService;
use App\Services\Concerns\RecordsAuditLogs;
use App\Traits\ServiceBaseTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ChangeOrderService
 * 
 * Round 220: Change Orders for Contracts
 * 
 * Handles tenant-, project-, and contract-scoped CRUD operations for change orders and their lines
 */
class ChangeOrderService
{
    use ServiceBaseTrait, RecordsAuditLogs;

    private AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * List change orders for a contract
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @param Contract $contract Contract model
     * @return Collection
     */
    public function listChangeOrdersForContract(string $tenantId, Project $project, Contract $contract): Collection
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        // Verify contract belongs to project and tenant
        if ($contract->tenant_id !== $tenantId || $contract->project_id !== $project->id) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Contract not found');
        }

        return ChangeOrder::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->where('contract_id', $contract->id)
            ->with('lines')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Create change order for a contract
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @param Contract $contract Contract model
     * @param array $data Change order data
     * @param array $lines Change order lines data (optional)
     * @return ChangeOrder
     */
    public function createChangeOrderForContract(string $tenantId, Project $project, Contract $contract, array $data, array $lines = []): ChangeOrder
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        // Verify contract belongs to project and tenant
        if ($contract->tenant_id !== $tenantId || $contract->project_id !== $project->id) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Contract not found');
        }

        return DB::transaction(function () use ($tenantId, $project, $contract, $data, $lines) {
            $data['tenant_id'] = $tenantId;
            $data['project_id'] = $project->id;
            $data['contract_id'] = $contract->id;
            $data['created_by'] = Auth::id();

            $changeOrder = ChangeOrder::create($data);

            // Create change order lines if provided
            if (!empty($lines)) {
                foreach ($lines as $lineData) {
                    $lineData['tenant_id'] = $tenantId;
                    $lineData['project_id'] = $project->id;
                    $lineData['contract_id'] = $contract->id;
                    $lineData['change_order_id'] = $changeOrder->id;
                    $lineData['created_by'] = Auth::id();

                    ChangeOrderLine::create($lineData);
                }
            }

            $this->logCrudOperation('created', $changeOrder, $data);

            // Round 235: Audit log
            $this->auditLogService->record(
                tenantId: $tenantId,
                userId: Auth::id(),
                action: 'co.created',
                entityType: 'ChangeOrder',
                entityId: $changeOrder->id,
                projectId: $project->id,
                before: null,
                after: [
                    'code' => $changeOrder->code,
                    'title' => $changeOrder->title,
                    'status' => $changeOrder->status,
                    'amount_delta' => $changeOrder->amount_delta,
                ]
            );

            return $changeOrder->load('lines');
        });
    }

    /**
     * Update change order for a contract
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @param Contract $contract Contract model
     * @param string $changeOrderId Change order ID
     * @param array $data Update data
     * @param array|null $lines Change order lines data (null = no change, [] = delete all, [...] = replace all)
     * @return ChangeOrder
     */
    public function updateChangeOrderForContract(string $tenantId, Project $project, Contract $contract, string $changeOrderId, array $data, ?array $lines = null): ChangeOrder
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        // Verify contract belongs to project and tenant
        if ($contract->tenant_id !== $tenantId || $contract->project_id !== $project->id) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Contract not found');
        }

        return DB::transaction(function () use ($tenantId, $project, $contract, $changeOrderId, $data, $lines) {
            $changeOrder = ChangeOrder::where('tenant_id', $tenantId)
                ->where('project_id', $project->id)
                ->where('contract_id', $contract->id)
                ->where('id', $changeOrderId)
                ->firstOrFail();

            $data['updated_by'] = Auth::id();

            $changeOrder->update($data);

            // Handle lines update if provided
            if ($lines !== null) {
                // Delete existing lines
                ChangeOrderLine::where('change_order_id', $changeOrder->id)->delete();

                // Create new lines
                if (!empty($lines)) {
                    foreach ($lines as $lineData) {
                        $lineData['tenant_id'] = $tenantId;
                        $lineData['project_id'] = $project->id;
                        $lineData['contract_id'] = $contract->id;
                        $lineData['change_order_id'] = $changeOrder->id;
                        $lineData['created_by'] = Auth::id();

                        ChangeOrderLine::create($lineData);
                    }
                }
            }

            $this->logCrudOperation('updated', $changeOrder, $data);

            // Round 235: Audit log
            $before = [
                'code' => $changeOrder->getOriginal('code'),
                'title' => $changeOrder->getOriginal('title'),
                'status' => $changeOrder->getOriginal('status'),
                'amount_delta' => $changeOrder->getOriginal('amount_delta'),
            ];
            $changeOrder->refresh();
            $this->auditLogService->record(
                tenantId: $tenantId,
                userId: Auth::id(),
                action: 'co.updated',
                entityType: 'ChangeOrder',
                entityId: $changeOrder->id,
                projectId: $project->id,
                before: $before,
                after: [
                    'code' => $changeOrder->code,
                    'title' => $changeOrder->title,
                    'status' => $changeOrder->status,
                    'amount_delta' => $changeOrder->amount_delta,
                ]
            );

            return $changeOrder->load('lines');
        });
    }

    /**
     * Delete change order for a contract
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @param Contract $contract Contract model
     * @param string $changeOrderId Change order ID
     * @return void
     */
    public function deleteChangeOrderForContract(string $tenantId, Project $project, Contract $contract, string $changeOrderId): void
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        // Verify contract belongs to project and tenant
        if ($contract->tenant_id !== $tenantId || $contract->project_id !== $project->id) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Contract not found');
        }

        $changeOrder = ChangeOrder::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->where('contract_id', $contract->id)
            ->where('id', $changeOrderId)
            ->firstOrFail();

        $this->logCrudOperation('deleted', $changeOrder, ['id' => $changeOrderId]);

        // Soft delete change order (lines will be cascade deleted or handled by DB constraints)
        $changeOrder->delete();
    }

    /**
     * Find change order for contract or fail
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @param Contract $contract Contract model
     * @param string $changeOrderId Change order ID
     * @return ChangeOrder
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findChangeOrderForContractOrFail(string $tenantId, Project $project, Contract $contract, string $changeOrderId): ChangeOrder
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        // Verify contract belongs to project and tenant
        if ($contract->tenant_id !== $tenantId || $contract->project_id !== $project->id) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Contract not found');
        }

        return ChangeOrder::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->where('contract_id', $contract->id)
            ->where('id', $changeOrderId)
            ->with('lines')
            ->firstOrFail();
    }
}
