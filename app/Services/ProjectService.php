<?php

namespace App\Services;

use App\Models\Project;
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
    public function createProject(array $data, int $userId, int $tenantId): Project
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
        $this->auditService->log('project_created', $userId, $tenantId, [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'project_code' => $project->code
        ]);
        
        return $project;
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
        if (method_exists($this->metricsService, 'getProjectMetrics')) {
            return $this->metricsService->getProjectMetrics($projectId);
        }

        return [
            'project_id' => $projectId,
            'status' => 'metrics unavailable',
        ];
    }
    
    /**
     * Generate project code
     */
    private function generateProjectCode(int $tenantId): string
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
    private function validateProjectCreation(array $data, int $userId, int $tenantId): void
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
        if ((string) $project->tenant_id !== (string) $tenantId) {
            throw new \UnauthorizedException('Project not found in tenant');
        }
        
        if (!$this->canUserAccessProject($project, $userId)) {
            throw new \UnauthorizedException('User cannot access this project');
        }
    }
    
    /**
     * Check if user can create projects
     */
    private function canUserCreateProjects(int $userId, int $tenantId): bool
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
}
