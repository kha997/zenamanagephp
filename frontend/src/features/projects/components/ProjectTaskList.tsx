import React, { useState, useMemo } from 'react';
import { DragDropContext, Droppable, Draggable, DropResult } from 'react-beautiful-dnd';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Select, type SelectOption } from '../../../components/ui/primitives/Select';
import { useProjectChecklistTasks, useUpdateProjectTask, useCompleteProjectTask, useIncompleteProjectTask, useReorderProjectTasks } from '../hooks';
import { useTenantMembers } from '../../tenant/hooks';
import type { ProjectTask } from '../api';
import { ChevronDown, ChevronRight, GripVertical } from 'lucide-react';

interface ProjectTaskListProps {
  projectId: string | number;
  filter?: 'all' | 'open' | 'completed' | 'overdue';
  sortBy?: 'order' | 'due_date' | 'status';
  onFilterChange?: (filter: 'all' | 'open' | 'completed' | 'overdue') => void;
  onSortChange?: (sortBy: 'order' | 'due_date' | 'status') => void;
}

/**
 * ProjectTaskList Component
 * 
 * Round 203: ProjectTasks checklist view
 * Round 207: Enhanced with checklist UI, status updates, due date updates, and complete/incomplete toggle
 * 
 * Displays checklist tasks auto-generated from TaskTemplates when creating a project from a template.
 * 
 * Features:
 * - Checkbox to toggle complete/incomplete
 * - Status dropdown for quick status updates
 * - Due date input for date management
 * - Filter by completion status and overdue
 * - Sort by order, due date, or status
 * - Visual indicators for completed and overdue tasks
 */
