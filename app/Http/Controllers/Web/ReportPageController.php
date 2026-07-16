<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MaterialRequest;
use App\Models\Ncr;
use App\Models\Project;
use App\Models\Rfi;
use App\Models\SiteDiary;
use App\Models\Task;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportPageController extends Controller
{
    private const DATASETS = [
        'projects' => 'Dự án',
        'tasks' => 'Công việc',
        'rfis' => 'RFI',
        'material-requests' => 'Yêu cầu vật tư',
        'site-diaries' => 'Nhật ký công trường',
        'ncrs' => 'NCR',
    ];

    public function index(): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        return view('reports.index', [
            'datasets' => self::DATASETS,
            'projects' => Project::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'tenant_id', 'name', 'code']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'dataset' => ['required', 'string', 'in:' . implode(',', array_keys(self::DATASETS))],
            'project_id' => ['nullable', 'string'],
        ]);

        $tenantId = (string) auth()->user()?->tenant_id;
        $dataset = (string) $validated['dataset'];
        $projectId = (string) ($validated['project_id'] ?? '');

        [$headers, $rows] = $this->buildDataset($dataset, $tenantId, $projectId);

        $filename = $dataset . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM so Excel renders Vietnamese correctly
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, array_map(self::escapeCsvFormula(...), $row));
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Neutralize spreadsheet formula injection (=, +, -, @ prefixes).
     */
    private static function escapeCsvFormula(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * @return array{0: list<string>, 1: iterable<list<mixed>>}
     */
    private function buildDataset(string $dataset, string $tenantId, string $projectId): array
    {
        return match ($dataset) {
            'projects' => [
                ['Mã', 'Tên dự án', 'Trạng thái', 'Tiến độ (%)', 'Ngân sách', 'Chi phí thực tế', 'Bắt đầu', 'Kết thúc'],
                Project::query()
                    ->where('tenant_id', $tenantId)
                    ->orderBy('name')
                    ->lazy()
                    ->map(fn (Project $project): array => [
                        $project->code,
                        $project->name,
                        $project->status,
                        $project->progress,
                        $project->budget_total,
                        $project->actual_cost,
                        (string) $project->start_date,
                        (string) $project->end_date,
                    ]),
            ],
            'tasks' => [
                ['Tên công việc', 'Dự án', 'Trạng thái', 'Ưu tiên', 'Tiến độ (%)', 'Bắt đầu', 'Kết thúc'],
                Task::query()
                    ->where('tenant_id', $tenantId)
                    ->when($projectId !== '', fn ($q) => $q->where('project_id', $projectId))
                    ->with('project:id,name')
                    ->orderBy('start_date')
                    ->lazy()
                    ->map(fn (Task $task): array => [
                        $task->name ?? $task->title,
                        $task->project?->name,
                        $task->status,
                        $task->priority,
                        $task->progress_percent,
                        (string) $task->start_date,
                        (string) $task->end_date,
                    ]),
            ],
            'rfis' => [
                ['Số RFI', 'Tiêu đề', 'Dự án', 'Trạng thái', 'Ưu tiên', 'Ngày tạo'],
                Rfi::query()
                    ->where('tenant_id', $tenantId)
                    ->when($projectId !== '', fn ($q) => $q->where('project_id', $projectId))
                    ->with('project:id,name')
                    ->orderByDesc('created_at')
                    ->lazy()
                    ->map(fn (Rfi $rfi): array => [
                        $rfi->rfi_number,
                        $rfi->title ?? $rfi->subject,
                        $rfi->project?->name,
                        $rfi->status,
                        $rfi->priority,
                        optional($rfi->created_at)->format('Y-m-d H:i'),
                    ]),
            ],
            'material-requests' => [
                ['Mã yêu cầu', 'Dự án', 'Mô tả', 'Trạng thái', 'Chi phí dự kiến', 'Ngày cần', 'Ngày tạo'],
                MaterialRequest::query()
                    ->whereHas('project', fn ($q) => $q->where('tenant_id', $tenantId))
                    ->when($projectId !== '', fn ($q) => $q->where('project_id', $projectId))
                    ->with('project:id,tenant_id,name')
                    ->orderByDesc('created_at')
                    ->lazy()
                    ->map(fn (MaterialRequest $materialRequest): array => [
                        $materialRequest->request_number,
                        $materialRequest->project?->name,
                        $materialRequest->description,
                        $materialRequest->status,
                        $materialRequest->estimated_cost,
                        (string) $materialRequest->required_date,
                        optional($materialRequest->created_at)->format('Y-m-d H:i'),
                    ]),
            ],
            'site-diaries' => [
                ['Mã nhật ký', 'Ngày', 'Dự án', 'Nhân lực', 'Công việc', 'Thời tiết', 'Trạng thái'],
                SiteDiary::query()
                    ->forTenant($tenantId)
                    ->when($projectId !== '', fn ($q) => $q->where('project_id', $projectId))
                    ->with('project:id,name')
                    ->orderByDesc('diary_date')
                    ->lazy()
                    ->map(fn (SiteDiary $siteDiary): array => [
                        $siteDiary->diary_number,
                        optional($siteDiary->diary_date)->format('Y-m-d'),
                        $siteDiary->project?->name,
                        $siteDiary->manpower_count,
                        $siteDiary->work_performed,
                        $siteDiary->weather,
                        $siteDiary->status,
                    ]),
            ],
            'ncrs' => [
                ['Số NCR', 'Tiêu đề', 'Dự án', 'Mức độ', 'Trạng thái', 'Ngày tạo'],
                Ncr::query()
                    ->where('tenant_id', $tenantId)
                    ->when($projectId !== '', fn ($q) => $q->where('project_id', $projectId))
                    ->with('project:id,name')
                    ->orderByDesc('created_at')
                    ->lazy()
                    ->map(fn (Ncr $ncr): array => [
                        $ncr->ncr_number,
                        $ncr->title,
                        $ncr->project?->name,
                        $ncr->severity,
                        $ncr->status,
                        optional($ncr->created_at)->format('Y-m-d H:i'),
                    ]),
            ],
        };
    }
}
