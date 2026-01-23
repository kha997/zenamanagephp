<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProjectService
{
    protected $projectRepository;
    protected $auditService;
    protected $metricsService;
    
    public function __construct(ProjectRepository $projectRepository, AuditService $auditService, MetricsService $metricsService)
    {
        $this->projectRepository = $projectRepository;
        $this->auditService = $auditService;
        $this->metricsService = $metricsService;
    }
    
    /**
     * Create a new project with business logic
     */
    public function createProject(array $data, ?string $userId = null, ?string $tenantId = null): Project
    {
        $userId = $userId ?? Auth::id();
        $tenantId = $tenantId ?? $data['tenant_id'] ?? Auth::user()?->tenant_id;

        if (!$userId || !$tenantId) {
            throw new \InvalidArgumentException('User and tenant IDs are required');
        }

        $this->logProjectCreationStage('start', [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'project_name' => $data['name'] ?? null
        ]);

        $this->validateProjectCreation($data, $userId, $tenantId);
        $this->logProjectCreationStage('validated', [
            'tenant_id' => $tenantId,
            'name' => $data['name'] ?? null
        ]);
        
        $data['tenant_id'] = $tenantId;
        $data['code'] = $this->generateProjectCode($tenantId);

        $this->logProjectCreationStage('before-repository-create', [
            'code' => $data['code'],
            'tenant_id' => $tenantId
        ]);

        // Create project
        $project = $this->projectRepository->create([
            'name' => $data['name'],
            'description' => $data['description'],
            'code' => $data['code'],
            'status' => 'planning',
            'budget_total' => $data['budget_total'] ?? 0,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'user_id' => $userId,
            'tenant_id' => $tenantId
        ]);
        
        // Fire events for side effects
        Event::dispatch('project.created', $project);
        $this->logProjectCreationStage('event-dispatched', ['project_id' => $project->id]);
        $this->logProjectCreationStage('sync-legacy-start', ['project_id' => $project->id]);
        $this->syncLegacyProjectRecord($project);
        $this->logProjectCreationStage('sync-legacy-end', ['project_id' => $project->id]);

        // Audit logging
        if (Schema::hasTable('interaction_logs')) {
            $this->logProjectCreationStage('audit-start', ['project_id' => $project->id]);
            $this->auditService->logCrudOperation('create', 'project', $project->id, [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'project_id' => $project->id,
                'project_name' => $project->name,
                'project_code' => $project->code
            ]);
            $this->logProjectCreationStage('audit-end', ['project_id' => $project->id]);
        }
        
        return $project;
    }

    /**
     * Delete project.
     */
    public function deleteProject(string $projectId): bool
    {
        return $this->projectRepository->delete($projectId);
    }

    /**
     * Get projects list with optional filters.
     */
    public function getProjectsList(array $filters = []): Collection
    {
        $query = Project::query();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get();
    }

    /**
     * Update project metadata.
     */
    public function updateProject(string $projectId, array $data): Project
    {
        $updated = $this->projectRepository->update($projectId, $data);

        if (! $updated) {
            throw new \InvalidArgumentException('Project not found');
        }

        $project = $this->projectRepository->getById($projectId);

        if (! $project) {
            throw new \InvalidArgumentException('Project not found after update');
        }

        return $project;
    }
    
    private function syncLegacyProjectRecord(Project $project): void
    {
        if (!Schema::hasTable('zena_projects')) {
            return;
        }

        $payload = [
            'code' => $project->code,
            'name' => $project->name,
            'description' => $project->description,
            'client_id' => $project->client_id,
            'status' => $project->status,
            'start_date' => $project->start_date,
            'end_date' => $project->end_date,
            'budget' => $project->budget_total ?? $project->budget ?? 0,
            'settings' => $project->settings ? json_encode($project->settings) : null,
            'created_at' => $project->created_at,
            'updated_at' => $project->updated_at,
        ];

        DB::table('zena_projects')->updateOrInsert(
            ['id' => $project->id],
            $payload
        );
    }
    
    /**
     * Get project metrics
     */
    public function getProjectMetrics(int $projectId, int $userId, int $tenantId): array
    {
        $project = $this->projectRepository->findById($projectId);
        
        // Validate access
        $this->validateProjectAccess($project, $userId, $tenantId);
        
        // Get metrics from metrics service
        return $this->metricsService->getProjectMetrics($projectId);
    }
    
    /**
     * Generate project code
     */
    private function generateProjectCode(string $tenantId): string
    {
        $prefix = 'PRJ';
        $tenantCode = strtoupper(substr(md5($tenantId), 0, 3));
        $timestamp = date('Ymd');
        $random = strtoupper(substr(uniqid(), -4));
        
        return "{$prefix}-{$tenantCode}-{$timestamp}-{$random}";
    }
    
    /**
     * Validate project creation
     */
    private function validateProjectCreation(array $data, string $userId, string $tenantId): void
    {
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Project name is required');
        }
        
        if (!$this->canUserCreateProjects($userId, $tenantId)) {
            throw new \UnauthorizedException('User cannot create projects in this tenant');
        }
    }
    
    /**
     * Validate project access
     */
    private function validateProjectAccess(Project $project, string $userId, string $tenantId): void
    {
        if ($project->tenant_id !== $tenantId) {
            throw new \UnauthorizedException('Project not found in tenant');
        }
        
        if (!$this->canUserAccessProject($project, $userId)) {
            throw new \UnauthorizedException('User cannot access this project');
        }
    }
    
    /**
     * Check if user can create projects
     */
    private function canUserCreateProjects(string $userId, string $tenantId): bool
    {
        return true; // Simplified for demo
    }
    
    /**
     * Check if user can access project
     */
    private function canUserAccessProject(Project $project, string $userId): bool
    {
        return true; // Simplified for demo
    }

    private function logProjectCreationStage(string $stage, array $context = []): void
    {
        if (!app()->environment('testing')) {
            return;
        }

        Log::info("ProjectService::createProject {$stage}", $context);
    }
}
