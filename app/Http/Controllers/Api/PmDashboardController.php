<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ZenaContractResponseTrait;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Rfi;
use App\Models\Task;
use App\Models\UserRoleProject;
use App\Support\Dashboard\Availability;
use App\Support\Dashboard\Freshness;
use App\Support\Dashboard\MetricGuard;
use App\Support\Dashboard\MetricResult;
use App\Support\Dashboard\Reliability;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PmDashboardController extends Controller
{
    use ZenaContractResponseTrait;

    public function getOverview(Request $request): JsonResponse
    {
        $user = Auth::user();

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
        $user = Auth::user();

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

        return $this->zenaSuccessResponse([
            'project' => [
                'id' => (string) $project->id,
                'name' => $project->name,
                'status' => $project->status,
            ],
            'overall_progress' => $this->computeOverallProgress($projectId),
            'overall_progress_meta' => $this->computeOverallProgressMeta($projectId)->toArray(),
            'task_progress' => $this->computeTaskProgress($projectId),
            'milestone_progress' => $this->computeMilestoneProgress($projectId),
            'budget_progress' => $this->computeBudgetProgress($project),
            'timeline_progress' => $this->computeTimelineProgress($project),
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

    private function computeOverallProgress(string $projectId): float
    {
        $total = Task::where('project_id', $projectId)->count();

        if ($total === 0) {
            return 0.0;
        }

        $completed = Task::where('project_id', $projectId)
            ->where('status', Task::STATUS_COMPLETED)
            ->count();

        return round(($completed / $total) * 100, 2);
    }

    private function computeOverallProgressMeta(string $projectId): MetricResult
    {
        $label = 'Tiến độ công việc (Task)';

        return MetricGuard::wrap(
            'overall_progress',
            ['project_id' => $projectId, 'tenant_id' => (string) Auth::user()?->tenant_id],
            $label,
            function () use ($projectId, $label) {
                $total = Task::where('project_id', $projectId)->count();

                if ($total === 0) {
                    return new MetricResult(
                        value: null,
                        availability: Availability::NO_DATA,
                        reliability: Reliability::RELIABLE,
                        freshness: Freshness::UNKNOWN,
                        asOf: null,
                        label: $label,
                        explanation: 'Dự án chưa có công việc (Task) nào được tạo.',
                    );
                }

                $completed = Task::where('project_id', $projectId)
                    ->where('status', Task::STATUS_COMPLETED)
                    ->count();

                $value = round(($completed / $total) * 100, 2);
                $asOf = Task::where('project_id', $projectId)->max('updated_at');

                return new MetricResult(
                    value: $value,
                    availability: Availability::AVAILABLE,
                    reliability: Reliability::RELIABLE,
                    freshness: Freshness::UNKNOWN,
                    asOf: $asOf ? Carbon::parse($asOf) : null,
                    label: $label,
                    explanation: null,
                );
            },
        );
    }

    private function computeTaskProgress(string $projectId): array
    {
        $tasks = Task::where('project_id', $projectId)->get();

        $overdue = $tasks->filter(
            fn ($t) => $t->end_date !== null
                && $t->end_date < now()
                && !in_array($t->status, [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED], true)
        )->count();

        return [
            'total' => $tasks->count(),
            'completed' => $tasks->where('status', Task::STATUS_COMPLETED)->count(),
            'in_progress' => $tasks->where('status', Task::STATUS_IN_PROGRESS)->count(),
            'pending' => $tasks->where('status', Task::STATUS_PENDING)->count(),
            'overdue' => $overdue,
        ];
    }

    private function computeMilestoneProgress(string $projectId): array
    {
        $milestones = ProjectMilestone::where('project_id', $projectId)->get();

        $total = $milestones->count();
        $completed = $milestones->where('status', ProjectMilestone::STATUS_COMPLETED)->count();
        $pending = $milestones->where('status', ProjectMilestone::STATUS_PENDING)->count();
        $overdue = $milestones->where('status', ProjectMilestone::STATUS_OVERDUE)->count();

        $completionRate = $total > 0 ? round(($completed / $total) * 100, 2) : 0.0;

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

        return [
            'total_milestones' => $total,
            'completed_milestones' => $completed,
            'pending_milestones' => $pending,
            'overdue_milestones' => $overdue,
            'completion_rate' => $completionRate,
            'upcoming_milestones' => $upcoming,
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
}
