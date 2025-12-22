<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

/**
 * Audit logging service for Projects
 */
class ProjectAuditService
{
    /**
     * Log project creation
     */
    public function logCreate(Project $project, User $user, Request $request): void
    {
        $this->log('create', $project, $user, $request, [
            'project_name' => $project->name,
            'project_code' => $project->code,
            'project_status' => $project->status,
            'project_priority' => $project->priority,
            'project_owner' => $project->owner_id,
            'project_budget' => $project->budget_total,
            'project_start_date' => $project->start_date,
            'project_due_date' => $project->due_date,
        ]);
    }

    /**
     * Log project update
     */
    public function logUpdate(Project $project, User $user, Request $request, array $changes): void
    {
        $this->log('update', $project, $user, $request, [
            'changes' => $changes,
            'project_name' => $project->name,
            'project_code' => $project->code,
        ]);
    }

    /**
     * Log project deletion
     */
    public function logDelete(Project $project, User $user, Request $request): void
    {
        $this->log('delete', $project, $user, $request, [
            'project_name' => $project->name,
            'project_code' => $project->code,
            'project_status' => $project->status,
        ]);
    }

    /**
     * Log project archive
     */
    public function logArchive(Project $project, User $user, Request $request): void
    {
        $this->log('archive', $project, $user, $request, [
            'project_name' => $project->name,
            'project_code' => $project->code,
            'previous_status' => 'active',
            'new_status' => 'archived',
        ]);
    }

    /**
     * Log project restore
     */
    public function logRestore(Project $project, User $user, Request $request): void
    {
        $this->log('restore', $project, $user, $request, [
            'project_name' => $project->name,
            'project_code' => $project->code,
            'previous_status' => 'archived',
            'new_status' => 'active',
        ]);
    }

    /**
     * Log project export
     */
    public function logExport(User $user, Request $request, array $filters = []): void
    {
        $this->log('export', null, $user, $request, [
            'export_filters' => $filters,
            'export_format' => $request->get('format', 'csv'),
        ]);
    }

    /**
     * Log project view
     */
    public function logView(Project $project, User $user, Request $request): void
    {
        $this->log('view', $project, $user, $request, [
            'project_name' => $project->name,
            'project_code' => $project->code,
        ]);
    }

    /**
     * Log project KPI access
     */
    public function logKpiAccess(User $user, Request $request, string $kpiType = 'general'): void
    {
        $this->log('kpi_access', null, $user, $request, [
            'kpi_type' => $kpiType,
            'filters' => $request->all(),
        ]);
    }

    /**
     * Core logging method
     */
    private function log(string $action, ?Project $project, User $user, Request $request, array $data = []): void
    {
        $logData = [
            'event' => 'project_' . $action,
            'timestamp' => now()->toISOString(),
            'user_id' => $user->id,
            'user_email' => $user->email,
            'tenant_id' => $user->tenant_id,
            'project_id' => $project?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'data' => $data,
        ];

        // Log to Laravel log
        Log::channel('audit')->info('Project Audit', $logData);

        // Also log to database if audit table exists
        try {
            \DB::table('audit_logs')->insert([
                'event' => 'project_' . $action,
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'model_type' => 'App\\Models\\Project',
                'model_id' => $project?->id,
                'data' => json_encode($logData),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Audit table doesn't exist, continue with file logging only
            Log::warning('Audit table not available, using file logging only', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get audit trail for a project
     */
    public function getAuditTrail(Project $project, int $limit = 50): array
    {
        try {
            return \DB::table('audit_logs')
                ->where('model_type', 'App\\Models\\Project')
                ->where('model_id', $project->id)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'event' => $log->event,
                        'user_id' => $log->user_id,
                        'data' => json_decode($log->data, true),
                        'ip_address' => $log->ip_address,
                        'created_at' => $log->created_at,
                    ];
                })
                ->toArray();
        } catch (\Exception $e) {
            Log::warning('Could not retrieve audit trail from database', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get audit trail for a user
     */
    public function getUserAuditTrail(User $user, int $limit = 100): array
    {
        try {
            return \DB::table('audit_logs')
                ->where('user_id', $user->id)
                ->where('tenant_id', $user->tenant_id)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'event' => $log->event,
                        'model_type' => $log->model_type,
                        'model_id' => $log->model_id,
                        'data' => json_decode($log->data, true),
                        'ip_address' => $log->ip_address,
                        'created_at' => $log->created_at,
                    ];
                })
                ->toArray();
        } catch (\Exception $e) {
            Log::warning('Could not retrieve user audit trail from database', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}
