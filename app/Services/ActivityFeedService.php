<?php declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ProjectActivity;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ActivityFeedService
 * 
 * Round 248: Global Activity / My Work Feed
 * 
 * Aggregates activities from project_activities and audit_logs
 * for the current user, filtered by module, date range, and search.
 */
class ActivityFeedService
{
    /**
     * Get activity feed for user
     * 
     * @param User $user Current user
     * @param string|null $module Filter by module: 'all', 'tasks', 'documents', 'cost', 'rbac'
     * @param string|null $from ISO datetime string (start date)
     * @param string|null $to ISO datetime string (end date)
     * @param string|null $search Search text (matches action/entity/project name)
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return LengthAwarePaginator
     */
    public function getFeedForUser(
        User $user,
        ?string $module = null,
        ?string $from = null,
        ?string $to = null,
        ?string $search = null,
        int $page = 1,
        int $perPage = 20
    ): LengthAwarePaginator {
        $tenantId = $user->tenant_id;
        $userId = $user->id;

        // Collect activities from both sources
        $activities = collect();

        // 1. Query project_activities
        $projectActivities = $this->getProjectActivities($tenantId, $userId, $module, $from, $to, $search);
        $activities = $activities->merge($projectActivities);

        // 2. Query audit_logs
        $auditLogs = $this->getAuditLogs($tenantId, $userId, $module, $from, $to, $search);
        $activities = $activities->merge($auditLogs);

        // Sort by timestamp descending
        $activities = $activities->sortByDesc('timestamp')->values();

        // Manual pagination
        $total = $activities->count();
        $items = $activities->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * Get activities from project_activities table
     */
    private function getProjectActivities(
        string $tenantId,
        string $userId,
        ?string $module,
        ?string $from,
        ?string $to,
        ?string $search
    ): Collection {
        $driver = DB::getDriverName();
        $isSqlite = ($driver === 'sqlite');

        if ($isSqlite) {
            // SQLite: Fetch broad records and filter in PHP
            $query = ProjectActivity::query()
                ->where('tenant_id', $tenantId)
                ->with(['user:id,name,email', 'project:id,name,code']);

            // Apply date range (can be done in SQL)
            if ($from) {
                $query->where('created_at', '>=', $from);
            }
            if ($to) {
                $query->where('created_at', '<=', $to);
            }

            // Fetch all matching tenant/date records
            $activities = $query->orderBy('created_at', 'desc')->get();

            // Filter in PHP: user-related check
            $activities = $activities->filter(function ($activity) use ($userId) {
                // User is actor
                if ($activity->user_id === $userId) {
                    return true;
                }

                // Check metadata for assignee
                $metadata = $activity->metadata ?? [];
                return
                    ($metadata['new_assignee_id'] ?? null) === $userId ||
                    ($metadata['old_assignee_id'] ?? null) === $userId ||
                    ($metadata['assignee_id'] ?? null) === $userId;
            });

            // Apply module filter in PHP
            if ($module && $module !== 'all') {
                $activities = $activities->filter(function ($activity) use ($module) {
                    return $this->matchesModuleForProjectActivity($activity, $module);
                });
            }

            // Apply search filter in PHP
            if ($search) {
                $searchLower = strtolower($search);
                $activities = $activities->filter(function ($activity) use ($searchLower) {
                    return
                        stripos($activity->action ?? '', $searchLower) !== false ||
                        stripos($activity->entity_type ?? '', $searchLower) !== false ||
                        stripos($activity->description ?? '', $searchLower) !== false ||
                        stripos($activity->project?->name ?? '', $searchLower) !== false;
                });
            }
        } else {
            // MySQL/PostgreSQL: Use JSON queries
            $query = ProjectActivity::query()
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($userId) {
                    // User is actor
                    $q->where('user_id', $userId)
                        // OR user is assignee (from metadata)
                        ->orWhere(function ($subQ) use ($userId) {
                            $subQ->whereJsonContains('metadata->new_assignee_id', $userId)
                                ->orWhereJsonContains('metadata->old_assignee_id', $userId)
                                ->orWhereJsonContains('metadata->assignee_id', $userId);
                        });
                })
                ->with(['user:id,name,email', 'project:id,name,code']);

            // Apply module filter
            if ($module && $module !== 'all') {
                $query->where(function ($q) use ($module) {
                    match ($module) {
                        'tasks' => $q->whereIn('entity_type', ['Task', 'ProjectTask'])
                            ->orWhereIn('action', [
                                'task_updated', 'task_completed',
                                'project_task_updated', 'project_task_completed',
                                'project_task_marked_incomplete',
                                'project_task_assigned', 'project_task_unassigned', 'project_task_reassigned'
                            ]),
                        'documents' => $q->where('entity_type', 'Document')
                            ->orWhereIn('action', [
                                'document_uploaded', 'document_updated', 'document_deleted',
                                'document_version_restored'
                            ]),
                        'cost' => $q->whereIn('entity_type', ['ChangeOrder', 'ContractPaymentCertificate', 'ContractActualPayment'])
                            ->orWhereIn('action', [
                                'change_order_proposed', 'change_order_approved', 'change_order_rejected',
                                'certificate_submitted', 'certificate_approved',
                                'payment_marked_paid'
                            ]),
                        'rbac' => $q->whereIn('entity_type', ['Role', 'User'])
                            ->orWhereIn('action', ['role.created', 'role.updated', 'user.roles_updated']),
                        default => null
                    };
                });
            }

            // Apply date range
            if ($from) {
                $query->where('created_at', '>=', $from);
            }
            if ($to) {
                $query->where('created_at', '<=', $to);
            }

            // Apply search
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('action', 'like', "%{$search}%")
                        ->orWhere('entity_type', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('project', function ($subQ) use ($search) {
                            $subQ->where('name', 'like', "%{$search}%");
                        });
                });
            }

