<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ChangeRequest;
use App\Models\MaterialReceipt;
use App\Models\MaterialRequest;
use App\Models\Rfi;
use App\Models\Submittal;
use Illuminate\Contracts\View\View;

class ProcurementDashboardController extends Controller
{
    public function __invoke(): View
    {
        $this->authorize('viewAny', MaterialRequest::class);

        $tenantId = (string) auth()->user()?->tenant_id;

        $requestCount = MaterialRequest::query()
            ->whereHas('project', function ($query) use ($tenantId): void {
                $query->where('tenant_id', $tenantId);
            })
            ->count();

        $receiptCount = MaterialReceipt::query()
            ->where('tenant_id', $tenantId)
            ->count();

        $rfiOpenCount = Rfi::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['open', 'escalated'])
            ->count();

        $rfiOverdueCount = Rfi::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['open', 'escalated'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay())
            ->count();

        $submittalPendingCount = Submittal::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['submitted', 'pending_review'])
            ->count();

        $changeRequestPendingCount = ChangeRequest::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'submitted')
            ->count();

        $recentRfis = Rfi::query()
            ->where('tenant_id', $tenantId)
            ->with('project:id,tenant_id,name')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('dashboard', [
            'requestCount' => $requestCount,
            'receiptCount' => $receiptCount,
            'rfiOpenCount' => $rfiOpenCount,
            'rfiOverdueCount' => $rfiOverdueCount,
            'submittalPendingCount' => $submittalPendingCount,
            'changeRequestPendingCount' => $changeRequestPendingCount,
            'recentRfis' => $recentRfis,
        ]);
    }
}
