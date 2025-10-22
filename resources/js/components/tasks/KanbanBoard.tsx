import React, { useState, useEffect, useCallback } from 'react';
import { Task, ULID } from '../../types/ulid';
import TaskRealtimeManager from '../../realtime/task-realtime';

export interface KanbanColumn {
  id: string;
  title: string;
  status: Task['status'];
  color: string;
  tasks: Task[];
}

interface KanbanBoardProps {
  tasks: Task[];
  loading?: boolean;
  error?: string;
  onTaskUpdate?: (taskId: ULID, updates: Partial<Task>) => Promise<void>;
  onTaskDelete?: (taskId: ULID) => Promise<void>;
  onTaskEdit?: (taskId: ULID) => void;
  className?: string;
}

// Constants
const COLUMNS: Omit<KanbanColumn, 'tasks'>[] = [
  {
    id: 'backlog',
    title: 'Backlog',
    status: 'backlog',
    color: 'bg-gray-100 border-gray-300'
  },
  {
    id: 'in_progress',
    title: 'In Progress',
    status: 'in_progress',
    color: 'bg-blue-100 border-blue-300'
  },
  {
    id: 'blocked',
    title: 'Blocked',
    status: 'blocked',
    color: 'bg-red-100 border-red-300'
  },
  {
    id: 'done',
    title: 'Done',
    status: 'done',
    color: 'bg-green-100 border-green-300'
  }
];

const PRIORITY_COLORS = {
  low: 'bg-gray-100 text-gray-800',
  normal: 'bg-blue-100 text-blue-800',
  high: 'bg-orange-100 text-orange-800',
  urgent: 'bg-red-100 text-red-800'
};

const PRIORITY_LABELS = {
  low: 'Low',
  normal: 'Normal',
  high: 'High',
  urgent: 'Urgent'
};

