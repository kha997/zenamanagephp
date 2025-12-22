<?php declare(strict_types=1);

namespace App\Services;

use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\Project;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * ChangeOrderPdfService
 * 
 * Round 228: PDF Export for Contracts, COs, and Payment Certificates
 * 
 * Generates PDF documents for change orders
 */
class ChangeOrderPdfService
{
    /**
     * Generate PDF for a change order
     * 
     * @param Project $project Project model
     * @param Contract $contract Contract model
     * @param ChangeOrder $changeOrder Change order model with lines loaded
     * @return string PDF content as string
     */
    public function generatePdf(Project $project, Contract $contract, ChangeOrder $changeOrder): string
    {
        // Ensure lines are loaded
        if (!$changeOrder->relationLoaded('lines')) {
            $changeOrder->load('lines');
        }

        // Prepare data for view
        $data = [
            'project' => $project,
            'contract' => $contract,
            'changeOrder' => $changeOrder,
            'currency' => $contract->currency ?? 'VND',
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];

        // Generate PDF using Blade template
        $pdf = Pdf::loadView('pdf.contracts.change_order', $data);
        $pdf->setPaper('A4', 'portrait');
        
        return $pdf->output();
    }
}
