<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Contract;
use App\Models\Project;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

/**
 * ContractPdfService
 * 
 * Round 228: PDF Export for Contracts, COs, and Payment Certificates
 * 
 * Generates PDF documents for contracts
 */
class ContractPdfService
{
    /**
     * Generate PDF for a contract
     * 
     * @param Project $project Project model
     * @param Contract $contract Contract model with lines loaded
     * @return string PDF content as string
     */
    public function generatePdf(Project $project, Contract $contract): string
    {
        // Ensure lines are loaded
        if (!$contract->relationLoaded('lines')) {
            $contract->load('lines');
        }

        // Prepare data for view
        $data = [
            'project' => $project,
            'contract' => $contract,
            'currency' => $contract->currency ?? 'VND',
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];

        // Generate PDF using Blade template
        $pdf = Pdf::loadView('pdf.contracts.contract', $data);
        $pdf->setPaper('A4', 'portrait');
        
        return $pdf->output();
    }
}
