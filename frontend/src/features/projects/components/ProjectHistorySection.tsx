import React, { useState, useCallback } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Select } from '../../../components/ui/primitives/Select';
import { useProjectHistory } from '../hooks';

interface ProjectHistorySectionProps {
  projectId: string | number;
  filters?: { action?: string; entity_type?: string; limit?: number };
}

// History actions - based on common project history actions
const HISTORY_ACTIONS = [
  { value: '', label: 'All Actions' },
  { value: 'created', label: 'Created' },
  { value: 'updated', label: 'Updated' },
  { value: 'status_changed', label: 'Status Changed' },
  { value: 'document_uploaded', label: 'Document Uploaded' },
  { value: 'document_updated', label: 'Document Updated' },
  { value: 'document_deleted', label: 'Document Deleted' },
  { value: 'document_downloaded', label: 'Document Downloaded' },
  { value: 'document_version_restored', label: 'Document Version Restored' },
  { value: 'task_created', label: 'Task Created' },
  { value: 'task_completed', label: 'Task Completed' },
  { value: 'project_tasks_generated_from_template', label: 'Tasks Generated From Template' },
  { value: 'project_task_updated', label: 'Project Task Updated' },
  { value: 'project_task_completed', label: 'Project Task Completed' },
  { value: 'project_task_marked_incomplete', label: 'Project Task Marked Incomplete' },
  { value: 'project_tasks_reordered', label: 'Tasks Reordered' },
  { value: 'project_task_assigned', label: 'Task Assigned' },
  { value: 'project_task_unassigned', label: 'Task Unassigned' },
  { value: 'project_task_reassigned', label: 'Task Reassigned' },
  { value: 'deleted', label: 'Deleted' },
];

// Entity types - based on common entities in project history
const ENTITY_TYPES = [
  { value: '', label: 'All Entities' },
  { value: 'project', label: 'Project' },
  { value: 'task', label: 'Task' },
  { value: 'ProjectTask', label: 'Project Task' },
  { value: 'document', label: 'Document' },
  { value: 'team_member', label: 'Team Member' },
];

// Limit options
const LIMIT_OPTIONS = [
  { value: '20', label: '20 items' },
  { value: '50', label: '50 items' },
  { value: '100', label: '100 items' },
];

