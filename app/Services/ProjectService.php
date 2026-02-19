<?php

namespace App\Services;

use App\Models\Project;
use App\Repositories\ProjectRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;

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
    public function createProject(array $data, string|int $userId, string|int $tenantId): Project
    {
        // Business logic validation
        $this->validateProjectCreation($data, $userId, $tenantId);
        
        // Generate project code
        $data['code'] = $this->generateProjectCode($tenantId);
        
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
        
        // Audit logging
        try {
            $this->auditService->logCrudOperation('create', 'project', $project->id, [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'project_id' => $project->id,
                'project_name' => $project->name,
                'project_code' => $project->code,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to log project creation', [
                'project_id' => $project->id,
                'error' => $exception->getMessage(),
            ]);
        }
        
        return $project;
    }

    public function updateProject(Project $project, array $data, string|int $userId): Project
    {
        $project->update($data);

        Event::dispatch('project.updated', $project);

        try {
            $this->auditService->logCrudOperation('update', 'project', $project->id, [
                'tenant_id' => $project->tenant_id,
                'user_id' => $userId,
                'project_id' => $project->id,
                'project_name' => $project->name,
                'project_code' => $project->code,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to log project update', [
                'project_id' => $project->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return $project->refresh();
    }

    public function deleteProject(Project $project, string|int $userId): bool
    {
        $deleted = $project->delete();

        Event::dispatch('project.deleted', $project);

        try {
            $this->auditService->logCrudOperation('delete', 'project', $project->id, [
                'tenant_id' => $project->tenant_id,
                'user_id' => $userId,
                'project_id' => $project->id,
                'project_name' => $project->name,
                'project_code' => $project->code,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to log project deletion', [
                'project_id' => $project->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return $deleted;
    }

    public function getProjectsList(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->projectRepository->getAll($filters, $perPage);
    }
    
    /**
     * Get project metrics
     */
    public function getProjectMetrics(string $projectId, string $userId, string $tenantId): array
    {
        $project = $this->projectRepository->getById($projectId);
        
        // Validate access
        $this->validateProjectAccess($project, $userId, $tenantId);
        
        // Get metrics from metrics service
        return $this->metricsService->getProjectMetrics($projectId);
    }
    
    /**
     * Generate project code
     */
    private function generateProjectCode(string|int $tenantId): string
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
    private function validateProjectCreation(array $data, string|int $userId, string|int $tenantId): void
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
    private function validateProjectAccess(Project $project, string|int $userId, string|int $tenantId): void
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
    private function canUserCreateProjects(string|int $userId, string|int $tenantId): bool
    {
        return true; // Simplified for demo
    }
    
    /**
     * Check if user can access project
     */
    private function canUserAccessProject(Project $project, string|int $userId): bool
    {
        return true; // Simplified for demo
    }
}
