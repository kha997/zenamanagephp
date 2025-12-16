<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\Project;
use App\Services\ChangeOrderPdfService;
use App\Services\ChangeOrderService;
use App\Services\ContractManagementService;
use App\Services\ProjectManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ChangeOrderPdfExportController
 * 
 * Round 228: PDF Export for Contracts, COs, and Payment Certificates
 * 
 * Handles PDF export for change orders
 */
class ChangeOrderPdfExportController extends BaseApiV1Controller
{
    public function __construct(
        private ChangeOrderPdfService $pdfService,
        private ChangeOrderService $changeOrderService,
        private ContractManagementService $contractService,
        private ProjectManagementService $projectService
    ) {}

    /**
     * Export change order as PDF
     * 
     * GET /api/v1/app/projects/{proj}/contracts/{contract}/change-orders/{co}/export/pdf
     */
    public function export(Request $request, string $proj, string $contract, string $co): StreamedResponse|Response
    {
        try {
            $user = $request->user();
            if (!$user || !$user->hasPermission('projects.cost.view')) {
                return response()->json(['error' => 'You do not have permission to export cost data'], 403);
            }
            
            $tenantId = $this->getTenantId();
            
            $project = $this->projectService->getProjectById($proj, $tenantId);
            if (!$project) {
                return response()->json(['error' => 'Project not found'], 404);
            }

            // Get contract
            $contracts = $this->contractService->listContractsForProject($tenantId, $project);
            $contractModel = $contracts->firstWhere('id', $contract);

            if (!$contractModel) {
                return response()->json(['error' => 'Contract not found'], 404);
            }

            // Get change order with lines
            $changeOrderModel = $this->changeOrderService->findChangeOrderForContractOrFail(
                $tenantId,
                $project,
                $contractModel,
                $co
            );

            // Authorize view access
            $this->authorize('view', $changeOrderModel);

            // Ensure lines are loaded
            if (!$changeOrderModel->relationLoaded('lines')) {
                $changeOrderModel->load('lines');
            }

            // Generate PDF
            $pdfContent = $this->pdfService->generatePdf($project, $contractModel, $changeOrderModel);
            $filename = "change-order-{$changeOrderModel->code}.pdf";

            return response()->streamDownload(function () use ($pdfContent) {
                echo $pdfContent;
            }, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Change order not found'], 404);
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'export', 'project_id' => $proj, 'contract_id' => $contract, 'change_order_id' => $co]);
            return response()->json(['error' => 'Failed to generate PDF'], 500);
        }
    }
}