export const ProjectHistorySection: React.FC<ProjectHistorySectionProps> = ({ projectId, filters: externalFilters }) => {
  // Local filter state
  const [action, setAction] = useState<string>(externalFilters?.action || '');
  const [entityType, setEntityType] = useState<string>(externalFilters?.entity_type || '');
  const [limit, setLimit] = useState<number>(externalFilters?.limit || 50);

  // Build filters object - only include non-empty values, ensure limit doesn't exceed 100
  const filters = React.useMemo(() => {
    const filterObj: { action?: string; entity_type?: string; limit?: number } = {};
    if (action) filterObj.action = action;
    if (entityType) filterObj.entity_type = entityType;
    // Ensure limit is between 1 and 100
    const clampedLimit = Math.min(Math.max(limit, 1), 100);
    filterObj.limit = clampedLimit;
    return filterObj;
  }, [action, entityType, limit]);

  const { data: historyData, isLoading, error } = useProjectHistory(projectId, filters);

  const handleActionChange = useCallback((value: string) => {
    setAction(value);
  }, []);

  const handleEntityTypeChange = useCallback((value: string) => {
    setEntityType(value);
  }, []);

  const handleLimitChange = useCallback((value: string) => {
    const numValue = parseInt(value, 10);
    if (!isNaN(numValue) && numValue >= 1 && numValue <= 100) {
      setLimit(numValue);
    }
  }, []);

  // Extract history items from response
  const historyItems = React.useMemo(() => {
    if (!historyData) return [];
    // Handle both response formats: { success: true, data: [...] } or { data: [...] }
    const items = (historyData as any).data ?? (Array.isArray(historyData) ? historyData : []);
    return Array.isArray(items) ? items : [];
  }, [historyData]);

  // Round 208: Render task activity text
  const renderTaskActivityText = (item: any): string | null => {
    const { action, metadata } = item;

    if (action === 'project_tasks_generated_from_template') {
      const templateName = metadata?.template_name ?? 'template';
      const taskCount = metadata?.task_count ?? 0;
      return `Generated ${taskCount} task(s) from template "${templateName}"`;
    }

    if (action === 'project_task_completed') {
      const taskName = metadata?.task_name ?? 'Task';
      return `Task "${taskName}" marked as completed`;
    }

    if (action === 'project_task_marked_incomplete') {
      const taskName = metadata?.task_name ?? 'Task';
      return `Task "${taskName}" marked as incomplete`;
    }

    if (action === 'project_task_updated') {
      const taskName = metadata?.task_name ?? 'Task';
      const statusBefore = metadata?.status_before ?? null;
      const statusAfter = metadata?.status_after ?? null;

      if (statusBefore && statusAfter && statusBefore !== statusAfter) {
        return `Task "${taskName}" status changed: ${statusBefore} → ${statusAfter}`;
      }

      // Fallback if only due date or other fields changed
      return `Task "${taskName}" updated`;
    }

    if (action === 'project_tasks_reordered') {
      // Round 212: Handle project_tasks_reordered with fallback to description
      const phaseLabel = metadata?.phase_label ?? null;
      const taskCount = metadata?.task_count ?? (metadata?.task_ids_after?.length ?? 0);
      
      // If metadata is missing or malformed, fallback to description
      if (!metadata && item.description) {
        return item.description;
      }
      
      if (phaseLabel && phaseLabel.trim() !== '') {
        return `Reordered ${taskCount} task(s) in phase '${phaseLabel}'`;
      }
      
      return `Reordered ${taskCount} task(s) (no phase)`;
    }

    // Round 214: Handle assignment actions
    if (action === 'project_task_assigned') {
      const taskName = metadata?.task_name ?? 'this task';
      const newName = metadata?.new_assignee_name ?? 'Unknown user';
      
      // Fallback to description if metadata is missing
      if (!metadata && item.description) {
        return item.description;
      }
      
      return `Task '${taskName}' assigned to ${newName}`;
    }

    if (action === 'project_task_unassigned') {
      const taskName = metadata?.task_name ?? 'this task';
      const oldName = metadata?.old_assignee_name ?? null;
      
      // Fallback to description if metadata is missing
      if (!metadata && item.description) {
        return item.description;
      }
      
      if (oldName) {
        return `Task '${taskName}' unassigned (was ${oldName})`;
      }
      
      return `Task '${taskName}' unassigned`;
    }

    if (action === 'project_task_reassigned') {
      const taskName = metadata?.task_name ?? 'this task';
      const oldName = metadata?.old_assignee_name ?? 'Unknown user';
      const newName = metadata?.new_assignee_name ?? 'Unknown user';
      
      // Fallback to description if metadata is missing
      if (!metadata && item.description) {
        return item.description;
      }
      
      return `Task '${taskName}' reassigned from ${oldName} to ${newName}`;
    }

    return null;
  };

  if (isLoading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Project History</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            {[1, 2, 3].map((i) => (
              <div key={i} className="animate-pulse">
                <div className="h-4 bg-[var(--muted-surface)] rounded w-3/4 mb-2"></div>
                <div className="h-3 bg-[var(--muted-surface)] rounded w-1/2"></div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
    );
  }

  if (error) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Project History</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="text-center py-8 text-[var(--color-semantic-danger-600)]">
            <p className="text-sm">
              Error loading project history: {(error as Error).message}
            </p>
          </div>
        </CardContent>
      </Card>
    );
  }

  if (historyItems.length === 0) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Project History</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="text-center py-8 text-[var(--muted)]">
            <p className="text-sm">No history found for this project</p>
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>
          Project History
          {historyItems.length > 0 && (
            <span className="ml-2 text-sm font-normal text-[var(--muted)]">
              ({historyItems.length})
            </span>
          )}
        </CardTitle>
      </CardHeader>
      <CardContent>
        {/* Filter Bar */}
        <div className="mb-4 space-y-3">
          <div className="flex flex-col sm:flex-row gap-3">
            {/* Action Select */}
            <div className="w-full sm:w-48">
              <Select
                options={HISTORY_ACTIONS}
                value={action}
                onChange={handleActionChange}
                placeholder="All Actions"
                data-testid="history-action-select"
              />
            </div>
            {/* Entity Type Select */}
            <div className="w-full sm:w-48">
              <Select
                options={ENTITY_TYPES}
                value={entityType}
                onChange={handleEntityTypeChange}
                placeholder="All Entities"
                data-testid="history-entity-type-select"
              />
            </div>
            {/* Limit Select */}
            <div className="w-full sm:w-48">
              <Select
                options={LIMIT_OPTIONS}
                value={String(limit)}
                onChange={handleLimitChange}
                placeholder="50 items"
                data-testid="history-limit-select"
              />
            </div>
          </div>
        </div>
        <div className="space-y-4">
          {historyItems.map((item: any) => (
            <div
              key={item.id}
              className="p-4 border border-[var(--border)] rounded-lg hover:bg-[var(--muted-surface)] transition-colors"
            >
              <div className="flex items-start justify-between gap-4">
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 mb-2">
                    {item.action_label && (
                      <span className="text-sm font-semibold text-[var(--text)]">
                        {item.action_label}
                      </span>
                    )}
                    {item.action && !item.action_label && (
                      <span className="text-sm font-semibold text-[var(--text)] capitalize">
                        {item.action.replace('_', ' ')}
                      </span>
                    )}
                    {item.entity_type && (
                      <span className="text-xs px-2 py-1 rounded bg-[var(--muted-surface)] text-[var(--muted)]">
                        {item.entity_type}
                      </span>
                    )}
                  </div>
                  {/* Round 208: Display task activity text */}
                  {/* Round 214: Include assignment actions in task activity rendering */}
                  {(item.entity_type === 'ProjectTask' || 
                    item.action?.startsWith('project_task') ||
                    item.action === 'project_tasks_reordered') && renderTaskActivityText(item) && (
                    <p className="text-sm text-[var(--text)] mb-2">
                      {renderTaskActivityText(item)}
                    </p>
                  )}
                  {/* Fallback to message/description if not a task activity */}
                  {!(item.entity_type === 'ProjectTask' || item.action?.startsWith('project_task')) &&
                   (item.message || item.description) && (
                    <p className="text-sm text-[var(--text)] mb-2">
                      {item.message || item.description}
                    </p>
                  )}
                  {/* Round 191: Display version info for document activities */}
                  {item.entity_type === 'Document' && item.metadata && (
                    <div className="mt-2 mb-2" data-testid={`history-version-meta-${item.id}`}>
                      {item.action === 'document_version_restored' && item.metadata.version_number && (
                        <p className="text-xs text-[var(--muted)]">
                          Restored to version <strong className="text-[var(--text)]">{item.metadata.version_number}</strong>
                          {item.metadata.version_note && (
                            <> – Note: <span className="italic">{item.metadata.version_note}</span></>
                          )}
                        </p>
                      )}
                      {(item.action === 'document_updated' || item.action === 'document_uploaded') && 
                       item.metadata.version_number && item.metadata.version_note && (
                        <p className="text-xs text-[var(--muted)]">
                          Version <strong className="text-[var(--text)]">{item.metadata.version_number}</strong> – Note: <span className="italic">{item.metadata.version_note}</span>
                        </p>
                      )}
                      {(item.action === 'document_updated' || item.action === 'document_uploaded') && 
                       item.metadata.version_note && !item.metadata.version_number && (
                        <p className="text-xs text-[var(--muted)]">
                          Note: <span className="italic">{item.metadata.version_note}</span>
                        </p>
                      )}
                    </div>
                  )}
                  <div className="flex items-center gap-4 text-xs text-[var(--muted)]">
                    {item.user && (
                      <span>
                        By: <span className="font-medium text-[var(--text)]">{item.user.name}</span>
                      </span>
                    )}
                    {item.time_ago && (
                      <span>{item.time_ago}</span>
                    )}
                    {item.created_at && !item.time_ago && (
                      <span>
                        {new Date(item.created_at).toLocaleString()}
                      </span>
                    )}
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  );
};

