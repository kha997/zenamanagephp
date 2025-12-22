import React, { useState, useMemo, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Select, type SelectOption } from '../../../components/ui/primitives/Select';
import { useMyTasks } from '../hooks';
import { projectsApi } from '../api';
import type { ProjectTask } from '../api';
import { formatDate } from '../../../lib/utils';

/**
 * MyTasksPage Component
 * 
 * Round 213: My Tasks page - shows tasks assigned to the current user across projects
 * Round 217: Enhanced with date range filter, grouping by project/phase, and quick actions
 * Round 218: Added UX guard - when range=overdue, force status=open
 */
export const MyTasksPage: React.FC = () => {
  const [statusFilter, setStatusFilter] = useState<'open' | 'completed' | 'all'>('open');
  const [rangeFilter, setRangeFilter] = useState<'today' | 'next_7_days' | 'overdue' | 'all'>('all');
  const [updatingTaskIds, setUpdatingTaskIds] = useState<Set<string>>(new Set());
  const queryClient = useQueryClient();

  // UX Guard: When range=overdue, force status=open
  // Overdue tasks are only meaningful when they're open (not completed)
  useEffect(() => {
    if (rangeFilter === 'overdue' && statusFilter !== 'open') {
      setStatusFilter('open');
    }
  }, [rangeFilter, statusFilter]);

  const { data, isLoading, error, refetch } = useMyTasks({ 
    status: statusFilter,
    range: rangeFilter === 'all' ? undefined : rangeFilter,
  });

  // Status options for dropdown (matching ProjectTaskList)
  const statusOptions: SelectOption[] = [
    { value: '', label: '—' },
    { value: 'todo', label: 'Todo' },
    { value: 'in_progress', label: 'In Progress' },
    { value: 'done', label: 'Done' },
    { value: 'completed', label: 'Completed' },
  ];

  // Status filter options
  const statusFilterOptions: SelectOption[] = [
    { value: 'open', label: 'Open' },
    { value: 'completed', label: 'Completed' },
    { value: 'all', label: 'All' },
  ];

  // Date range filter options
  const rangeFilterOptions: SelectOption[] = [
    { value: 'all', label: 'All' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'today', label: 'Today' },
    { value: 'next_7_days', label: 'Next 7 days' },
  ];

  // Group and sort tasks
  const groupedTasks = useMemo(() => {
    if (!data?.data) return [];

    let tasks = [...data.data];
    const now = new Date();
    now.setHours(0, 0, 0, 0);

    // Sort: overdue first, then by due_date, then by sort_order
    tasks.sort((a, b) => {
      // Overdue tasks first
      const aOverdue = !a.is_completed && a.due_date && new Date(a.due_date) < now;
      const bOverdue = !b.is_completed && b.due_date && new Date(b.due_date) < now;
      if (aOverdue && !bOverdue) return -1;
      if (!aOverdue && bOverdue) return 1;

      // Then by due_date
      if (!a.due_date && !b.due_date) {
        return a.sort_order - b.sort_order;
      }
      if (!a.due_date) return 1;
      if (!b.due_date) return -1;
      
      const dateA = new Date(a.due_date);
      const dateB = new Date(b.due_date);
      const dateDiff = dateA.getTime() - dateB.getTime();
      if (dateDiff !== 0) return dateDiff;

      // Finally by sort_order
      return a.sort_order - b.sort_order;
    });

    // Group by project, then by phase
    const grouped: Record<string, Record<string, ProjectTask[]>> = {};

    tasks.forEach((task) => {
      const projectId = task.project_id;
      const projectName = task.project?.name || task.project?.code || `Project ${projectId}`;
      const phaseLabel = task.phase_label || 'No phase';

      if (!grouped[projectId]) {
        grouped[projectId] = {};
      }
      if (!grouped[projectId][phaseLabel]) {
        grouped[projectId][phaseLabel] = [];
      }
      grouped[projectId][phaseLabel].push(task);
    });

    return grouped;
  }, [data?.data]);

  // Check if task is overdue
  const isOverdue = (task: ProjectTask): boolean => {
    if (task.is_completed) return false;
    if (!task.due_date) return false;
    const now = new Date();
    now.setHours(0, 0, 0, 0);
    const dueDate = new Date(task.due_date);
    dueDate.setHours(0, 0, 0, 0);
    return dueDate < now;
  };

  // Get status badge class
  const getStatusBadgeClass = (status: string | null | undefined): string => {
    if (!status) return 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
    const statusLower = status.toLowerCase();
    if (statusLower === 'completed' || statusLower === 'done') {
      return 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300';
    }
    if (statusLower === 'in_progress' || statusLower === 'in-progress') {
      return 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300';
    }
    if (statusLower === 'pending' || statusLower === 'todo') {
      return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300';
    }
    return 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
  };

  // Handle complete/incomplete toggle
  const handleToggleComplete = async (task: ProjectTask) => {
    if (updatingTaskIds.has(task.id)) return;

    setUpdatingTaskIds(prev => new Set(prev).add(task.id));
    try {
      if (task.is_completed) {
        await projectsApi.incompleteProjectTask(task.project_id, task.id);
      } else {
        await projectsApi.completeProjectTask(task.project_id, task.id);
      }
      // Invalidate my-tasks query to refetch
      queryClient.invalidateQueries({ queryKey: ['my-tasks'] });
    } catch (error) {
      console.error('Failed to toggle task completion:', error);
    } finally {
      setUpdatingTaskIds(prev => {
        const next = new Set(prev);
        next.delete(task.id);
        return next;
      });
    }
  };

  // Handle status change
  const handleStatusChange = async (task: ProjectTask, newStatus: string) => {
    if (updatingTaskIds.has(task.id)) return;

    setUpdatingTaskIds(prev => new Set(prev).add(task.id));
    try {
      await projectsApi.updateProjectTask(task.project_id, task.id, {
        status: newStatus || null,
      });
      // Invalidate my-tasks query to refetch
      queryClient.invalidateQueries({ queryKey: ['my-tasks'] });
    } catch (error) {
      console.error('Failed to update task status:', error);
    } finally {
      setUpdatingTaskIds(prev => {
        const next = new Set(prev);
        next.delete(task.id);
        return next;
      });
    }
  };

  if (isLoading) {
    return (
      <div className="container mx-auto px-4 py-8">
        <Card>
          <CardHeader>
            <CardTitle>My Tasks</CardTitle>
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
      </div>
    );
  }

  if (error) {
    return (
      <div className="container mx-auto px-4 py-8">
        <Card>
          <CardHeader>
            <CardTitle>My Tasks</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-center py-8 text-[var(--muted)]">
              <p className="text-sm mb-2">
                Không tải được danh sách task. Vui lòng thử lại sau.
              </p>
              <p className="text-xs text-[var(--color-semantic-danger-600)]">
                {(error as Error).message || 'Unknown error'}
              </p>
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  const totalTasks = data?.data?.length || 0;
  const projectIds = Object.keys(groupedTasks);

  return (
    <div className="container mx-auto px-4 py-8">
      <Card>
        <CardHeader>
          <CardTitle>
            My Tasks
            {totalTasks > 0 && (
              <span className="ml-2 text-sm font-normal text-[var(--muted)]">
                ({totalTasks})
              </span>
            )}
          </CardTitle>
        </CardHeader>
        <CardContent>
          {/* Filters */}
          <div className="flex flex-wrap gap-4 mb-4 pb-4 border-b border-[var(--border)]">
            <div className="flex-1 min-w-[150px]">
              <label className="block text-xs font-medium text-[var(--muted)] mb-1">
                Status
              </label>
              <Select
                options={statusFilterOptions}
                value={statusFilter}
                onChange={(value) => setStatusFilter(value as 'open' | 'completed' | 'all')}
                disabled={rangeFilter === 'overdue'} // Disable status filter when overdue is selected
                style={{ width: '100%' }}
              />
              {rangeFilter === 'overdue' && (
                <p className="text-xs text-[var(--muted)] mt-1">
                  Overdue only applies to open tasks.
                </p>
              )}
            </div>
            <div className="flex-1 min-w-[150px]">
              <label className="block text-xs font-medium text-[var(--muted)] mb-1">
                Date Range
              </label>
              <Select
                options={rangeFilterOptions}
                value={rangeFilter}
                onChange={(value) => setRangeFilter(value as 'today' | 'next_7_days' | 'overdue' | 'all')}
                style={{ width: '100%' }}
              />
            </div>
          </div>

          {/* Tasks List - Grouped by Project and Phase */}
          {projectIds.length === 0 ? (
            <div className="text-center py-8 text-[var(--muted)]">
              <p className="text-sm mb-2">
                {statusFilter === 'open' && rangeFilter === 'overdue'
                  ? 'Không có task quá hạn nào.'
                  : statusFilter === 'open'
                  ? 'Bạn chưa có task nào được giao.'
                  : 'Không có task nào phù hợp với bộ lọc đã chọn.'}
              </p>
            </div>
          ) : (
            <div className="space-y-6">
              {projectIds.map((projectId) => {
                const project = data?.data?.find(t => t.project_id === projectId)?.project;
                const projectName = project?.name || project?.code || `Project ${projectId}`;
                const phases = Object.keys(groupedTasks[projectId]);

                return (
                  <div key={projectId} className="space-y-4">
                    {/* Project Header */}
                    <div className="flex items-center gap-2">
                      <h2 className="text-lg font-semibold text-[var(--text)]">
                        <Link
                          to={`/app/projects/${projectId}`}
                          className="hover:text-[var(--accent)] hover:underline"
                        >
                          {projectName}
                        </Link>
                        {project?.code && (
                          <span className="ml-2 text-sm font-normal text-[var(--muted)]">
                            ({project.code})
                          </span>
                        )}
                      </h2>
                    </div>

                    {/* Phases within Project */}
                    {phases.map((phaseLabel) => {
                      const phaseTasks = groupedTasks[projectId][phaseLabel];

                      return (
                        <div key={`${projectId}-${phaseLabel}`} className="ml-4 space-y-2">
                          {/* Phase Header */}
                          {phaseLabel !== 'No phase' && (
                            <h3 className="text-sm font-medium text-[var(--muted)] mb-2">
                              Phase: {phaseLabel}
                            </h3>
                          )}

                          {/* Tasks in Phase */}
                          {phaseTasks.map((task) => {
                            const taskIsOverdue = isOverdue(task);
                            const isCompleted = task.is_completed;
                            const isUpdating = updatingTaskIds.has(task.id);

                            return (
                              <div
                                key={task.id}
                                className={`border border-[var(--border)] rounded-lg p-4 hover:bg-[var(--muted-surface)] transition-colors ${
                                  isCompleted ? 'opacity-60' : ''
                                } ${taskIsOverdue ? 'border-red-300 dark:border-red-700 bg-red-50 dark:bg-red-900/20' : ''}`}
                              >
                                <div className="flex items-start justify-between gap-4">
                                  <div className="flex-1">
                                    <div className="flex items-center gap-2 mb-2">
                                      {/* Completion Checkbox */}
                                      <input
                                        type="checkbox"
                                        checked={isCompleted}
                                        onChange={() => handleToggleComplete(task)}
                                        disabled={isUpdating}
                                        className="w-4 h-4 rounded border-[var(--border)] text-[var(--accent)] focus:ring-2 focus:ring-[var(--accent)] cursor-pointer disabled:opacity-50"
                                      />
                                      <h3
                                        className={`text-sm font-medium ${
                                          isCompleted
                                            ? 'line-through text-[var(--muted)]'
                                            : 'text-[var(--text)]'
                                        }`}
                                      >
                                        {task.name}
                                      </h3>
                                      {taskIsOverdue && (
                                        <span className="text-xs text-red-600 dark:text-red-400">
                                          ⚠️ Quá hạn
                                        </span>
                                      )}
                                      {task.is_milestone && (
                                        <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300">
                                          Milestone
                                        </span>
                                      )}
                                    </div>
                                    {task.description && (
                                      <p className="text-xs text-[var(--muted)] mb-2 line-clamp-2">
                                        {task.description}
                                      </p>
                                    )}
                                    <div className="flex flex-wrap items-center gap-4 text-xs text-[var(--muted)]">
                                      {task.phase_label && phaseLabel !== 'No phase' && (
                                        <span>📋 {task.phase_label}</span>
                                      )}
                                      {task.due_date && (
                                        <span className={taskIsOverdue ? 'text-red-600 dark:text-red-400 font-medium' : ''}>
                                          📅 {formatDate(task.due_date)}
                                        </span>
                                      )}
                                      {task.status && (
                                        <span className={`px-2 py-1 rounded-full text-xs font-medium ${getStatusBadgeClass(task.status)}`}>
                                          {task.status}
                                        </span>
                                      )}
                                    </div>
                                  </div>
                                  <div className="flex items-center gap-2">
                                    {/* Status Dropdown */}
                                    <Select
                                      options={statusOptions}
                                      value={task.status || ''}
                                      onChange={(value) => handleStatusChange(task, value)}
                                      disabled={isUpdating}
                                      style={{ width: '140px' }}
                                    />
                                  </div>
                                </div>
                              </div>
                            );
                          })}
                        </div>
                      );
                    })}
                  </div>
                );
              })}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
};
