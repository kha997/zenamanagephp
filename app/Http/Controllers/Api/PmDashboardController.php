<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ZenaContractResponseTrait;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Rfi;
use App\Models\Task;
use App\Models\User;
use App\Models\UserRoleProject;
use App\Services\ErrorEnvelopeService;
use App\Support\Dashboard\Availability;
use App\Support\Dashboard\Freshness;
use App\Support\Dashboard\MetricGuard;
use App\Support\Dashboard\MetricResult;
use App\Support\Dashboard\Reliability;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PmDashboardController extends Controller
{
    use ZenaContractResponseTrait;

    private function authenticatedUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    public function getOverview(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser();

        $projectIds = $this->actorProjectIds($user, $request->input('project_id'));

        $projects = Project::whereIn('id', $projectIds)->get();

        $tasks = Task::whereIn('project_id', $projectIds)->get();

        $overdueCount = Task::whereIn('project_id', $projectIds)
            ->where('end_date', '<', now())
            ->whereNotIn('status', [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED])
            ->count();

        $pendingRfis = Rfi::whereIn('project_id', $projectIds)
            ->where('tenant_id', (string) $user->tenant_id)
            ->where('status', 'pending')
            ->count();

        return $this->zenaSuccessResponse([
            'pm_widget' => [
                'widget_key' => 'pm_summary',
                'projects' => [
                    'total' => $projects->count(),
                    'active' => $projects->where('status', Project::STATUS_ACTIVE)->count(),
                    'completed' => $projects->where('status', Project::STATUS_COMPLETED)->count(),
                ],
                'tasks' => [
                    'total' => $tasks->count(),
                    'completed' => $tasks->where('status', Task::STATUS_COMPLETED)->count(),
                    'overdue' => $overdueCount,
                ],
                'rfis' => [
                    'pending' => $pendingRfis,
                ],
            ],
        ]);
    }

    public function getProjectProgress(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser();

        $projectId = $request->input('project_id');

        if (!$projectId) {
            return response()->json(['status' => 'error', 'message' => 'Project ID is required'], 400);
        }

        $accessible = UserRoleProject::where('user_id', (string) $user->id)
            ->where('project_id', $projectId)
            ->exists();

        if (!$accessible) {
            return response()->json(['status' => 'error', 'message' => 'Project not found or access denied'], 404);
        }

        $project = Project::find($projectId);

        if (!$project) {
            return response()->json(['status' => 'error', 'message' => 'Project not found or access denied'], 404);
        }

        $taskMetrics = $this->computeTaskMetrics($projectId);
        $milestoneMetrics = $this->computeMilestoneMetrics($projectId);

        return $this->zenaSuccessResponse([
            'project' => [
                'id' => (string) $project->id,
                'name' => $project->name,
                'status' => $project->status,
            ],
            'overall_progress' => $taskMetrics['overall_progress'],
            'overall_progress_meta' => $taskMetrics['overall_progress_meta']->toArray(),
            'task_progress' => $taskMetrics['task_progress'],
            'milestone_progress' => $milestoneMetrics['milestone_progress'],
            'milestone_progress_meta' => $milestoneMetrics['milestone_progress_meta']->toArray(),
            'budget_progress' => $this->computeBudgetProgress($project),
            'budget_progress_meta' => $this->computeBudgetProgressMeta($project)->toArray(),
            'timeline_progress' => $this->computeTimelineProgress($project),
            'timeline_progress_meta' => $this->computeTimelineProgressMeta($project)->toArray(),
        ]);
    }

    private function actorProjectIds($user, ?string $projectIdFilter): array
    {
        $query = UserRoleProject::where('user_id', (string) $user->id);

        if ($projectIdFilter !== null) {
            $query->where('project_id', $projectIdFilter);
        }

        return $query->pluck('project_id')->all();
    }

    /**
     * Tính overall_progress + overall_progress_meta + task_progress từ MỘT lần
     * fetch Task duy nhất (P2-A): tránh chạy 3 câu Task::query() độc lập (2 cho
     * legacy value + 1 cho meta) khiến response 500 khi truy vấn Task lỗi mà
     * legacy field không đi qua guard.
     *
     * @return array{overall_progress: float, overall_progress_meta: MetricResult, task_progress: array<string, int>}
     */
    private function computeTaskMetrics(string $projectId): array
    {
        $label = 'Tiến độ công việc (Task)';
        $emptyBreakdown = ['total' => 0, 'completed' => 0, 'in_progress' => 0, 'pending' => 0, 'overdue' => 0];

        try {
            $tasks = Task::query()->where('project_id', $projectId)->get();
        } catch (\Throwable $e) {
            Log::error('dashboard_metric_error', [
                'project_id' => $projectId,
                'tenant_id' => (string) $this->authenticatedUser()->tenant_id,
                'widget' => 'overall_progress',
                'request_id' => ErrorEnvelopeService::getCurrentRequestId(),
                'exception' => $e->getMessage(),
                'exception_class' => $e::class,
            ]);

            return [
                'overall_progress' => 0.0,
                'overall_progress_meta' => new MetricResult(
                    value: null,
                    availability: Availability::ERROR,
                    reliability: Reliability::UNKNOWN,
                    freshness: Freshness::UNKNOWN,
                    asOf: null,
                    label: $label,
                    explanation: "Không thể tính được \"{$label}\" do lỗi truy vấn dữ liệu.",
                ),
                'task_progress' => $emptyBreakdown,
            ];
        }

        $total = $tasks->count();
        $overdue = $tasks->filter(
            fn ($t) => $t->end_date !== null
                && $t->end_date < now()
                && !in_array($t->status, [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED], true)
        )->count();

        $taskProgress = [
            'total' => $total,
            'completed' => $tasks->where('status', Task::STATUS_COMPLETED)->count(),
            'in_progress' => $tasks->where('status', Task::STATUS_IN_PROGRESS)->count(),
            'pending' => $tasks->where('status', Task::STATUS_PENDING)->count(),
            'overdue' => $overdue,
        ];

        if ($total === 0) {
            return [
                'overall_progress' => 0.0,
                'overall_progress_meta' => new MetricResult(
                    value: null,
                    availability: Availability::NO_DATA,
                    reliability: Reliability::RELIABLE,
                    freshness: Freshness::UNKNOWN,
                    asOf: null,
                    label: $label,
                    explanation: 'Dự án chưa có công việc (Task) nào được tạo.',
                ),
                'task_progress' => $taskProgress,
            ];
        }

        $value = round(($taskProgress['completed'] / $total) * 100, 2);
        $asOf = $tasks->max('updated_at');

        return [
            'overall_progress' => $value,
            'overall_progress_meta' => new MetricResult(
                value: $value,
                availability: Availability::AVAILABLE,
                reliability: Reliability::RELIABLE,
                freshness: Freshness::UNKNOWN,
                asOf: $asOf ? Carbon::parse($asOf) : null,
                label: $label,
                explanation: null,
            ),
            'task_progress' => $taskProgress,
        ];
    }

    /**
     * Tính milestone_progress + milestone_progress_meta từ MỘT lần fetch
     * ProjectMilestone duy nhất (P2-A) — cùng lý do với computeTaskMetrics().
     *
     * @return array{milestone_progress: array<string, mixed>, milestone_progress_meta: MetricResult}
     */
    private function computeMilestoneMetrics(string $projectId): array
    {
        $label = 'Tỷ lệ hoàn thành mốc tiến độ';
        $emptyBreakdown = [
            'total_milestones' => 0,
            'completed_milestones' => 0,
            'pending_milestones' => 0,
            'overdue_milestones' => 0,
            'completion_rate' => 0.0,
            'upcoming_milestones' => [],
        ];

        try {
            $milestones = ProjectMilestone::query()->where('project_id', $projectId)->get();
        } catch (\Throwable $e) {
            Log::error('dashboard_metric_error', [
                'project_id' => $projectId,
                'tenant_id' => (string) $this->authenticatedUser()->tenant_id,
                'widget' => 'milestone_progress',
                'request_id' => ErrorEnvelopeService::getCurrentRequestId(),
                'exception' => $e->getMessage(),
                'exception_class' => $e::class,
            ]);

            return [
                'milestone_progress' => $emptyBreakdown,
                'milestone_progress_meta' => new MetricResult(
                    value: null,
                    availability: Availability::ERROR,
                    reliability: Reliability::UNKNOWN,
                    freshness: Freshness::UNKNOWN,
                    asOf: null,
                    label: $label,
                    explanation: "Không thể tính được \"{$label}\" do lỗi truy vấn dữ liệu.",
                ),
            ];
        }

        $total = $milestones->count();
        $completed = $milestones->where('status', ProjectMilestone::STATUS_COMPLETED)->count();
        $pending = $milestones->where('status', ProjectMilestone::STATUS_PENDING)->count();
        $overdue = $milestones->where('status', ProjectMilestone::STATUS_OVERDUE)->count();

        $upcoming = $milestones
            ->whereNotIn('status', [ProjectMilestone::STATUS_COMPLETED])
            ->sortBy('target_date')
            ->values()
            ->map(fn ($m) => [
                'id' => (string) $m->id,
                'name' => $m->name,
                'status' => $m->status,
                'target_date' => $m->target_date,
            ])
            ->values();

        if ($total === 0) {
            return [
                'milestone_progress' => array_merge($emptyBreakdown, ['upcoming_milestones' => $upcoming]),
                'milestone_progress_meta' => new MetricResult(
                    value: null,
                    availability: Availability::NO_DATA,
                    reliability: Reliability::LEGACY,
                    freshness: Freshness::UNKNOWN,
                    asOf: null,
                    label: $label,
                    explanation: 'Dự án chưa có mốc tiến độ (Milestone) nào được tạo. Nguồn dữ liệu này không còn kênh cập nhật chính thức.',
                ),
            ];
        }

        $value = round(($completed / $total) * 100, 2);
        $asOf = $milestones->max('updated_at');

        return [
            'milestone_progress' => [
                'total_milestones' => $total,
                'completed_milestones' => $completed,
                'pending_milestones' => $pending,
                'overdue_milestones' => $overdue,
                'completion_rate' => $value,
                'upcoming_milestones' => $upcoming,
            ],
            'milestone_progress_meta' => new MetricResult(
                value: $value,
                availability: Availability::AVAILABLE,
                reliability: Reliability::LEGACY,
                freshness: Freshness::UNKNOWN,
                asOf: $asOf ? Carbon::parse($asOf) : null,
                label: $label,
                explanation: 'Dữ liệu lịch sử — không còn kênh cập nhật chính thức.',
            ),
        ];
    }

    private function computeBudgetProgress(Project $project): array
    {
        $total = (float) ($project->budget_total ?? 0);
        $spent = (float) ($project->budget_actual ?? 0);
        $remaining = $total - $spent;
        $pct = $total > 0 ? round(($spent / $total) * 100, 2) : 0;

        return [
            'total_budget' => $total,
            'spent_amount' => $spent,
            'remaining_amount' => $remaining,
            'percentage_spent' => $pct,
        ];
    }

    private function computeBudgetProgressMeta(Project $project): MetricResult
    {
        $label = 'Tỷ lệ ngân sách đã chi';

        return MetricGuard::wrap(
            'budget_progress',
            ['project_id' => (string) $project->id, 'tenant_id' => (string) $this->authenticatedUser()->tenant_id],
            $label,
            function () use ($project, $label) {
                $total = (float) ($project->budget_total ?? 0);

                if ($total <= 0) {
                    return new MetricResult(
                        value: null,
                        availability: Availability::NOT_APPLICABLE,
                        reliability: Reliability::RELIABLE,
                        freshness: Freshness::UNKNOWN,
                        asOf: null,
                        label: $label,
                        explanation: 'Dự án chưa nhập ngân sách.',
                    );
                }

                $spent = (float) ($project->budget_actual ?? 0);
                $value = round(($spent / $total) * 100, 2);

                return new MetricResult(
                    value: $value,
                    availability: Availability::AVAILABLE,
                    reliability: Reliability::RELIABLE,
                    freshness: Freshness::UNKNOWN,
                    asOf: $project->updated_at,
                    label: $label,
                    explanation: null,
                );
            },
        );
    }

    private function computeTimelineProgress(Project $project): array
    {
        if (!$project->start_date || !$project->end_date) {
            return [
                'start_date' => null,
                'end_date' => null,
                'days_elapsed' => 0,
                'total_days' => 0,
                'percentage_elapsed' => 0,
            ];
        }

        $start = $project->start_date;
        $end = $project->end_date;
        $now = now()->startOfDay();

        $totalDays = (int) $start->diffInDays($end);
        $elapsedDays = (int) $start->diffInDays($now);
        $pct = $totalDays > 0 ? round(min(($elapsedDays / $totalDays) * 100, 100), 2) : 0;

        return [
            'start_date' => $start,
            'end_date' => $end,
            'days_elapsed' => $elapsedDays,
            'total_days' => $totalDays,
            'percentage_elapsed' => $pct,
        ];
    }

    private function computeTimelineProgressMeta(Project $project): MetricResult
    {
        $label = 'Tỷ lệ thời gian kế hoạch đã trôi qua';

        return MetricGuard::wrap(
            'timeline_progress',
            ['project_id' => (string) $project->id, 'tenant_id' => (string) $this->authenticatedUser()->tenant_id],
            $label,
            function () use ($project, $label) {
                if (!$project->start_date || !$project->end_date) {
                    return new MetricResult(
                        value: null,
                        availability: Availability::NOT_APPLICABLE,
                        reliability: Reliability::RELIABLE,
                        freshness: Freshness::UNKNOWN,
                        asOf: null,
                        label: $label,
                        explanation: 'Dự án chưa nhập đủ ngày bắt đầu/kết thúc kế hoạch.',
                    );
                }

                $start = $project->start_date;
                $end = $project->end_date;
                $now = now()->startOfDay();

                $totalDays = (int) $start->diffInDays($end);
                $elapsedDays = (int) $start->diffInDays($now);
                $value = $totalDays > 0 ? round(min(($elapsedDays / $totalDays) * 100, 100), 2) : 0.0;

                return new MetricResult(
                    value: $value,
                    availability: Availability::AVAILABLE,
                    reliability: Reliability::RELIABLE,
                    freshness: Freshness::UNKNOWN,
                    asOf: null,
                    label: $label,
                    explanation: null,
                );
            },
        );
    }
}
