<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Payroll\PayrollLine;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use App\Services\Payroll\LabourCostReportService;
use App\Services\Payroll\PayrollRuleViolation;
use App\Services\Payroll\PayrollService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * GAP-109 (Gate 2 C-1, B-1, H-1, L-1, P-1, Q-1) — monthly payroll pages: list and create months, enter lines, close /
 * reopen (payroll.close), mark paid, CSV export. Rules live in PayrollService. GAP-113 H-1: the month page lists hours
 * still running / waiting and hides the close button while there are any.
 */
class PayrollPageController extends Controller
{
    public function __construct(
        private readonly PayrollService $payroll,
        private readonly LabourCostReportService $labour,
    ) {
    }

    public function index(): View
    {
        abort_unless($this->payroll->canManage($this->user()), 403);
        /** @var \Illuminate\Database\Eloquent\Collection<int, PayrollPeriod> $periods */
        $periods = PayrollPeriod::query()->where('tenant_id', (string) $this->user()->tenant_id)->orderByDesc('period')->get();
        $totals = [];
        foreach ($periods as $period) {
            $totals[(string) $period->id] = $this->payroll->totals($period);
        }

        return view('payroll.index', [
            'periods' => $periods,
            'totals' => $totals,
            'nextPeriod' => now()->format('Y-m'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->payroll->canManage($this->user()), 403);
        $data = $request->validate([
            'period' => ['required', 'string'],
            'working_days' => ['required', 'integer'],
            'hours_per_day' => ['nullable', 'numeric'],
        ]);

        try {
            $period = $this->payroll->createPeriod($this->user(), (string) $data['period'], (int) $data['working_days'], (string) ($data['hours_per_day'] ?? '8'));
        } catch (PayrollRuleViolation $e) {
            return back()->withInput()->withErrors([$e->field ?? 'period' => $e->getMessage()]);
        }

        return redirect()->route('operator.payroll.periods.show', $period->id)->with('success', 'Đã tạo bảng lương tháng.');
    }

    public function show(string $period): View
    {
        abort_unless($this->payroll->canManage($this->user()), 403);
        $model = $this->findPeriod($period);

        return view('payroll.period', [
            'period' => $model,
            'lines' => $this->payroll->linesOf($model),
            'totals' => $this->payroll->totals($model),
            'canClose' => $this->payroll->canClose($this->user()),
            // GAP-113 H-1: running / waiting hours block the close.
            'pendingHours' => $model->isClosed() ? [] : $this->labour->pendingByPerson($model),
        ]);
    }

    public function updateSettings(Request $request, string $period): RedirectResponse
    {
        abort_unless($this->payroll->canManage($this->user()), 403);
        $model = $this->findPeriod($period);
        $data = $request->validate(['working_days' => ['required', 'integer'], 'hours_per_day' => ['required', 'numeric']]);

        return $this->attempt($model, fn () => $this->payroll->updatePeriod($this->user(), $model, (int) $data['working_days'], (string) $data['hours_per_day']), 'Đã cập nhật ngày công chuẩn.');
    }

    public function updateLine(Request $request, string $line): RedirectResponse
    {
        abort_unless($this->payroll->canManage($this->user()), 403);
        $model = $this->findLine($line);
        $data = $request->only([...PayrollLine::INPUT_COLUMNS, 'note']);
        $period = $this->findPeriod((string) $model->payroll_period_id);

        return $this->attempt($period, fn () => $this->payroll->updateLine($this->user(), $model, $data), 'Đã lưu dòng lương.');
    }

    public function markPaid(Request $request, string $line): RedirectResponse
    {
        abort_unless($this->payroll->canManage($this->user()), 403);
        $model = $this->findLine($line);
        $data = $request->validate(['paid_at' => ['required', 'date_format:Y-m-d'], 'payment_reference' => ['nullable', 'string', 'max:100']]);
        $period = $this->findPeriod((string) $model->payroll_period_id);

        return $this->attempt($period, fn () => $this->payroll->markPaid($this->user(), $model, (string) $data['paid_at'], (string) ($data['payment_reference'] ?? '')), 'Đã ghi nhận trả lương.');
    }

    public function close(string $period): RedirectResponse
    {
        abort_unless($this->payroll->canClose($this->user()), 403);
        $model = $this->findPeriod($period);

        return $this->attempt($model, fn () => $this->payroll->close($this->user(), $model), 'Đã chốt bảng lương.');
    }

    public function reopen(Request $request, string $period): RedirectResponse
    {
        abort_unless($this->payroll->canClose($this->user()), 403);
        $model = $this->findPeriod($period);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->attempt($model, fn () => $this->payroll->reopen($this->user(), $model, (string) $data['reason']), 'Đã mở lại bảng lương.');
    }

    public function export(string $period): Response
    {
        abort_unless($this->payroll->canManage($this->user()), 403);
        $model = $this->findPeriod($period);
        $csv = $this->payroll->csv($model);

        return response()->streamDownload(static function () use ($csv): void {
            echo $csv;
        }, 'bang-luong-' . $model->period . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function attempt(PayrollPeriod $period, callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (PayrollRuleViolation $e) {
            return back()->withInput()->withErrors([$e->field ?? 'period' => $e->getMessage()]);
        }

        return redirect()->route('operator.payroll.periods.show', $period->id)->with('success', $message);
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

    private function findLine(string $id): PayrollLine
    {
        /** @var PayrollLine $line */
        $line = PayrollLine::query()->withoutGlobalScopes()
            ->where('tenant_id', (string) $this->user()->tenant_id)
            ->whereKey($id)
            ->firstOrFail();

        return $line;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