export const ProjectTaskList: React.FC<ProjectTaskListProps> = ({ 
  projectId,
  filter: externalFilter,
  sortBy: externalSortBy,
  onFilterChange,
  onSortChange,
}) => {
  const [internalFilter, setInternalFilter] = useState<'all' | 'open' | 'completed' | 'overdue'>('all');
  const [internalSortBy, setInternalSortBy] = useState<'order' | 'due_date' | 'status'>('order');
  const [updatingTaskIds, setUpdatingTaskIds] = useState<Set<string>>(new Set());
  const [collapsedGroups, setCollapsedGroups] = useState<Record<string, boolean>>({});

  const filter = externalFilter ?? internalFilter;
  const sortBy = externalSortBy ?? internalSortBy;

  const { data, isLoading, error } = useProjectChecklistTasks(projectId, {}, { page: 1, per_page: 100 });
  const updateTaskMutation = useUpdateProjectTask(projectId);
  const completeTaskMutation = useCompleteProjectTask(projectId);
  const incompleteTaskMutation = useIncompleteProjectTask(projectId);
  const reorderTaskMutation = useReorderProjectTasks(projectId);
  
  // Fetch tenant members for assignment dropdown
  const { data: membersData } = useTenantMembers({ per_page: 100 });

  // Status options for dropdown
  const statusOptions: SelectOption[] = [
    { value: '', label: '—' },
    { value: 'todo', label: 'Todo' },
    { value: 'in_progress', label: 'In Progress' },
    { value: 'done', label: 'Done' },
    { value: 'completed', label: 'Completed' },
  ];

  // Filter and sort tasks
  const filteredAndSortedTasks = useMemo(() => {
    if (!data?.data) return [];

    let arr = [...data.data];
    const now = new Date();
    now.setHours(0, 0, 0, 0);

    // Filter
    if (filter === 'open') {
      arr = arr.filter(t => !t.is_completed);
    } else if (filter === 'completed') {
      arr = arr.filter(t => t.is_completed);
    } else if (filter === 'overdue') {
      arr = arr.filter(t => {
        if (t.is_completed) return false;
        if (!t.due_date) return false;
        const dueDate = new Date(t.due_date);
        dueDate.setHours(0, 0, 0, 0);
        return dueDate < now;
      });
    }

    // Sort
    if (sortBy === 'order') {
      arr.sort((a, b) => {
        if (a.sort_order !== b.sort_order) {
          return a.sort_order - b.sort_order;
        }
        return new Date(a.created_at).getTime() - new Date(b.created_at).getTime();
      });
    } else if (sortBy === 'due_date') {
      arr.sort((a, b) => {
        if (!a.due_date && !b.due_date) return 0;
        if (!a.due_date) return 1;
        if (!b.due_date) return -1;
        return new Date(a.due_date).getTime() - new Date(b.due_date).getTime();
      });
    } else if (sortBy === 'status') {
      arr.sort((a, b) => {
        const statusA = a.status || '';
        const statusB = b.status || '';
        return statusA.localeCompare(statusB);
      });
    }

    return arr;
  }, [data?.data, filter, sortBy]);

  // Group tasks by phase_label
  const groupedTasks = useMemo(() => {
    const groups: Record<string, ProjectTask[]> = {};
    
    filteredAndSortedTasks.forEach(task => {
      const groupKey = task.phase_label || 'No phase';
      if (!groups[groupKey]) {
        groups[groupKey] = [];
      }
      groups[groupKey].push(task);
    });

    // Sort groups by min sort_order in the group, then alphabetically by phase_label
    const sortedGroupKeys = Object.keys(groups).sort((a, b) => {
      const minOrderA = Math.min(...groups[a].map(t => t.sort_order));
      const minOrderB = Math.min(...groups[b].map(t => t.sort_order));
      if (minOrderA !== minOrderB) {
        return minOrderA - minOrderB;
      }
      return a.localeCompare(b);
    });

    const sortedGroups: Record<string, ProjectTask[]> = {};
    sortedGroupKeys.forEach(key => {
      sortedGroups[key] = groups[key];
    });

    return sortedGroups;
  }, [filteredAndSortedTasks]);

  // Toggle group collapse/expand
  const toggleGroup = (groupKey: string) => {
    setCollapsedGroups(prev => ({
      ...prev,
      [groupKey]: !prev[groupKey],
    }));
  };

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

  // Handle complete/incomplete toggle
  const handleToggleComplete = async (task: ProjectTask) => {
    if (updatingTaskIds.has(task.id)) return;

    setUpdatingTaskIds(prev => new Set(prev).add(task.id));
    try {
      if (task.is_completed) {
        await incompleteTaskMutation.mutateAsync(task.id);
      } else {
        await completeTaskMutation.mutateAsync(task.id);
      }
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
      await updateTaskMutation.mutateAsync({
        taskId: task.id,
        payload: { status: newStatus || null },
      });
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

  // Handle due date change
  const handleDueDateChange = async (task: ProjectTask, newDate: string | null) => {
    if (updatingTaskIds.has(task.id)) return;

    setUpdatingTaskIds(prev => new Set(prev).add(task.id));
    try {
      await updateTaskMutation.mutateAsync({
        taskId: task.id,
        payload: { due_date: newDate || null },
      });
    } catch (error) {
      console.error('Failed to update task due date:', error);
    } finally {
      setUpdatingTaskIds(prev => {
        const next = new Set(prev);
        next.delete(task.id);
        return next;
      });
    }
  };

  // Handle assignee change
  const handleAssigneeChange = async (task: ProjectTask, newAssigneeId: string | null) => {
    if (updatingTaskIds.has(task.id)) return;

    setUpdatingTaskIds(prev => new Set(prev).add(task.id));
    try {
      await updateTaskMutation.mutateAsync({
        taskId: task.id,
        payload: { assignee_id: newAssigneeId || null },
      });
    } catch (error) {
      console.error('Failed to update task assignee:', error);
    } finally {
      setUpdatingTaskIds(prev => {
        const next = new Set(prev);
        next.delete(task.id);
        return next;
      });
    }
  };

  // Build assignee options from tenant members
  const assigneeOptions: SelectOption[] = useMemo(() => {
    const options: SelectOption[] = [
      { value: '', label: 'Unassigned' },
    ];
    
    if (membersData?.data?.members) {
      membersData.data.members.forEach((member: { id: string; name: string; email?: string }) => {
        options.push({
          value: member.id,
          label: member.name || member.email || `User ${member.id}`,
        });
      });
    }
    
    return options;
  }, [membersData]);

  // Format date for display
  const formatDate = (dateString: string | null | undefined): string => {
    if (!dateString) return '—';
    try {
      const date = new Date(dateString);
      return date.toLocaleDateString('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
      });
    } catch {
      return '—';
    }
  };

  // Format date for input (YYYY-MM-DD)
  const formatDateForInput = (dateString: string | null | undefined): string => {
    if (!dateString) return '';
    try {
      const date = new Date(dateString);
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    } catch {
      return '';
    }
  };

  // Get status badge class
  const getStatusBadgeClass = (status: string | null | undefined): string => {
    if (!status) return 'bg-gray-100 text-gray-700';
    const statusLower = status.toLowerCase();
    if (statusLower === 'completed' || statusLower === 'done') {
      return 'bg-green-100 text-green-700';
    }
    if (statusLower === 'in_progress' || statusLower === 'in-progress') {
      return 'bg-blue-100 text-blue-700';
    }
    if (statusLower === 'pending' || statusLower === 'todo') {
      return 'bg-yellow-100 text-yellow-700';
    }
    if (statusLower === 'cancelled' || statusLower === 'canceled') {
      return 'bg-gray-100 text-gray-500';
    }
    return 'bg-gray-100 text-gray-700';
  };

  // Filter options
  const filterOptions: SelectOption[] = [
    { value: 'all', label: 'Tất cả' },
    { value: 'open', label: 'Chưa hoàn thành' },
    { value: 'completed', label: 'Đã hoàn thành' },
    { value: 'overdue', label: 'Quá hạn' },
  ];

  // Sort options
  const sortOptions: SelectOption[] = [
    { value: 'order', label: 'Theo thứ tự' },
    { value: 'due_date', label: 'Theo hạn' },
    { value: 'status', label: 'Theo trạng thái' },
  ];

  const handleFilterChangeInternal = (value: string) => {
    const newFilter = value as 'all' | 'open' | 'completed' | 'overdue';
    if (onFilterChange) {
      onFilterChange(newFilter);
    } else {
      setInternalFilter(newFilter);
    }
  };

  const handleSortChangeInternal = (value: string) => {
    const newSortBy = value as 'order' | 'due_date' | 'status';
    if (onSortChange) {
      onSortChange(newSortBy);
    } else {
      setInternalSortBy(newSortBy);
    }
  };

  // Handle drag & drop end
  const handleDragEnd = async (result: DropResult) => {
    const { destination, source, draggableId } = result;

    // If dropped outside a droppable area or in the same position, do nothing
    if (!destination) {
      return;
    }

    if (
      destination.droppableId === source.droppableId &&
      destination.index === source.index
    ) {
      return;
    }

    // Only allow reordering within the same phase (same droppableId)
    if (destination.droppableId !== source.droppableId) {
      return; // Cross-phase drag is not allowed in this round
    }

    const phaseLabel = source.droppableId;
    const phaseTasks = groupedTasks[phaseLabel] || [];

    // Create new ordered array
    const newTasks = Array.from(phaseTasks);
    const [removed] = newTasks.splice(source.index, 1);
    newTasks.splice(destination.index, 0, removed);

    // Extract ordered IDs
    const orderedIds = newTasks.map((task) => task.id);

    // Call reorder mutation
    try {
      await reorderTaskMutation.mutateAsync({ orderedIds });
    } catch (error) {
      console.error('Failed to reorder tasks:', error);
      // On error, the query will be invalidated and refetched, so UI will reset
    }
  };

  if (isLoading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Tasks</CardTitle>
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
          <CardTitle>Tasks</CardTitle>
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
    );
  }

  const tasks = data?.data || [];

  if (tasks.length === 0) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Tasks</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="text-center py-8 text-[var(--muted)]">
            <p className="text-sm mb-2">
              Chưa có task nào cho dự án này.
            </p>
            <p className="text-xs">
              (Chưa áp dụng template hoặc template không có task)
            </p>
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>
          Tasks
          {filteredAndSortedTasks.length > 0 && (
            <span className="ml-2 text-sm font-normal text-[var(--muted)]">
              ({filteredAndSortedTasks.length})
            </span>
          )}
        </CardTitle>
      </CardHeader>
      <CardContent>
        {/* Filter and Sort Controls */}
        <div className="flex flex-wrap gap-4 mb-4 pb-4 border-b border-[var(--border)]">
          <div className="flex-1 min-w-[150px]">
            <label className="block text-xs font-medium text-[var(--muted)] mb-1">
              Lọc
            </label>
            <Select
              options={filterOptions}
              value={filter}
              onChange={handleFilterChangeInternal}
              style={{ width: '100%' }}
            />
          </div>
          <div className="flex-1 min-w-[150px]">
            <label className="block text-xs font-medium text-[var(--muted)] mb-1">
              Sắp xếp
            </label>
            <Select
              options={sortOptions}
              value={sortBy}
              onChange={handleSortChangeInternal}
              style={{ width: '100%' }}
            />
          </div>
        </div>

        {/* Tasks Table */}
        <DragDropContext onDragEnd={handleDragEnd}>
          <div className="overflow-x-auto">
            <table className="w-full border-collapse">
              <thead>
                <tr className="border-b border-[var(--border)]">
                  <th className="text-left py-2 px-3 text-xs font-medium text-[var(--muted)] w-12"></th>
                  <th className="text-left py-2 px-3 text-xs font-medium text-[var(--muted)] w-8"></th>
                  <th className="text-left py-2 px-3 text-xs font-medium text-[var(--muted)]">Task Name</th>
                  <th className="text-center py-2 px-3 text-xs font-medium text-[var(--muted)]">Milestone</th>
                  <th className="text-left py-2 px-3 text-xs font-medium text-[var(--muted)]">Status</th>
                  <th className="text-left py-2 px-3 text-xs font-medium text-[var(--muted)]">Due Date</th>
                  <th className="text-left py-2 px-3 text-xs font-medium text-[var(--muted)]">Assignee</th>
                  <th className="text-center py-2 px-3 text-xs font-medium text-[var(--muted)]">Source</th>
                </tr>
              </thead>
            </table>
            <div className="w-full table">
              {Object.entries(groupedTasks).map(([groupKey, tasks]) => {
                const isCollapsed = collapsedGroups[groupKey] ?? false;
                const completedCount = tasks.filter(t => t.is_completed).length;
                const totalCount = tasks.length;
                const phaseLabel = groupKey === 'No phase' ? 'Không phân phase' : groupKey;

                return (
                  <React.Fragment key={groupKey}>
                    {/* Group Header */}
                    <div className="bg-[var(--muted-surface)] border-b border-[var(--border)] py-2 px-3">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                          <button
                            onClick={() => toggleGroup(groupKey)}
                            className="p-1 hover:bg-[var(--muted)] rounded transition-colors"
                            title={isCollapsed ? 'Expand' : 'Collapse'}
                          >
                            {isCollapsed ? (
                              <ChevronRight className="w-4 h-4 text-[var(--muted)]" />
                            ) : (
                              <ChevronDown className="w-4 h-4 text-[var(--muted)]" />
                            )}
                          </button>
                          <span className="font-semibold text-sm text-[var(--text)]">
                            {phaseLabel}
                          </span>
                          <span className="text-xs px-2 py-0.5 rounded-full bg-[var(--muted)] text-[var(--muted-foreground)]">
                            {completedCount}/{totalCount}
                          </span>
                        </div>
                      </div>
                    </div>
                    {/* Group Tasks */}
                    {!isCollapsed && (
                      <Droppable droppableId={groupKey}>
                        {(provided, snapshot) => (
                          <div
                            ref={provided.innerRef}
                            {...provided.droppableProps}
                            className="table-row-group"
                            style={{
                              backgroundColor: snapshot.isDraggingOver ? 'rgba(59, 130, 246, 0.05)' : 'transparent',
                            }}
                          >
                            {tasks.map((task: ProjectTask, index: number) => {
                              const taskIsOverdue = isOverdue(task);
                              const isUpdating = updatingTaskIds.has(task.id);
                              const isCompleted = task.is_completed;
                              const isReordering = reorderTaskMutation.isPending;

                              return (
                                <Draggable
                                  key={task.id}
                                  draggableId={task.id}
                                  index={index}
                                  isDragDisabled={isReordering}
                                >
                                    {(provided, snapshot) => (
                                      <div
                                        ref={provided.innerRef}
                                        {...provided.draggableProps}
                                        className="table-row border-b border-[var(--border)] hover:bg-[var(--muted-surface)] transition-colors"
                                        style={{
                                          opacity: isCompleted ? 0.6 : 1,
                                          backgroundColor: snapshot.isDragging 
                                            ? 'var(--background)' 
                                            : taskIsOverdue 
                                              ? 'rgba(239, 68, 68, 0.1)' 
                                              : snapshot.isDraggingOver 
                                                ? 'rgba(59, 130, 246, 0.1)' 
                                                : 'transparent',
                                          boxShadow: snapshot.isDragging ? '0 10px 15px -3px rgba(0, 0, 0, 0.1)' : 'none',
                                        }}
                                      >
                                        {/* Drag Handle */}
                                        <div
                                          {...provided.dragHandleProps}
                                          className="table-cell py-3 px-3 cursor-grab active:cursor-grabbing w-8"
                                          title="Drag to reorder"
                                        >
                                          <GripVertical className="w-4 h-4 text-[var(--muted)]" />
                                        </div>

                                        {/* Checkbox */}
                                        <div className="table-cell py-3 px-3 w-12">
                                          <input
                                            type="checkbox"
                                            checked={isCompleted}
                                            onChange={() => handleToggleComplete(task)}
                                            disabled={isUpdating || isReordering}
                                            className="w-4 h-4 rounded border-[var(--border)] text-[var(--accent)] focus:ring-2 focus:ring-[var(--accent)] cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                                            title={isCompleted ? 'Mark as incomplete' : 'Mark as complete'}
                                          />
                                        </div>

                                        {/* Task Name */}
                                        <div className="table-cell py-3 px-3">
                                          <div className="flex flex-col">
                                            <span
                                              className={`text-sm font-medium ${
                                                isCompleted
                                                  ? 'line-through text-[var(--muted)]'
                                                  : 'text-[var(--text)]'
                                              }`}
                                            >
                                              {task.name}
                                            </span>
                                            {task.description && (
                                              <span
                                                className={`text-xs mt-1 line-clamp-2 ${
                                                  isCompleted ? 'text-[var(--muted)]' : 'text-[var(--muted)]'
                                                }`}
                                              >
                                                {task.description}
                                              </span>
                                            )}
                                            {taskIsOverdue && (
                                              <span className="text-xs text-red-600 dark:text-red-400 mt-1">
                                                ⚠️ Quá hạn
                                              </span>
                                            )}
                                          </div>
                                        </div>

                                        {/* Milestone */}
                                        <div className="table-cell py-3 px-3 text-center">
                                          {task.is_milestone ? (
                                            <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300">
                                              Milestone
                                            </span>
                                          ) : (
                                            <span className="text-xs text-[var(--muted)]">—</span>
                                          )}
                                        </div>

                                        {/* Status */}
                                        <div className="table-cell py-3 px-3">
                                          <div className="min-w-[120px]">
                                            <Select
                                              options={statusOptions}
                                              value={task.status || ''}
                                              onChange={(value) => handleStatusChange(task, value)}
                                              disabled={isUpdating || isReordering}
                                              style={{ width: '100%' }}
                                            />
                                          </div>
                                        </div>

                                        {/* Due Date */}
                                        <div className="table-cell py-3 px-3">
                                          <div className="flex items-center gap-2">
                                            <input
                                              type="date"
                                              value={formatDateForInput(task.due_date)}
                                              onChange={(e) => handleDueDateChange(task, e.target.value || null)}
                                              disabled={isUpdating || isReordering}
                                              className={`text-sm px-2 py-1 border border-[var(--border)] rounded focus:outline-none focus:ring-2 focus:ring-[var(--accent)] disabled:opacity-50 disabled:cursor-not-allowed ${
                                                taskIsOverdue ? 'border-red-300 dark:border-red-700' : ''
                                              }`}
                                            />
                                            {task.due_date && (
                                              <span className="text-xs text-[var(--muted)]">
                                                {formatDate(task.due_date)}
                                              </span>
                                            )}
                                          </div>
                                        </div>

                                        {/* Assignee */}
                                        <div className="table-cell py-3 px-3">
                                          <div className="min-w-[150px]">
                                            <Select
                                              options={assigneeOptions}
                                              value={task.assignee_id || ''}
                                              onChange={(value) => handleAssigneeChange(task, value || null)}
                                              disabled={isUpdating || isReordering}
                                              style={{ width: '100%' }}
                                            />
                                          </div>
                                        </div>

                                        {/* Source */}
                                        <div className="table-cell py-3 px-3 text-center">
                                          {task.template_task_id ? (
                                            <span
                                              className="inline-flex items-center text-xs text-[var(--muted)]"
                                              title="Created from template"
                                            >
                                              📋
                                            </span>
                                          ) : (
                                            <span className="text-xs text-[var(--muted)]">—</span>
                                          )}
                                        </div>
                                      </div>
                                    )}
                                </Draggable>
                              );
                            })}
                            {provided.placeholder}
                          </div>
                        )}
                      </Droppable>
                    )}
                  </React.Fragment>
                );
              })}
            </div>
          </div>
        </DragDropContext>

        {filteredAndSortedTasks.length === 0 && (
          <div className="text-center py-8 text-[var(--muted)]">
            <p className="text-sm">
              Không có task nào phù hợp với bộ lọc đã chọn.
            </p>
          </div>
        )}
      </CardContent>
    </Card>
  );
};