            $activities = $query->orderBy('created_at', 'desc')->get();
        }

        return $activities->map(function ($activity) use ($userId) {
            // Determine module
            $module = $this->determineModuleFromProjectActivity($activity);
            
            // Determine type (canonical action)
            $type = $this->mapProjectActivityActionToType($activity->action);
            
            // Determine if directly related
            $isDirectlyRelated = $this->isProjectActivityDirectlyRelated($activity, $userId);

            // Get project info
            $project = $activity->project;
            $projectName = $project?->name;
            $projectId = $activity->project_id;

            // Get actor info
            $actor = $activity->user;
            $actorName = $actor?->name;
            $actorId = $activity->user_id;

            // Build summary
            $summary = $activity->description;
            if ($activity->metadata) {
                $metadata = $activity->metadata;
                // Enhance summary with metadata if available
                if (isset($metadata['task_name'])) {
                    $summary = "Task '{$metadata['task_name']}' " . strtolower($activity->action);
                } elseif (isset($metadata['document_name'])) {
                    $summary = "Document '{$metadata['document_name']}' " . strtolower($activity->action);
                }
            }

            return [
                'id' => 'pa_' . $activity->id,
                'timestamp' => $activity->created_at->toISOString(),
                'module' => $module,
                'type' => $type,
                'title' => $this->getTitleFromAction($activity->action),
                'summary' => $summary,
                'project_id' => $projectId,
                'project_name' => $projectName,
                'entity_type' => strtolower($activity->entity_type),
                'entity_id' => $activity->entity_id,
                'actor_id' => $actorId,
                'actor_name' => $actorName,
                'is_directly_related' => $isDirectlyRelated,
            ];
        });
    }

    /**
     * Get activities from audit_logs table
     */
    private function getAuditLogs(
        string $tenantId,
        string $userId,
        ?string $module,
        ?string $from,
        ?string $to,
        ?string $search
    ): Collection {
        $driver = DB::getDriverName();
        $isSqlite = ($driver === 'sqlite');

        if ($isSqlite) {
            // SQLite: Fetch broad records and filter in PHP
            $query = AuditLog::query()
                ->where('tenant_id', $tenantId)
                ->with(['user:id,name,email', 'project:id,name,code']);

            // Apply date range (can be done in SQL)
            if ($from) {
                $query->where('created_at', '>=', $from);
            }
            if ($to) {
                $query->where('created_at', '<=', $to);
            }

            // Fetch all matching tenant/date records
            $logs = $query->orderBy('created_at', 'desc')->get();

            // Filter in PHP: user-related check
            $logs = $logs->filter(function ($log) use ($userId) {
                // User is actor
                if ($log->user_id === $userId) {
                    return true;
                }

                // Check payload_after for approver
                $payloadAfter = $log->payload_after ?? [];
                return
                    ($payloadAfter['first_approved_by'] ?? null) === $userId ||
                    ($payloadAfter['second_approved_by'] ?? null) === $userId ||
                    ($payloadAfter['approved_by'] ?? null) === $userId;
            });

            // Apply module filter in PHP
            if ($module && $module !== 'all') {
                $logs = $logs->filter(function ($log) use ($module) {
                    return $this->matchesModuleForAuditLog($log, $module);
                });
            }

            // Apply search filter in PHP
            if ($search) {
                $searchLower = strtolower($search);
                $logs = $logs->filter(function ($log) use ($searchLower) {
                    return
                        stripos($log->action ?? '', $searchLower) !== false ||
                        stripos($log->entity_type ?? '', $searchLower) !== false ||
                        stripos($log->project?->name ?? '', $searchLower) !== false;
                });
            }
        } else {
            // MySQL/PostgreSQL: Use JSON queries
            $query = AuditLog::query()
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($userId) {
                    // User is actor
                    $q->where('user_id', $userId)
                        // OR user is approver (from payload_after)
                        ->orWhere(function ($subQ) use ($userId) {
                            $subQ->whereJsonContains('payload_after->first_approved_by', $userId)
                                ->orWhereJsonContains('payload_after->second_approved_by', $userId)
                                ->orWhereJsonContains('payload_after->approved_by', $userId);
                        });
                })
                ->with(['user:id,name,email', 'project:id,name,code']);

            // Apply module filter
            if ($module && $module !== 'all') {
                $query->where(function ($q) use ($module) {
                    match ($module) {
                        'tasks' => $q->whereIn('entity_type', ['Task', 'ProjectTask']),
                        'documents' => $q->where('entity_type', 'Document'),
                        'cost' => $q->whereIn('entity_type', ['ChangeOrder', 'ContractPaymentCertificate', 'ContractActualPayment'])
                            ->orWhere('action', 'like', 'co.%')
                            ->orWhere('action', 'like', 'certificate.%')
                            ->orWhere('action', 'like', 'payment.%'),
                        'rbac' => $q->whereIn('entity_type', ['Role', 'User'])
                            ->orWhere('action', 'like', 'role.%')
                            ->orWhere('action', 'like', 'user.%'),
                        default => null
                    };
                });
            }

            // Apply date range
            if ($from) {
                $query->where('created_at', '>=', $from);
            }
            if ($to) {
                $query->where('created_at', '<=', $to);
            }

            // Apply search
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('action', 'like', "%{$search}%")
                        ->orWhere('entity_type', 'like', "%{$search}%")
                        ->orWhereHas('project', function ($subQ) use ($search) {
                            $subQ->where('name', 'like', "%{$search}%");
                        });
                });
            }

            $logs = $query->orderBy('created_at', 'desc')->get();
        }

        return $logs->map(function ($log) use ($userId) {
            // Determine module
            $module = $this->determineModuleFromAuditLog($log);
            
            // Determine type (canonical action)
            $type = $log->action;
            
            // Determine if directly related
            $isDirectlyRelated = $this->isAuditLogDirectlyRelated($log, $userId);

            // Get project info
            $project = $log->project;
            $projectName = $project?->name;
            $projectId = $log->project_id;

            // Get actor info
            $actor = $log->user;
            $actorName = $actor?->name;
            $actorId = $log->user_id;

            // Build summary from action and payload
            $summary = $this->buildSummaryFromAuditLog($log);

            return [
                'id' => 'al_' . $log->id,
                'timestamp' => $log->created_at->toISOString(),
                'module' => $module,
                'type' => $type,
                'title' => $this->getTitleFromAction($log->action),
                'summary' => $summary,
                'project_id' => $projectId,
                'project_name' => $projectName,
                'entity_type' => strtolower($log->entity_type ?? ''),
                'entity_id' => $log->entity_id,
                'actor_id' => $actorId,
                'actor_name' => $actorName,
                'is_directly_related' => $isDirectlyRelated,
            ];
        });
    }

    /**
     * Check if project activity matches module filter (PHP filtering helper)
     */
    private function matchesModuleForProjectActivity(ProjectActivity $activity, string $module): bool
    {
        $entityType = $activity->entity_type;
        $action = $activity->action;

        return match ($module) {
            'tasks' => in_array($entityType, ['Task', 'ProjectTask']) ||
                in_array($action, [
                    'task_updated', 'task_completed',
                    'project_task_updated', 'project_task_completed',
                    'project_task_marked_incomplete',
                    'project_task_assigned', 'project_task_unassigned', 'project_task_reassigned'
                ]),
            'documents' => $entityType === 'Document' ||
                in_array($action, [
                    'document_uploaded', 'document_updated', 'document_deleted',
                    'document_version_restored'
                ]),
            'cost' => in_array($entityType, ['ChangeOrder', 'ContractPaymentCertificate', 'ContractActualPayment']) ||
                in_array($action, [
                    'change_order_proposed', 'change_order_approved', 'change_order_rejected',
                    'certificate_submitted', 'certificate_approved',
                    'payment_marked_paid'
                ]),
            'rbac' => in_array($entityType, ['Role', 'User']) ||
                in_array($action, ['role.created', 'role.updated', 'user.roles_updated']),
            default => false
        };
    }

    /**
     * Check if audit log matches module filter (PHP filtering helper)
     */
    private function matchesModuleForAuditLog(AuditLog $log, string $module): bool
    {
        $entityType = $log->entity_type;
        $action = $log->action;

        return match ($module) {
            'tasks' => in_array($entityType, ['Task', 'ProjectTask']),
            'documents' => $entityType === 'Document',
            'cost' => in_array($entityType, ['ChangeOrder', 'ContractPaymentCertificate', 'ContractActualPayment']) ||
                str_starts_with($action ?? '', 'co.') ||
                str_starts_with($action ?? '', 'certificate.') ||
                str_starts_with($action ?? '', 'payment.'),
            'rbac' => in_array($entityType, ['Role', 'User']) ||
                str_starts_with($action ?? '', 'role.') ||
                str_starts_with($action ?? '', 'user.'),
            default => false
        };
    }

    /**
     * Determine module from project activity
     */
    private function determineModuleFromProjectActivity(ProjectActivity $activity): string
    {
        $entityType = $activity->entity_type;
        $action = $activity->action;

        if (in_array($entityType, ['Task', 'ProjectTask']) || 
            str_contains($action, 'task')) {
            return 'tasks';
        }

        if ($entityType === 'Document' || str_contains($action, 'document')) {
            return 'documents';
        }

        if (in_array($entityType, ['ChangeOrder', 'ContractPaymentCertificate', 'ContractActualPayment']) ||
            str_contains($action, 'change_order') ||
            str_contains($action, 'certificate') ||
            str_contains($action, 'payment')) {
            return 'cost';
        }

        if (in_array($entityType, ['Role', 'User']) || str_contains($action, 'role')) {
            return 'rbac';
        }

        return 'tasks'; // default
    }

    /**
     * Determine module from audit log
     */
    private function determineModuleFromAuditLog(AuditLog $log): string
    {
        $entityType = $log->entity_type;
        $action = $log->action;

        if (in_array($entityType, ['Task', 'ProjectTask']) || str_starts_with($action, 'task.')) {
            return 'tasks';
        }

        if ($entityType === 'Document' || str_starts_with($action, 'document.')) {
            return 'documents';
        }

        if (in_array($entityType, ['ChangeOrder', 'ContractPaymentCertificate', 'ContractActualPayment']) ||
            str_starts_with($action, 'co.') ||
            str_starts_with($action, 'certificate.') ||
            str_starts_with($action, 'payment.')) {
            return 'cost';
        }

        if (in_array($entityType, ['Role', 'User']) ||
            str_starts_with($action, 'role.') ||
            str_starts_with($action, 'user.')) {
            return 'rbac';
        }

        return 'tasks'; // default
    }

    /**
     * Map project activity action to canonical type
     */
    private function mapProjectActivityActionToType(string $action): string
    {
        return match ($action) {
            'task_updated', 'project_task_updated' => 'task.updated',
            'task_completed', 'project_task_completed' => 'task.completed',
            'project_task_marked_incomplete' => 'task.marked_incomplete',
            'project_task_assigned' => 'task.assigned',
            'project_task_unassigned' => 'task.unassigned',
            'project_task_reassigned' => 'task.reassigned',
            'document_uploaded' => 'document.uploaded',
            'document_updated' => 'document.updated',
            'document_deleted' => 'document.deleted',
            'document_version_restored' => 'document.version_restored',
            'change_order_proposed' => 'co.proposed',
            'change_order_approved' => 'co.approved',
            'change_order_rejected' => 'co.rejected',
            'certificate_submitted' => 'certificate.submitted',
            'certificate_approved' => 'certificate.approved',
            'payment_marked_paid' => 'payment.marked_paid',
            default => str_replace('_', '.', $action),
        };
    }

    /**
     * Check if project activity is directly related to user
     */
    private function isProjectActivityDirectlyRelated(ProjectActivity $activity, string $userId): bool
    {
        // User is actor
        if ($activity->user_id === $userId) {
            return true;
        }

        // Check metadata for assignee
        if ($activity->metadata) {
            $metadata = $activity->metadata;
            if (isset($metadata['new_assignee_id']) && $metadata['new_assignee_id'] === $userId) {
                return true;
            }
            if (isset($metadata['old_assignee_id']) && $metadata['old_assignee_id'] === $userId) {
                return true;
            }
            if (isset($metadata['assignee_id']) && $metadata['assignee_id'] === $userId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if audit log is directly related to user
     */
    private function isAuditLogDirectlyRelated(AuditLog $log, string $userId): bool
    {
        // User is actor
        if ($log->user_id === $userId) {
            return true;
        }

        // Check payload_after for approver
        if ($log->payload_after) {
            $after = $log->payload_after;
            if (isset($after['first_approved_by']) && $after['first_approved_by'] === $userId) {
                return true;
            }
            if (isset($after['second_approved_by']) && $after['second_approved_by'] === $userId) {
                return true;
            }
            if (isset($after['approved_by']) && $after['approved_by'] === $userId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get title from action
     */
    private function getTitleFromAction(string $action): string
    {
        return match ($action) {
            'task_updated', 'project_task_updated' => 'Task updated',
            'task_completed', 'project_task_completed' => 'Task completed',
            'project_task_marked_incomplete' => 'Task marked incomplete',
            'project_task_assigned' => 'Task assigned',
            'project_task_unassigned' => 'Task unassigned',
            'project_task_reassigned' => 'Task reassigned',
            'document_uploaded' => 'Document uploaded',
            'document_updated' => 'Document updated',
            'document_deleted' => 'Document deleted',
            'document_version_restored' => 'Document version restored',
            'change_order_proposed' => 'Change order proposed',
            'change_order_approved' => 'Change order approved',
            'change_order_rejected' => 'Change order rejected',
            'certificate_submitted' => 'Certificate submitted',
            'certificate_approved' => 'Certificate approved',
            'payment_marked_paid' => 'Payment marked paid',
            default => ucfirst(str_replace(['_', '.'], ' ', $action)),
        };
    }

    /**
     * Build summary from audit log
     */
    private function buildSummaryFromAuditLog(AuditLog $log): string
    {
        $action = $log->action;
        $entityType = $log->entity_type;
        $payloadAfter = $log->payload_after;

        // Try to extract meaningful info from payload
        if ($payloadAfter) {
            if (isset($payloadAfter['code'])) {
                return ucfirst(str_replace('.', ' ', $action)) . ": {$payloadAfter['code']}";
            }
            if (isset($payloadAfter['name'])) {
                return ucfirst(str_replace('.', ' ', $action)) . ": {$payloadAfter['name']}";
            }
        }

        // Fallback to action description
        return ucfirst(str_replace('.', ' ', $action));
    }
}
