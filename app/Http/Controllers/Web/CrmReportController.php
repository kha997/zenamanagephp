<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\BusinessKpiService;
use Illuminate\Contracts\View\View;

class CrmReportController extends Controller
{
    public function index(BusinessKpiService $kpiService): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        return view('crm.report', [
            'monthlyRevenue' => $kpiService->monthlyRevenue($tenantId),
            'pipelineByStage' => $kpiService->pipelineByStage($tenantId),
            'outstandingDebt' => $kpiService->outstandingDebt($tenantId),
            'salesWinRate' => $kpiService->salesWinRate($tenantId),
            'serviceCategoryPerformance' => $kpiService->serviceCategoryPerformance($tenantId),
        ]);
    }
}
