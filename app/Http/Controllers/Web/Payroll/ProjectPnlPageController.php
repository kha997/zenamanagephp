<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Payroll\OverheadPeriod;
use App\Models\User;
use App\Services\Payroll\PayrollService;
use App\Services\Payroll\ProjectPnlService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * GAP-114 (Gate 2 R-1, D-1, V-1) — project P&L page, payroll.manage only.
 *
 * - One month, preview until the overhead month is closed.
 * - Cumulative over closed months, with projected profit.
 * - CSV export.
 */
class ProjectPnlPageController extends Controller
{
    public function __construct(
        private readonly ProjectPnlService $pnl,
        private readonly PayrollService $payroll,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($this->payroll->canManage($this->user()), 403);
        $tenantId = (string) $this->user()->tenant_id;
        $month = $this->month($request, $tenantId);

        return view('payroll.pnl', [
            'month' => $month,
            'data' => $this->pnl->month($tenantId, $month),
            'cumulative' => $this->pnl->cumulative($tenantId),
        ]);
    }

    public function export(Request $request): Response
    {
        abort_unless($this->payroll->canManage($this->user()), 403);
        $tenantId = (string) $this->user()->tenant_id;
        $month = $this->month($request, $tenantId);
        $csv = $this->pnl->csv($tenantId, $month);

        return response()->streamDownload(static function () use ($csv): void {
            echo $csv;
        }, 'loi-lo-cong-trinh-' . $month . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** The requested month, else the latest closed overhead month, else the current month. */
    private function month(Request $request, string $tenantId): string
    {
        $month = (string) $request->query('month', '');
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1) {
            return $month;
        }
        $latest = OverheadPeriod::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->where('status', OverheadPeriod::STATUS_CLOSED)->max('period');

        return is_string($latest) ? $latest : now()->format('Y-m');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
