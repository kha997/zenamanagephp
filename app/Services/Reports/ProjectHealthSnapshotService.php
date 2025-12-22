<?php declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Project;
use App\Models\ProjectHealthSnapshot;
use App\Services\Projects\ProjectOverviewService;
use Illuminate\Support\Collection;

/**
 * Project Health Snapshot Service
 * 
 * Round 86: Project Health History (snapshots + history API, backend-only)
 * 
 * Handles creation and retrieval of project health snapshots.
 */
class ProjectHealthSnapshotService
{
    public function __construct(
        private readonly ProjectOverviewService $overviewService,
    ) {}

    /**
     * Create a snapshot for one project
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @return ProjectHealthSnapshot Created snapshot
     */
    public function snapshotProjectHealthForProject(string $tenantId, Project $project): ProjectHealthSnapshot
    {
        // Ensure the project belongs to the tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \InvalidArgumentException('Project does not belong to the specified tenant');
        }

        // Get current health from overview service
        $overview = $this->overviewService->buildOverview($tenantId, $project->id);
        $health = $overview['health'];

        // Determine snapshot date (today in app timezone)
        $snapshotDate = now(config('app.timezone'))->toDateString();

        // Upsert: update existing snapshot for same day, or create new one
        return ProjectHealthSnapshot::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'project_id' => $project->id,
                'snapshot_date' => $snapshotDate,
            ],
            [
                'schedule_status' => $health['schedule_status'],
                'cost_status' => $health['cost_status'],
                'overall_status' => $health['overall_status'],
                'tasks_completion_rate' => $health['tasks_completion_rate'],
                'blocked_tasks_ratio' => $health['blocked_tasks_ratio'],
                'overdue_tasks' => $health['overdue_tasks'],
            ]
        );
    }

    /**
     * Get health history for one project
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @param int $limit Maximum number of snapshots to return (default: 30)
     * @return Collection Collection of ProjectHealthSnapshot models
     */
    public function getHealthHistoryForProject(string $tenantId, Project $project, int $limit = 30): Collection
    {
        // Ensure the project belongs to the tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \InvalidArgumentException('Project does not belong to the specified tenant');
        }

        return ProjectHealthSnapshot::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->orderBy('snapshot_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Create snapshots for all projects in a tenant
     * 
     * Round 88: Daily Project Health Snapshots (command + schedule)
     * 
     * @param string $tenantId Tenant ID
     * @return int Number of projects for which snapshots were created/updated
     */
    public function snapshotAllProjectsForTenant(string $tenantId): int
    {
        // Get all non-deleted projects for this tenant
        $projects = Project::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->get();

        $count = 0;
        foreach ($projects as $project) {
            try {
                $this->snapshotProjectHealthForProject($tenantId, $project);
                $count++;
            } catch (\Exception $e) {
                // Log error but continue with other projects
                \Log::warning('Failed to create snapshot for project', [
                    'tenant_id' => $tenantId,
                    'project_id' => $project->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }
}