export const KanbanBoard: React.FC<KanbanBoardProps> = ({
  tasks,
  loading = false,
  error = null,
  onTaskUpdate,
  onTaskDelete,
  onTaskEdit,
  className = ''
}) => {
  const [columns, setColumns] = useState<KanbanColumn[]>([]);
  const [draggedTask, setDraggedTask] = useState<Task | null>(null);
  const [draggedOverColumn, setDraggedOverColumn] = useState<string | null>(null);
  const [currentUserId, setCurrentUserId] = useState<string | null>(null);

  // Get current user ID
  useEffect(() => {
    const userIdMeta = document.querySelector('meta[name="user-id"]');
    if (userIdMeta) {
      setCurrentUserId(userIdMeta.getAttribute('content'));
    }
  }, []);

  // Setup real-time updates
  useEffect(() => {
    if (!currentUserId) return;

    // Subscribe to tenant-wide events for all tasks
    const tenantId = document.querySelector('meta[name="tenant-id"]')?.getAttribute('content');
    if (tenantId) {
      TaskRealtimeManager.subscribeToTenant(tenantId, {
        onTaskStatusUpdated: (data) => {
          // Only update if it's not from current user (avoid duplicates)
          if (data.user.id !== currentUserId) {
            handleRealtimeTaskUpdate(data);
          }
        }
      });
    }

    return () => {
      // Cleanup subscriptions when component unmounts
      if (tenantId) {
        TaskRealtimeManager.unsubscribe(`tenant.${tenantId}`);
      }
    };
  }, [currentUserId]);

  // Handle real-time task updates
  const handleRealtimeTaskUpdate = useCallback((data: any) => {
    setColumns(prevColumns =>
      prevColumns.map(col => {
        if (col.status === data.old_status) {
          // Remove task from old column
          return { ...col, tasks: col.tasks.filter(task => task.id !== data.id) };
        }
        if (col.status === data.new_status) {
          // Add task to new column
          const updatedTask = {
            ...data,
            status: data.new_status,
            progress_percent: data.progress_percent
          };
          return { ...col, tasks: [...col.tasks, updatedTask] };
        }
        return col;
      })
    );
  }, []);

  // Initialize columns with tasks
  useEffect(() => {
    const initializedColumns = COLUMNS.map(column => ({
      ...column,
      tasks: tasks.filter(task => task.status === column.status)
    }));
    setColumns(initializedColumns);
  }, [tasks]);

  // Handle drag start
  const handleDragStart = useCallback((e: React.DragEvent, task: Task) => {
    setDraggedTask(task);
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/html', e.currentTarget.outerHTML);
  }, []);

  // Handle drag over
  const handleDragOver = useCallback((e: React.DragEvent, columnId: string) => {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    setDraggedOverColumn(columnId);
  }, []);

  // Handle drag leave
  const handleDragLeave = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setDraggedOverColumn(null);
  }, []);

  // Handle drop
  const handleDrop = useCallback(async (e: React.DragEvent, targetColumnId: string) => {
    e.preventDefault();
    
    if (!draggedTask || !onTaskUpdate) return;

    const targetColumn = columns.find(col => col.id === targetColumnId);
    if (!targetColumn || draggedTask.status === targetColumn.status) {
      setDraggedTask(null);
      setDraggedOverColumn(null);
      return;
    }

    // Optimistic update
    const updatedColumns = columns.map(col => ({
      ...col,
      tasks: col.tasks.filter(task => task.id !== draggedTask.id)
    }));

    const targetColIndex = updatedColumns.findIndex(col => col.id === targetColumnId);
    if (targetColIndex !== -1) {
      updatedColumns[targetColIndex].tasks.push({
        ...draggedTask,
        status: targetColumn.status
      });
    }

    setColumns(updatedColumns);

    try {
      await onTaskUpdate(draggedTask.id, { status: targetColumn.status });
    } catch (error) {
      console.error('Failed to update task status:', error);
      // Revert optimistic update
      setColumns(columns);
    }

    setDraggedTask(null);
    setDraggedOverColumn(null);
  }, [draggedTask, columns, onTaskUpdate]);

  // Handle task edit
  const handleTaskEdit = useCallback((taskId: string) => {
    if (onTaskEdit) {
      onTaskEdit(taskId);
    }
  }, [onTaskEdit]);

  // Handle task delete
  const handleTaskDelete = useCallback(async (taskId: string) => {
    if (!onTaskDelete || !confirm('Are you sure you want to delete this task?')) return;

    try {
      await onTaskDelete(taskId);
      // Remove task from columns
      setColumns(prevColumns => 
        prevColumns.map(col => ({
          ...col,
          tasks: col.tasks.filter(task => task.id !== taskId)
        }))
      );
    } catch (error) {
      console.error('Failed to delete task:', error);
    }
  }, [onTaskDelete]);

  // Format date
  const formatDate = (dateString?: string) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric'
    });
  };

  // Format time ago
  const formatTimeAgo = (dateString: string) => {
    const now = new Date();
    const date = new Date(dateString);
    const diff = now.getTime() - date.getTime();
    
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);
    
    if (minutes < 60) return `${minutes}m ago`;
    if (hours < 24) return `${hours}h ago`;
    return `${days}d ago`;
  };

  if (loading) {
    return (
      <div className={`flex items-center justify-center h-64 ${className}`}>
        <div className="flex items-center space-x-2">
          <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
          <span className="text-gray-600">Loading tasks...</span>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className={`flex items-center justify-center h-64 ${className}`}>
        <div className="text-center">
          <div className="text-red-500 text-lg mb-2">
            <i className="fas fa-exclamation-triangle"></i>
          </div>
          <p className="text-gray-600">{error}</p>
        </div>
      </div>
    );
  }

  return (
    <div className={`kanban-board ${className}`} data-testid="react-kanban-board">
      <div className="flex space-x-4 overflow-x-auto pb-4">
        {columns.map(column => (
          <div
            key={column.id}
            data-testid="kanban-column"
            className={`flex-shrink-0 w-80 ${column.color} rounded-lg border-2 p-4 ${
              draggedOverColumn === column.id ? 'border-dashed border-blue-400' : ''
            }`}
            onDragOver={(e) => handleDragOver(e, column.id)}
            onDragLeave={handleDragLeave}
            onDrop={(e) => handleDrop(e, column.id)}
          >
            {/* Column Header */}
            <div className="flex items-center justify-between mb-4">
              <h3 className="font-semibold text-gray-800">{column.title}</h3>
              <span className="bg-white text-gray-600 text-sm px-2 py-1 rounded-full">
                {column.tasks.length}
              </span>
            </div>

            {/* Tasks */}
            <div className="space-y-3 min-h-[200px]">
              {column.tasks.map(task => (
                <div
                  key={task.id}
                  data-testid="kanban-task"
                  data-task-id={task.id}
                  draggable
                  onDragStart={(e) => handleDragStart(e, task)}
                  className="bg-white rounded-lg shadow-sm border border-gray-200 p-3 cursor-move hover:shadow-md transition-shadow"
                >
                  {/* Task Header */}
                  <div className="flex items-start justify-between mb-2">
                    <h4 className="text-sm font-medium text-gray-900 line-clamp-2 flex-1">
                      {task.name}
                    </h4>
                    <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ml-2 ${PRIORITY_COLORS[task.priority]}`}>
                      {PRIORITY_LABELS[task.priority]}
                    </span>
                  </div>

                  {/* Task Description */}
                  {task.description && (
                    <p className="text-xs text-gray-500 mb-2 line-clamp-2">
                      {task.description}
                    </p>
                  )}

                  {/* Progress Bar */}
                  {task.progress_percent !== undefined && (
                    <div className="mb-2">
                      <div className="flex justify-between text-xs text-gray-600 mb-1">
                        <span>Progress</span>
                        <span>{task.progress_percent}%</span>
                      </div>
                      <div className="w-full bg-gray-200 rounded-full h-1.5">
                        <div
                          className="bg-blue-600 h-1.5 rounded-full transition-all duration-300"
                          style={{ width: `${task.progress_percent}%` }}
                        ></div>
                      </div>
                    </div>
                  )}

                  {/* Task Meta */}
                  <div className="flex items-center justify-between text-xs text-gray-500">
                    <div className="flex items-center space-x-2">
                      {task.assignee && (
                        <div className="flex items-center">
                          <div className="w-4 h-4 bg-blue-500 rounded-full flex items-center justify-center text-white text-xs">
                            {task.assignee.name.charAt(0).toUpperCase()}
                          </div>
                          <span className="ml-1">{task.assignee.name}</span>
                        </div>
                      )}
                      {task.end_date && (
                        <span className="flex items-center">
                          <i className="fas fa-calendar-alt mr-1"></i>
                          {formatDate(task.end_date)}
                        </span>
                      )}
                    </div>
                    <span>{formatTimeAgo(task.updated_at)}</span>
                  </div>

                  {/* Task Actions */}
                  <div className="flex items-center justify-end mt-2 pt-2 border-t border-gray-100">
                    <div className="flex items-center space-x-2">
                      <button
                        onClick={() => handleTaskEdit(task.id)}
                        data-testid="edit-task-button"
                        className="text-blue-600 hover:text-blue-800 text-xs"
                        title="Edit task"
                      >
                        <i className="fas fa-edit"></i>
                      </button>
                      <button
                        onClick={() => handleTaskDelete(task.id)}
                        data-testid="delete-task-button"
                        className="text-red-600 hover:text-red-800 text-xs"
                        title="Delete task"
                      >
                        <i className="fas fa-trash"></i>
                      </button>
                    </div>
                  </div>
                </div>
              ))}

              {/* Empty State */}
              {column.tasks.length === 0 && (
                <div className="text-center py-8 text-gray-500">
                  <i className="fas fa-inbox text-2xl mb-2"></i>
                  <p className="text-sm">No tasks</p>
                </div>
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};

export default KanbanBoard;
