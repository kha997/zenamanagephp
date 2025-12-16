<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractPaymentCertificate;
use App\Models\Project;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * PaymentCertificatePdfService
 * 
 * Round 228: PDF Export for Contracts, COs, and Payment Certificates
 * 
 * Generates PDF documents for payment certificates
 */
class PaymentCertificatePdfService
{
    /**
     * Generate PDF for a payment certificate
     * 
     * @param Project $project Project model
     * @param Contract $contract Contract model
     * @param ContractPaymentCertificate $certificate Payment certificate model
     * @return string PDF content as string
     */
    public function generatePdf(Project $project, Contract $contract, ContractPaymentCertificate $certificate): string
    {
        // Prepare data for view
        $data = [
            'project' => $project,
            'contract' => $contract,
            'certificate' => $certificate,
            'currency' => $contract->currency ?? 'VND',
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];

        // Generate PDF using Blade template
        $pdf = Pdf::loadView('pdf.contracts.payment_certificate', $data);
        $pdf->setPaper('A4', 'portrait');
        
        return $pdf->output();
    }
}
