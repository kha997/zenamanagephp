<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Payroll\OverheadAllocation;
use App\Models\Payroll\OverheadCostLine;
use App\Models\Payroll\OverheadPeriod;
use App\Models\User;
use App\Services\Payroll\OverheadService;
use App\Services\Payroll\PayrollRuleViolation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * GAP-114 (Gate 2 N-1, C-1, P-1, A-1) — monthly overhead page.
 *
 * - payroll.manage enters lines; payroll.close closes / reopens.
 * - A draft month shows the split as preview; a closed month shows the stored split.
 * - Rules live in OverheadService.
 */
class OverheadPageController extends Controller
{
    public function __construct(private readonly OverheadService $overhead)
    {
    }

    public function index(Request $request): View
    {
        abort_unless($this->overhead->canManage($this->user()), 403);
        $month = (string) $request->query('month', now()->format('Y-m'));
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            $month = now()->format('Y-m');
        }
        $tenantId = (string) $this->user()->tenant_id;
        $period = $this->overhead->periodFor($tenantId, $month);
        $summary = $this->overhead->compute($period ?? new OverheadPeriod(['tenant_id' => $tenantId, 'period' => $month]));
        if ($period !== null && $period->isClosed()) {
            $summary['rows'] = OverheadAllocation::query()->withoutGlobalScopes()->where('overhead_period_id', (string) $period->id)->get()
                ->map(static fn (OverheadAllocation $row): array => ['project_id' => $row->project_id, 'minutes' => $row->minutes, 'amount' => (string) $row->amount])
                ->values()->all();
        }
        $ids = array_values(array_filter(array_column($summary['rows'], 'project_id')));

        return view('payroll.overhead', [
            'month' => $month,
            'period' => $period,
            'lines' => $period === null ? collect() : $this->overhead->linesOf($period),
            'summary' => $summary,
            'projects' => $ids === [] ? [] : DB::table('projects')->where('tenant_id', $tenantId)->whereIn('id', $ids)->pluck('name', 'id')->all(),
            'categories' => OverheadCostLine::CATEGORIES,
            'canClose' => $this->overhead->canClose($this->user()),
        ]);
    }

    public function storeLine(Request $request): RedirectResponse
    {
        abort_unless($this->overhead->canManage($this->user()), 403);
        $month = (string) $request->input('month', '');

        return $this->attempt($month, fn () => $this->overhead->addLine($this->user(), $month, $request->only(['category', 'description', 'amount'])), 'Đã thêm chi phí.');
    }

    public function updateLine(Request $request, string $line): RedirectResponse
    {
        abort_unless($this->overhead->canManage($this->user()), 403);
        $model = $this->findLine($line);

        return $this->attempt($this->monthOf((string) $model->overhead_period_id), fn () => $this->overhead->updateLine($this->user(), $model, $request->only(['category', 'description', 'amount'])), 'Đã lưu chi phí.');
    }

    public function deleteLine(string $line): RedirectResponse
    {
        abort_unless($this->overhead->canManage($this->user()), 403);
        $model = $this->findLine($line);

        return $this->attempt($this->monthOf((string) $model->overhead_period_id), fn () => $this->overhead->deleteLine($this->user(), $model), 'Đã xoá chi phí.');
    }

    public function close(string $period): RedirectResponse
    {
        abort_unless($this->overhead->canClose($this->user()), 403);
        $model = $this->findPeriod($period);

        return $this->attempt($model->period, fn () => $this->overhead->close($this->user(), $model), 'Đã chốt chi phí chung.');
    }

    public function closeMonth(string $month): RedirectResponse
    {
        abort_unless($this->overhead->canClose($this->user()), 403);

        return $this->attempt($month, fn () => $this->overhead->closeMonth($this->user(), $month), 'Đã chốt chi phí chung.');
    }

    public function reopen(Request $request, string $period): RedirectResponse
    {
        abort_unless($this->overhead->canClose($this->user()), 403);
        $model = $this->findPeriod($period);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->attempt($model->period, fn () => $this->overhead->reopen($this->user(), $model, (string) $data['reason']), 'Đã mở lại chi phí chung.');
    }

    private function attempt(string $month, callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (PayrollRuleViolation $e) {
            return back()->withInput()->withErrors([$e->field ?? 'period' => $e->getMessage()]);
        }

        return redirect()->route('operator.payroll.overhead', ['month' => $month])->with('success', $message);
    }

    private function monthOf(string $periodId): string
    {
        return $this->findPeriod($periodId)->period;
    }

    private function findPeriod(string $id): OverheadPeriod
    {
        /** @var OverheadPeriod $period */
        $period = OverheadPeriod::query()->withoutGlobalScopes()->where('tenant_id', (string) $this->user()->tenant_id)->whereKey($id)->firstOrFail();

        return $period;
    }

    private function findLine(string $id): OverheadCostLine
    {
        /** @var OverheadCostLine $line */
        $line = OverheadCostLine::query()->withoutGlobalScopes()->where('tenant_id', (string) $this->user()->tenant_id)->whereKey($id)->firstOrFail();

        return $line;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
