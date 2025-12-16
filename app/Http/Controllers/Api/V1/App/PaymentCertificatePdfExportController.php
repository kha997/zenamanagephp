<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Models\Contract;
use App\Models\ContractPaymentCertificate;
use App\Models\Project;
use App\Services\ContractManagementService;
use App\Services\ContractPaymentService;
use App\Services\PaymentCertificatePdfService;
use App\Services\ProjectManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PaymentCertificatePdfExportController
 * 
 * Round 228: PDF Export for Contracts, COs, and Payment Certificates
 * 
 * Handles PDF export for payment certificates
 */
class PaymentCertificatePdfExportController extends BaseApiV1Controller
{
    public function __construct(
        private PaymentCertificatePdfService $pdfService,
        private ContractPaymentService $paymentService,
        private ContractManagementService $contractService,
        private ProjectManagementService $projectService
    ) {}

    /**
     * Export payment certificate as PDF
     * 
     * GET /api/v1/app/projects/{proj}/contracts/{contract}/payment-certificates/{certificate}/export/pdf
     */
    public function export(Request $request, string $proj, string $contract, string $certificate): StreamedResponse|Response
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

            // Get payment certificate
            $certificateModel = $this->paymentService->findPaymentCertificateForContractOrFail(
                $tenantId,
                $project,
                $contractModel,
                $certificate
            );

            // Authorize view access
            $this->authorize('view', $certificateModel);

            // Generate PDF
            $pdfContent = $this->pdfService->generatePdf($project, $contractModel, $certificateModel);
            $filename = "payment-certificate-{$certificateModel->code}.pdf";

            return response()->streamDownload(function () use ($pdfContent) {
                echo $pdfContent;
            }, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Payment certificate not found'], 404);
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'export', 'project_id' => $proj, 'contract_id' => $contract, 'certificate_id' => $certificate]);
            return response()->json(['error' => 'Failed to generate PDF'], 500);
        }
    }
}
