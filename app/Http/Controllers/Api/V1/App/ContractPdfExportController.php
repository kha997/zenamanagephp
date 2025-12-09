<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Models\Contract;
use App\Models\Project;
use App\Services\ContractManagementService;
use App\Services\ContractPdfService;
use App\Services\ProjectManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ContractPdfExportController
 * 
 * Round 228: PDF Export for Contracts, COs, and Payment Certificates
 * 
 * Handles PDF export for contracts
 */
class ContractPdfExportController extends BaseApiV1Controller
{
    public function __construct(
        private ContractPdfService $pdfService,
        private ContractManagementService $contractService,
        private ProjectManagementService $projectService
    ) {}

    /**
     * Export contract as PDF
     * 
     * GET /api/v1/app/projects/{proj}/contracts/{contract}/export/pdf
     */
    public function export(Request $request, string $proj, string $contract): StreamedResponse|Response
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

            // Get contract with lines
            $contracts = $this->contractService->listContractsForProject($tenantId, $project);
            $contractModel = $contracts->firstWhere('id', $contract);

            if (!$contractModel) {
                return response()->json(['error' => 'Contract not found'], 404);
            }

            // Authorize view access
            $this->authorize('view', $contractModel);

            // Ensure lines are loaded
            if (!$contractModel->relationLoaded('lines')) {
                $contractModel->load('lines');
            }

            // Generate PDF
            $pdfContent = $this->pdfService->generatePdf($project, $contractModel);
            $filename = "contract-{$contractModel->code}.pdf";

            return response()->streamDownload(function () use ($pdfContent) {
                echo $pdfContent;
            }, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'export', 'project_id' => $proj, 'contract_id' => $contract]);
            return response()->json(['error' => 'Failed to generate PDF'], 500);
        }
    }
}
