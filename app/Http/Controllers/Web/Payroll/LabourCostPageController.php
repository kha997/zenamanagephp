<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use App\Services\Payroll\LabourCostReportService;
use App\Services\Payroll\PayrollService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * GAP-113 (Gate 2 D-1, V-1) — labour cost page, payroll.manage only.
 *
 * - One month's split per project and person; draft months shown as preview.
 * - Closed-month cumulative per project.
 * - CSV export.
 */
class LabourCostPageController extends Controller
{
    public function __construct(
        private readonly LabourCostReportService $reports,
        private readonly PayrollService $payroll,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($this->payroll->canManage($this->user()), 403);
        $tenantId = (string) $this->user()->tenant_id;
        /** @var \Illuminate\Database\Eloquent\Collection<int, PayrollPeriod> $periods */
        $periods = PayrollPeriod::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->orderByDesc('period')->get();
        $selected = $request->filled('period') ? $this->findPeriod((string) $request->query('period')) : $periods->first();

        return view('payroll.labour-cost', [
            'periods' => $periods,
            'period' => $selected,
            'month' => $selected === null ? null : $this->reports->month($selected),
            'cumulative' => $this->reports->cumulative($tenantId),
        ]);
    }

    public function export(string $period): Response
    {
        abort_unless($this->payroll->canManage($this->user()), 403);
        $model = $this->findPeriod($period);
        $csv = $this->reports->csv($model);

        return response()->streamDownload(static function () use ($csv): void {
            echo $csv;
        }, 'chi-phi-nhan-cong-' . $model->period . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function findPeriod(string $id): PayrollPeriod
    {
        /** @var PayrollPeriod $period */
        $period = PayrollPeriod::query()->withoutGlobalScopes()
            ->where('tenant_id', (string) $this->user()->tenant_id)
            ->whereKey($id)
            ->firstOrFail();

        return $period;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
