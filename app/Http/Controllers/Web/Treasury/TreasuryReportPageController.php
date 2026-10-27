<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Treasury;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Services\Treasury\TreasuryReportService;
use App\Services\Treasury\TreasuryTimelineService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GAP-097 S6 — operator pages for the project's Treasury reports and the
 * financial timeline, and the CSV exports (approved Gate 2 Option A: view
 * with treasury.view, CSV with treasury.export).
 */
class TreasuryReportPageController extends Controller
{
    public function __construct(
        private readonly TreasuryReportService $reports,
        private readonly TreasuryTimelineService $timeline,
    ) {
    }

    public function index(Request $request, string $project): View
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.view-project', $model);
        $key = array_key_exists((string) $request->query('report'), TreasuryReportService::REPORTS) ? (string) $request->query('report') : 'cashflow';

        return view('treasury.reports', [
            'project' => $model,
            'reportKey' => $key,
            'report' => $this->reports->report($model, $key),
            'canExport' => $this->user()->hasPermission('treasury.export'),
        ]);
    }

    public function timeline(Request $request, string $project): View
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.view-project', $model);
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month')) === 1 ? (string) $request->query('month') : Carbon::now()->format('Y-m');
        $start = Carbon::createFromFormat('Y-m-d', $month . '-01');

        return view('treasury.timeline', [
            'project' => $model,
            'month' => $month,
            // Codex P2 / Owner amendment 1: label from the first day, never today's day-of-month.
            'monthLabel' => $start->format('m/Y'),
            'previousMonth' => $start->copy()->subMonthNoOverflow()->format('Y-m'),
            'nextMonth' => $start->copy()->addMonthNoOverflow()->format('Y-m'),
            'events' => $this->timeline->month($model, $month),
        ]);
    }

    /** One report, or the filtered register (`register`), as CSV. */
    public function export(Request $request, string $project, string $report): StreamedResponse
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.view-project', $model);
        abort_unless($this->user()->hasPermission('treasury.export'), 403);
        if ($report === 'register') {
            $status = (string) $request->query('status', '');
            $data = $this->reports->registerReport($model, TreasuryReportService::registerFilters($request->query())
                + ['status' => in_array($status, TreasuryReportService::LEDGER_STATUSES, true) ? $status : '']);
        } else {
            abort_unless(array_key_exists($report, TreasuryReportService::REPORTS), 404);
            $data = $this->reports->report($model, $report);
        }
        $filename = 'ngan-quy-' . ($model->code ?: $model->id) . '-' . $report . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(static function () use ($data): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }
            // UTF-8 BOM so Excel renders Vietnamese correctly.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $data['headers']);
            foreach ($data['rows'] as $row) {
                fputcsv($handle, array_map([TreasuryReportService::class, 'csvValue'], $row));
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function findProject(string $id): Project
    {
        /** @var Project $project */
        $project = Project::query()
            ->where('tenant_id', (string) $this->user()->tenant_id)
            ->whereKey($id)
            ->firstOrFail();

        return $project;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
