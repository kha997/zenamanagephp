import React, { useState, useMemo, useCallback, useEffect, useRef } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { DragDropContext, Droppable, Draggable, DropResult, DragOverResult } from '@hello-pangea/dnd';
import toast from 'react-hot-toast';
import { useQueryClient } from '@tanstack/react-query';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { KpiStrip } from '../../../components/shared/KpiStrip';
import { AlertBar } from '../../../components/shared/AlertBar';
import { ActivityFeed } from '../../../components/shared/ActivityFeed';
import { EmptyState } from '../../../components/shared/EmptyState';
import { SmartFilters, type FilterPreset } from '../../../components/shared/SmartFilters';
import { Button } from '../../../components/ui/primitives/Button';
import { Input } from '../../../components/ui/primitives/Input';
import { useTheme } from '../../../shared/theme/ThemeProvider';
import { useTasks, useTasksKpis, useTasksActivity, useTasksAlerts, useUpdateTask, useBulkDeleteTasks, useBulkUpdateStatus, useBulkAssignTasks } from '../hooks';
import { tasksApi } from '../api';
import { mapToBackendStatus, mapToFrontendStatus } from '../../../shared/utils/taskStatusMapper';
import { TaskMoveReasonModal } from '../components/TaskMoveReasonModal';
import { TaskMoveErrorModal } from '../components/TaskMoveErrorModal';
import { InvalidDropFeedback } from '../components/InvalidDropFeedback';
import { useTaskTransitionValidation } from '../hooks/useTaskTransitionValidation';
import { animateRollback } from '../utils/rollbackAnimation';
import { TaskStatusTooltip } from '../components/TaskStatusTooltip';
import { useAuthStore } from '../../auth/store';
import type { KpiItem } from '../../../components/shared/KpiStrip';
import type { Alert } from '../../../components/shared/AlertBar';
import type { Activity } from '../../../components/shared/ActivityFeed';
import type { TaskFilters } from '../types';
import type { Task } from '../types';

type ViewMode = 'table' | 'card' | 'kanban';

export const TasksListPage: React.FC = () => {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const queryClient = useQueryClient();
  const { theme } = useTheme();
  const { hasTenantPermission } = useAuthStore();
  
  // View mode state with persistence
  const [viewMode, setViewMode] = useState<ViewMode>(() => {
    const saved = localStorage.getItem('tasks_view_mode');
    return (saved as ViewMode) || 'card';
  });
  
  // Persist view mode changes
  useEffect(() => {
    localStorage.setItem('tasks_view_mode', viewMode);
  }, [viewMode]);
  
  // KPI period state for trend comparison
  const [kpiPeriod, setKpiPeriod] = useState<'week' | 'month'>('week');
  
  // Loading state for drag operations
  const [draggingTaskId, setDraggingTaskId] = useState<string | null>(null);
  const [isDragging, setIsDragging] = useState(false);
  
  // Reason modal state
  const [reasonModal, setReasonModal] = useState<{
    isOpen: boolean;
    taskId: string;
    taskTitle: string;
    targetStatus: string;
    pendingMove: {
      destination: { droppableId: string; index: number };
      source: { droppableId: string; index: number };
      draggableId: string;
    } | null;
  }>({
    isOpen: false,
    taskId: '',
    taskTitle: '',
    targetStatus: '',
    pendingMove: null,
  });

  // Error modal state
  const [errorModal, setErrorModal] = useState<{
    isOpen: boolean;
    error: { code: string; message: string; details?: any };
    task: Task | null;
    targetStatus: string;
  }>({
    isOpen: false,
    error: { code: '', message: '' },
    task: null,
    targetStatus: '',
  });

  // Invalid drop feedback state
  const [invalidDropTarget, setInvalidDropTarget] = useState<{
    columnId: string | null;
    reason: string;
  }>({ columnId: null, reason: '' });

  // Task transition validation hook
  const { canMoveToStatus } = useTaskTransitionValidation();

  // Original task position for rollback animation
  const [originalTaskPosition, setOriginalTaskPosition] = useState<{
    taskId: string;
    position: { x: number; y: number };
  } | null>(null);
  
  // Bulk selection state
  const [selectedTasks, setSelectedTasks] = useState<Set<string | number>>(new Set());
  const [bulkAction, setBulkAction] = useState<'delete' | 'status' | 'assign' | null>(null);
  const [bulkStatus, setBulkStatus] = useState<string>('');
  const [bulkAssigneeId, setBulkAssigneeId] = useState<string | number>('');
  
  // Filters state - initialize empty (no filters by default)
  // Filters will only be applied when user explicitly sets them via UI
  const [filters, setFilters] = useState<TaskFilters>({});
  
  // Search input state (for debouncing) - initialize empty
  const [searchInput, setSearchInput] = useState('');
  
  // Pagination state - initialize to page 1
  const [page, setPage] = useState(1);
  const [perPage] = useState(12);
  
  // For Kanban view, we need to load all tasks to group them correctly
  // Otherwise, pending tasks on other pages won't show in the Kanban columns
  const effectivePerPage = viewMode === 'kanban' ? 1000 : perPage; // Load up to 1000 tasks for Kanban view
  
  // Track if we've cleared filters on mount to avoid syncing from URL immediately after
  const hasClearedFiltersOnMount = useRef(false);
  
  // Clear URL params on mount to ensure fresh start (no filters by default)
  // This ensures that when user refreshes the page, all tasks are shown
  useEffect(() => {
    // Check if URL has any filter params
    const hasFilters = searchParams.has('search') || 
                      searchParams.has('status') || 
                      searchParams.has('priority') || 
                      searchParams.has('project_id') || 
                      searchParams.has('assignee_id');
    
    // If URL has filter params, clear them and navigate to clean URL
    if (hasFilters) {
      if (process.env.NODE_ENV === 'development') {
        console.log('[TasksListPage] Clearing URL filters on mount:', Object.fromEntries(searchParams));
      }
      // Clear all filter params but keep page if it's not 1
      const newParams = new URLSearchParams();
      const pageParam = searchParams.get('page');
      if (pageParam && pageParam !== '1') {
        newParams.set('page', pageParam);
      }
      setSearchParams(newParams);
      // Reset filters state to empty
      setFilters({});
      setSearchInput('');
      setPage(pageParam ? parseInt(pageParam, 10) : 1);
      hasClearedFiltersOnMount.current = true;
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []); // Only run on mount
  
  // Sync filters from URL params when URL changes (e.g., when clicking KPI cards or filter buttons)
  // This ensures filters state stays in sync with URL params when user interacts with UI
  useEffect(() => {
    // Skip syncing if we just cleared filters on mount (to avoid re-applying cleared filters)
    if (hasClearedFiltersOnMount.current) {
      hasClearedFiltersOnMount.current = false;
      return;
    }
    
    const urlSearch = searchParams.get('search') || '';
    const urlStatus = searchParams.get('status') || '';
    const urlPriority = searchParams.get('priority') || '';
    const urlProjectId = searchParams.get('project_id') || undefined;
    const urlAssigneeId = searchParams.get('assignee_id') || undefined;
    const urlPage = parseInt(searchParams.get('page') || '1', 10);
    
    // Build new filters object - only include non-empty values
    const newFilters: TaskFilters = {};
    if (urlSearch) newFilters.search = urlSearch;
    if (urlStatus) newFilters.status = urlStatus;
    if (urlPriority) newFilters.priority = urlPriority;
    if (urlProjectId) newFilters.project_id = urlProjectId;
    if (urlAssigneeId) newFilters.assignee_id = urlAssigneeId;
    
    // Update filters if URL params changed
    setFilters(prev => {
      // Normalize both objects for comparison (handle undefined vs missing key)
      const prevNormalized = {
        search: prev.search || undefined,
        status: prev.status || undefined,
        priority: prev.priority || undefined,
        project_id: prev.project_id || undefined,
        assignee_id: prev.assignee_id || undefined,
      };
      const newNormalized = {
        search: newFilters.search || undefined,
        status: newFilters.status || undefined,
        priority: newFilters.priority || undefined,
        project_id: newFilters.project_id || undefined,
        assignee_id: newFilters.assignee_id || undefined,
      };
      
      const changed = 
        prevNormalized.search !== newNormalized.search ||
        prevNormalized.status !== newNormalized.status ||
        prevNormalized.priority !== newNormalized.priority ||
        prevNormalized.project_id !== newNormalized.project_id ||
        prevNormalized.assignee_id !== newNormalized.assignee_id;
      
      if (changed) {
        if (process.env.NODE_ENV === 'development') {
          console.log('[TasksListPage] Syncing filters from URL:', { 
            prev, 
            prevNormalized,
            newFilters, 
            newNormalized,
            urlParams: Object.fromEntries(searchParams),
            changed
          });
        }
        return newFilters;
      }
      return prev;
    });
    
    // Sync search input if search param changed (only if different to avoid loops)
    setSearchInput(prev => urlSearch !== prev ? urlSearch : prev);
    
    // Sync page if page param changed (only if different to avoid loops)
    setPage(prev => urlPage !== prev ? urlPage : prev);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchParams]); // Only depend on searchParams to avoid infinite loops
  
  // Debounce search input
  useEffect(() => {
    const timer = setTimeout(() => {
      setFilters(prev => ({ ...prev, search: searchInput }));
      setSearchParams(prev => {
        const newParams = new URLSearchParams(prev);
        if (searchInput) {
          newParams.set('search', searchInput);
        } else {
          newParams.delete('search');
        }
        newParams.set('page', '1');
        return newParams;
      });
      setPage(1);
    }, 300);
    
    return () => clearTimeout(timer);
  }, [searchInput, setSearchParams]);
  
  // Fetch data with filters and pagination
  // Use effectivePerPage to load all tasks for Kanban view
  // Include viewMode in query key to refetch when switching views
  const { data: tasksData, isLoading: tasksLoading, error: tasksError, refetch: refetchTasks } = useTasks(
    filters,
    { page: viewMode === 'kanban' ? 1 : page, per_page: effectivePerPage }
  );
  
  // Debug logging in development
  useEffect(() => {
    if (process.env.NODE_ENV === 'development') {
      console.log('[TasksListPage] Current state:', {
        filters,
        searchParams: Object.fromEntries(searchParams),
        tasksData: tasksData?.data?.length || 0,
        total: tasksData?.meta?.total || 0,
        isLoading: tasksLoading,
        error: tasksError,
      });
    }
  }, [filters, searchParams, tasksData, tasksLoading, tasksError]);
  const { data: kpisData, isLoading: kpisLoading, error: kpisError } = useTasksKpis(kpiPeriod);
  const { data: activityData, isLoading: activityLoading, error: activityError } = useTasksActivity(10);
  const { data: alertsData, isLoading: alertsLoading, error: alertsError } = useTasksAlerts();
  const updateTask = useUpdateTask();
  const bulkDeleteTasks = useBulkDeleteTasks();
  const bulkUpdateStatus = useBulkUpdateStatus();
  const bulkAssignTasks = useBulkAssignTasks();
  
  // Track dismissed alerts locally (since these are temporary alerts)
  const [dismissedAlerts, setDismissedAlerts] = useState<Set<string | number>>(new Set());
  
  // Filter out dismissed alerts
  const activeAlerts = useMemo(() => {
    if (!alertsData?.data) return [];
    return alertsData.data.filter(alert => !dismissedAlerts.has(alert.id));
  }, [alertsData, dismissedAlerts]);
  
  // Handle dismiss single alert
  const handleDismissAlert = useCallback((id: string | number) => {
    setDismissedAlerts(prev => new Set(prev).add(id));
  }, []);
  
  // Handle dismiss all alerts
  const handleDismissAllAlerts = useCallback(() => {
    if (!alertsData?.data) return;
    const allIds = alertsData.data.map(alert => alert.id);
    setDismissedAlerts(prev => new Set([...prev, ...allIds]));
  }, [alertsData]);

  // Transform KPI data to KpiItem format
  const kpiItems: KpiItem[] = useMemo(() => {
    // Handle error case: return empty KPIs with fallback values
    if (kpisError) {
      console.error('Failed to load KPI data:', kpisError);
      // Return empty KPIs - KpiStrip will handle loading/error states
      return [];
    }
    
    if (!kpisData?.data) return [];
    
    const kpis = kpisData.data;
    const period = kpis.period || kpiPeriod;
    
    // Helper function to format trend change with percentage
    const formatTrendChange = (change?: number): string | undefined => {
      if (change === undefined || change === null || change === 0) return undefined;
      return `${change > 0 ? '+' : ''}${change.toFixed(1)}%`;
    };
    
    // Helper function to determine trend direction
    const getTrendDirection = (change?: number): 'up' | 'down' | 'neutral' => {
      if (change === undefined || change === null || change === 0) return 'neutral';
      return change > 0 ? 'up' : 'down';
    };
    
    // Helper function to get period label
    const getPeriodLabel = (): string => {
      return kpiPeriod === 'week' ? 'vs previous week' : 'vs previous month';
    };
    
    return [
      {
        label: 'Total Tasks',
        value: kpis.total || 0,
        variant: 'default',
        change: formatTrendChange(kpis.total_change),
        trend: getTrendDirection(kpis.total_change),
        periodLabel: getPeriodLabel(),
        onClick: () => {
          setFilters({ search: '', status: '', priority: '', project_id: undefined, assignee_id: undefined });
          setSearchInput('');
          navigate('/app/tasks');
        },
        actionLabel: 'View all',
      },
      {
        label: 'Pending Tasks',
        value: kpis.pending || 0,
        variant: 'warning',
        change: formatTrendChange(kpis.pending_change),
        trend: getTrendDirection(kpis.pending_change),
        periodLabel: getPeriodLabel(),
        onClick: () => {
          setFilters(prev => ({ ...prev, status: 'pending' }));
          navigate('/app/tasks?status=pending');
        },
        actionLabel: 'View pending',
      },
      {
        label: 'In Progress',
        value: kpis.in_progress || 0,
        variant: 'info',
        change: formatTrendChange(kpis.in_progress_change),
        trend: getTrendDirection(kpis.in_progress_change),
        periodLabel: getPeriodLabel(),
        onClick: () => {
          setFilters(prev => ({ ...prev, status: 'in_progress' }));
          navigate('/app/tasks?status=in_progress');
        },
        actionLabel: 'View in progress',
      },
      {
        label: 'Completed',
        value: kpis.completed || 0,
        variant: 'success',
        change: formatTrendChange(kpis.completed_change),
        trend: getTrendDirection(kpis.completed_change),
        periodLabel: getPeriodLabel(),
        onClick: () => {
          setFilters(prev => ({ ...prev, status: 'completed' }));
          navigate('/app/tasks?status=completed');
        },
        actionLabel: 'View completed',
      },
      {
        label: 'Overdue',
        value: kpis.overdue || 0,
        variant: kpis.overdue > 0 ? 'danger' : 'default',
        change: formatTrendChange(kpis.overdue_change),
        trend: getTrendDirection(kpis.overdue_change),
        periodLabel: getPeriodLabel(),
        onClick: () => {
          setFilters(prev => ({ ...prev, status: 'overdue' }));
          navigate('/app/tasks?status=overdue');
        },
        actionLabel: kpis.overdue > 0 ? 'View overdue' : undefined,
      },
    ];
  }, [kpisData, kpiPeriod, kpisError, navigate, setFilters]);

  // Transform alerts data to Alert format (only active alerts)
  const alerts: Alert[] = useMemo(() => {
    if (!activeAlerts || activeAlerts.length === 0) return [];
    return activeAlerts.map((alert: any) => ({
      id: alert.id,
      message: alert.message || alert.title || 'Alert',
      type: alert.type || alert.severity || 'info',
      priority: alert.priority || 0,
      created_at: alert.created_at || alert.createdAt,
      dismissed: false, // Already filtered out dismissed alerts
    }));
  }, [activeAlerts]);

  // Transform activity data to Activity format
  const activities: Activity[] = useMemo(() => {
    if (!activityData?.data) return [];
    return Array.isArray(activityData.data)
      ? activityData.data.map((activity: any) => ({
          id: activity.id,
          type: activity.type || 'task',
          action: activity.action,
          description: activity.description || activity.message || 'Activity',
          timestamp: activity.timestamp || activity.created_at || activity.createdAt,
          user: activity.user,
          metadata: activity.metadata,
        }))
      : [];
  }, [activityData]);

  // Filter presets
  const filterPresets: FilterPreset[] = useMemo(() => [
    {
      id: 'pending',
      name: 'Pending',
      filters: { status: 'pending' },
      icon: '⏳',
    },
    {
      id: 'in_progress',
      name: 'In Progress',
      filters: { status: 'in_progress' },
      icon: '🔄',
    },
    {
      id: 'completed',
      name: 'Completed',
      filters: { status: 'completed' },
      icon: '✅',
    },
    {
      id: 'overdue',
      name: 'Overdue',
      filters: { status: 'overdue' },
      icon: '⚠️',
    },
  ], []);

  // Filter options
  const filterOptions = useMemo(() => ({
    status: [
      { id: 'pending', label: 'Pending', value: 'pending' },
      { id: 'in_progress', label: 'In Progress', value: 'in_progress' },
      { id: 'completed', label: 'Completed', value: 'completed' },
      { id: 'cancelled', label: 'Cancelled', value: 'cancelled' },
    ],
    priority: [
      { id: 'low', label: 'Low', value: 'low' },
      { id: 'medium', label: 'Medium', value: 'medium' },
      { id: 'high', label: 'High', value: 'high' },
      { id: 'urgent', label: 'Urgent', value: 'urgent' },
    ],
  }), []);

  const handleFilterChange = useCallback((newFilters: TaskFilters) => {
    // Clean up empty strings and undefined values
    const cleanedFilters: TaskFilters = {};
    Object.entries(newFilters).forEach(([key, value]) => {
      if (value !== null && value !== undefined && value !== '') {
        cleanedFilters[key as keyof TaskFilters] = value;
      }
    });
    
    if (process.env.NODE_ENV === 'development') {
      console.log('[TasksListPage] Filter change:', { newFilters, cleanedFilters });
    }
    
    setFilters(cleanedFilters);
    setSearchParams(prev => {
      const newParams = new URLSearchParams(prev);
      Object.entries(cleanedFilters).forEach(([key, value]) => {
        if (value) {
          newParams.set(key, String(value));
        } else {
          newParams.delete(key);
        }
      });
      newParams.set('page', '1');
      return newParams;
    });
    setPage(1);
  }, [setSearchParams]);


  // Check if any filters are active
  const hasActiveFilters = useMemo(() => {
    return !!(
      filters.search ||
      filters.status ||
      filters.priority ||
      filters.project_id ||
      filters.assignee_id
    );
  }, [filters]);

  // Pagination helpers
  const paginationMeta = tasksData?.meta;
  const totalPages = paginationMeta?.last_page || 1;
  const currentPage = paginationMeta?.current_page || page;
  const total = paginationMeta?.total || 0;

  const handlePageChange = useCallback((newPage: number) => {
    setPage(newPage);
    setSearchParams(prev => {
      const newParams = new URLSearchParams(prev);
      newParams.set('page', String(newPage));
      return newParams;
    });
  }, [setSearchParams]);

  // Generate page numbers for pagination
  const getPageNumbers = useCallback(() => {
    const pages: (number | string)[] = [];
    const maxVisible = 5; // Show max 5 page numbers
    const delta = 2; // Show 2 pages on each side of current page

    if (totalPages <= maxVisible) {
      // Show all pages if total is small
      for (let i = 1; i <= totalPages; i++) {
        pages.push(i);
      }
    } else {
      // Always show first page
      pages.push(1);

      // Calculate start and end of visible range
      let start = Math.max(2, currentPage - delta);
      let end = Math.min(totalPages - 1, currentPage + delta);

      // Adjust if we're near the start
      if (currentPage <= delta + 1) {
        end = Math.min(maxVisible, totalPages - 1);
      }

      // Adjust if we're near the end
      if (currentPage >= totalPages - delta) {
        start = Math.max(2, totalPages - maxVisible + 1);
      }

      // Add ellipsis before range if needed
      if (start > 2) {
        pages.push('...');
      }

      // Add page numbers in range
      for (let i = start; i <= end; i++) {
        pages.push(i);
      }

      // Add ellipsis after range if needed
      if (end < totalPages - 1) {
        pages.push('...');
      }

      // Always show last page
      if (totalPages > 1) {
        pages.push(totalPages);
      }
    }

    return pages;
  }, [currentPage, totalPages]);

  // Helper function to get stable task ID (always string, never null/undefined)
  const getStableTaskId = useCallback((task: Task): string => {
    // Task ID is ULID (string) from backend, but ensure it's always a string
    if (!task.id) {
      // This should not happen - task should always have an ID
      if (process.env.NODE_ENV === 'development') {
        console.error('Task missing ID:', task);
      }
      // Generate a temporary stable ID for debugging - but this should never happen
      return `temp-${Date.now()}-${Math.random()}`;
    }
    return String(task.id);
  }, []);

  // Group tasks by status for kanban view
  // Use state to freeze groupedTasks during drag operations
  const [frozenGroupedTasks, setFrozenGroupedTasks] = useState<Record<string, Task[]> | null>(null);

  const groupedTasks = useMemo(() => {
    if (!tasksData?.data) {
      return {
        pending: [],
        in_progress: [],
        completed: [],
        cancelled: [],
        backlog: [],
        blocked: [],
        done: [],
        canceled: [],
      };
    }
    const grouped: Record<string, Task[]> = {
      pending: [],
      in_progress: [],
      completed: [],
      cancelled: [],
      backlog: [],
      blocked: [],
      done: [],
      canceled: [],
    };
    
    // Filter out tasks without ID before grouping
    const validTasks = tasksData.data.filter(task => {
      if (!task.id) {
        if (process.env.NODE_ENV === 'development') {
          console.warn('Task missing ID - filtering out from Kanban:', task);
        }
        return false;
      }
      return true;
    });
    
    validTasks.forEach(task => {
      const status = task.status || 'backlog';
      
      // Map backend statuses to frontend display statuses
      // Backend: backlog, in_progress, blocked, done, canceled (and legacy: pending, completed, cancelled)
      // Frontend Kanban columns: pending (backlog + legacy pending), in_progress, completed (done + legacy completed), cancelled (canceled + legacy cancelled)
      // Also support blocked column
      let displayStatus: string;
      if (status === 'backlog' || status === 'pending') {
        // Map both backlog and legacy pending to pending column - matches KPI counting logic
        displayStatus = 'pending';
      } else if (status === 'done' || status === 'completed') {
        // Map both done and legacy completed to completed column - matches KPI counting logic
        displayStatus = 'completed';
      } else if (status === 'canceled' || status === 'cancelled') {
        // Map both canceled and legacy cancelled to cancelled column - matches KPI counting logic
        displayStatus = 'cancelled';
      } else if (status === 'blocked') {
        displayStatus = 'blocked'; // Show blocked tasks in blocked column (if we add it)
      } else {
        displayStatus = status; // in_progress and other statuses pass through
      }
      
      // Ensure task is added to the correct group
      if (grouped[displayStatus]) {
        grouped[displayStatus].push(task);
      } else {
        // Fallback to pending for unknown statuses
        if (process.env.NODE_ENV === 'development') {
          console.warn('[TasksListPage] Unknown task status:', status, 'for task:', task.id, 'displayed as:', displayStatus);
        }
        grouped.pending.push(task);
      }
    });
    
    return grouped;
  }, [tasksData]);

  // Use frozen tasks if dragging, otherwise use current groupedTasks
  // Memoize to prevent unnecessary re-renders
  const displayGroupedTasks = useMemo(() => {
    return draggingTaskId && frozenGroupedTasks ? frozenGroupedTasks : groupedTasks;
  }, [draggingTaskId, frozenGroupedTasks, groupedTasks]);

  // Handle drag start - set loading state and freeze groupedTasks
  const handleDragStart = useCallback((start: { draggableId: string }) => {
    // Set isDragging flag to prevent refetch/invalidate during drag
    setIsDragging(true);
    
    const allTaskIds = Object.values(groupedTasks).flat().map(t => getStableTaskId(t));
    const taskExists = allTaskIds.includes(start.draggableId);
    
    if (!taskExists) {
      // Task not found - this should not happen in normal flow
      if (process.env.NODE_ENV === 'development') {
        console.error('Task not found in groupedTasks:', start.draggableId);
      }
      setIsDragging(false);
      // Don't proceed if task doesn't exist
      return;
    }
    
    setDraggingTaskId(start.draggableId);
    // Freeze groupedTasks during drag to prevent re-render issues
    // Deep clone to prevent reference issues
    const frozen: Record<string, Task[]> = {};
    Object.keys(groupedTasks).forEach(key => {
      frozen[key] = [...groupedTasks[key]];
    });
    setFrozenGroupedTasks(frozen);

    // Store original position for rollback animation
    setTimeout(() => {
      const taskElement = document.querySelector(`[data-task-id="${start.draggableId}"]`);
      if (taskElement) {
        const rect = taskElement.getBoundingClientRect();
        setOriginalTaskPosition({
          taskId: start.draggableId,
          position: { x: rect.left, y: rect.top }
        });
      }
    }, 0);
  }, [groupedTasks, getStableTaskId]);

  // Perform the actual move operation
  const performMove = useCallback(async (
    task: Task,
    backendStatus: string,
    destination: { droppableId: string; index: number },
    source: { droppableId: string; index: number },
    reason?: string
  ) => {
    const taskId = getStableTaskId(task);
    const originalTask = { ...task };

    // Calculate before_id and after_id for positioning
    // Use current groupedTasks to find tasks in target column
    const targetColumnKey = destination.droppableId;
    const targetColumnTasks = groupedTasks[targetColumnKey] || [];
    
    // Filter out the task being moved from the target column
    const tasksInTargetColumn = targetColumnTasks.filter(t => getStableTaskId(t) !== taskId);
    
    const beforeId = destination.index > 0 && tasksInTargetColumn[destination.index - 1]
      ? getStableTaskId(tasksInTargetColumn[destination.index - 1])
      : undefined;
    const afterId = destination.index < tasksInTargetColumn.length && tasksInTargetColumn[destination.index]
      ? getStableTaskId(tasksInTargetColumn[destination.index])
      : undefined;

    // Optimistic update
    const optimisticStatus = mapToFrontendStatus(backendStatus);
    queryClient.setQueriesData(
      { queryKey: ['tasks'] },
      (oldData: any) => {
        if (!oldData?.data || !Array.isArray(oldData.data)) return oldData;
        return {
          ...oldData,
          data: oldData.data.map((t: Task) => 
            getStableTaskId(t) === taskId 
              ? { ...t, status: optimisticStatus, version: (t.version || 1) + 1 }
              : t
          ),
        };
      }
    );

    try {
      // Call move API
      const result = await tasksApi.moveTask(taskId, {
        to_status: backendStatus,
        before_id: beforeId,
        after_id: afterId,
        reason: reason,
        version: task.version,
      });

      // Update cache with response
      const updatedTask = result?.data || { ...task, status: optimisticStatus };
      queryClient.setQueriesData(
        { queryKey: ['tasks'] },
        (oldData: any) => {
          if (!oldData?.data || !Array.isArray(oldData.data)) return oldData;
          return {
            ...oldData,
            data: oldData.data.map((t: Task) => 
              getStableTaskId(t) === taskId ? updatedTask : t
            ),
          };
        }
      );

      // Show warning if present
      if (result.warning) {
        toast.success('Task moved', {
          description: result.warning,
        });
      } else {
        toast.success('Task moved', {
          description: `Task status changed to ${backendStatus.replace('_', ' ')}`,
        });
      }

      // Invalidate and refetch
      setTimeout(() => {
        queryClient.invalidateQueries({ queryKey: ['tasks'] });
        queryClient.invalidateQueries({ queryKey: ['tasks', 'kpis'] });
        refetchTasks();
        setIsDragging(false);
        setOriginalTaskPosition(null);
      }, 300);
    } catch (error: any) {
      console.error('❌ Failed to move task:', error);
      
      // Rollback
      queryClient.setQueriesData(
        { queryKey: ['tasks'] },
        (oldData: any) => {
          if (!oldData?.data || !Array.isArray(oldData.data)) return oldData;
          return {
            ...oldData,
            data: oldData.data.map((t: Task) => 
              getStableTaskId(t) === taskId ? originalTask : t
            ),
          };
        }
      );

      // Extract error info
      const errorResponse = error?.response?.data?.error || {};
      const errorCode = errorResponse.code || 'UNKNOWN_ERROR';
      const errorMessage = errorResponse.message || error?.message || 'Failed to move task';
      const errorDetails = errorResponse.details || {};
      
      // Animate rollback if position stored
      if (originalTaskPosition && originalTaskPosition.taskId === taskId) {
        setTimeout(() => {
          const taskElement = document.querySelector(`[data-task-id="${taskId}"]`);
          if (taskElement) {
            animateRollback(taskElement as HTMLElement, originalTaskPosition.position, () => {
              setOriginalTaskPosition(null);
            });
          }
        }, 50);
      }
      
      // Determine handling strategy
      const complexErrorCodes = [
        'dependencies_incomplete',
        'project_status_restricted',
        'invalid_transition',
        'optimistic_lock_conflict',
        'dependents_active'
      ];
      
      if (complexErrorCodes.includes(errorCode)) {
        // Show mini modal for complex errors
        setErrorModal({
          isOpen: true,
          error: { code: errorCode, message: errorMessage, details: errorDetails },
          task,
          targetStatus: backendStatus
        });
      } else {
        // Simple toast for other errors
        toast.error('Move failed', {
          description: errorMessage,
          duration: 4000
        });
      }

      setTimeout(() => {
        refetchTasks();
        setIsDragging(false);
      }, 200);
    }
  }, [groupedTasks, queryClient, refetchTasks, getStableTaskId]);

  // Handle drag and drop for Kanban view with new move API
  const handleDragEnd = useCallback(async (result: DropResult) => {
    const { destination, source, draggableId } = result;

    // Always clear frozen state when drag ends
    setDraggingTaskId(null);
    setFrozenGroupedTasks(null);

    // If dropped outside a droppable area, do nothing
    if (!destination) {
      setIsDragging(false);
      setOriginalTaskPosition(null);
      return;
    }

    // If dropped in the same position, do nothing
    if (
      destination.droppableId === source.droppableId &&
      destination.index === source.index
    ) {
      setIsDragging(false);
      setOriginalTaskPosition(null);
      return;
    }

    // Get the new status from destination droppableId
    const frontendStatus = destination.droppableId;
    const taskId = draggableId;

    // Find the task being moved
    const allTasks = frozenGroupedTasks 
      ? Object.values(frozenGroupedTasks).flat()
      : (tasksData?.data || []);
    
    const task = allTasks.find(t => getStableTaskId(t) === taskId);
    if (!task) {
      if (process.env.NODE_ENV === 'development') {
        console.error('Task not found in handleDragEnd:', taskId);
      }
      setIsDragging(false);
      return;
    }

    // Map frontend status to backend status
    const backendStatus = mapToBackendStatus(frontendStatus);
    
    // Check if status actually changed (after mapping)
    const currentBackendStatus = mapToBackendStatus(task.status || 'backlog');
    if (currentBackendStatus === backendStatus) {
      setIsDragging(false);
      return;
    }

    // Check if reason is required (blocked or canceled)
    if (backendStatus === 'blocked' || backendStatus === 'canceled') {
      // Show reason modal
      setReasonModal({
        isOpen: true,
        taskId: String(taskId),
        taskTitle: task.title,
        targetStatus: backendStatus,
        pendingMove: { destination, source, draggableId },
      });
      setIsDragging(false);
      return;
    }

    // Proceed with move (no reason required)
    await performMove(task, backendStatus, destination, source);
  }, [tasksData, frozenGroupedTasks, getStableTaskId, performMove]);

  // Handle reason modal confirm
  const handleReasonConfirm = useCallback(async (reason: string) => {
    if (!reasonModal.pendingMove) {
      setReasonModal({ isOpen: false, taskId: '', taskTitle: '', targetStatus: '', pendingMove: null });
      return;
    }

    const { destination, source, draggableId } = reasonModal.pendingMove;
    const taskId = draggableId;

    // Find task
    const allTasks = tasksData?.data || [];
    const task = allTasks.find(t => getStableTaskId(t) === taskId);
    
    if (!task) {
      toast.error('Task not found');
      setReasonModal({ isOpen: false, taskId: '', taskTitle: '', targetStatus: '', pendingMove: null });
      return;
    }

    // Close modal
    setReasonModal({ isOpen: false, taskId: '', taskTitle: '', targetStatus: '', pendingMove: null });

    // Perform move with reason
    await performMove(task, reasonModal.targetStatus, destination, source, reason);
  }, [reasonModal, tasksData, performMove, getStableTaskId]);

  // Handle reason modal cancel
  const handleReasonCancel = useCallback(() => {
    setReasonModal({ isOpen: false, taskId: '', taskTitle: '', targetStatus: '', pendingMove: null });
    setIsDragging(false);
  }, []);

  // Handle error modal action
  const handleErrorModalAction = useCallback((action: string, data?: any) => {
    switch (action) {
      case 'view_dependencies':
        navigate(`/app/tasks?dependencies=${data?.taskId}`);
        break;
      case 'view_project':
        navigate(`/app/projects/${data?.projectId}`);
        break;
      case 'refresh':
        window.location.reload();
        break;
      default:
        console.warn('Unknown action:', action);
    }
  }, [navigate]);

  const getStatusBadgeClass = (status: string) => {
    const isDark = theme === 'dark';
    switch (status) {
      case 'completed':
        return isDark 
          ? 'bg-green-900 text-green-300' 
          : 'bg-green-100 text-green-700';
      case 'in_progress':
        return isDark 
          ? 'bg-blue-900 text-blue-300' 
          : 'bg-blue-100 text-blue-700';
      case 'pending':
        return isDark 
          ? 'bg-yellow-900 text-yellow-300' 
          : 'bg-yellow-100 text-yellow-700';
      case 'cancelled':
        return isDark 
          ? 'bg-gray-700 text-gray-300' 
          : 'bg-gray-100 text-gray-500';
      default:
        return isDark 
          ? 'bg-gray-700 text-gray-300' 
          : 'bg-gray-100 text-gray-500';
    }
  };

  const getPriorityBadgeClass = (priority?: string) => {
    switch (priority) {
      case 'urgent':
        return 'text-red-600 font-semibold';
      case 'high':
        return 'text-orange-600 font-medium';
      case 'medium':
        return 'text-yellow-600';
      case 'low':
        return 'text-gray-500';
      default:
        return 'text-gray-400';
    }
  };

  // Bulk selection handlers
  const toggleTaskSelection = useCallback((taskId: string | number) => {
    setSelectedTasks(prev => {
      const newSet = new Set(prev);
      if (newSet.has(taskId)) {
        newSet.delete(taskId);
      } else {
        newSet.add(taskId);
      }
      return newSet;
    });
  }, []);

  const selectAllTasks = useCallback(() => {
    if (!tasksData?.data) return;
    setSelectedTasks(new Set(tasksData.data.map(t => t.id)));
  }, [tasksData]);

  const clearSelection = useCallback(() => {
    setSelectedTasks(new Set());
    setBulkAction(null);
    setBulkStatus('');
    setBulkAssigneeId('');
  }, []);

  const handleBulkAction = useCallback(async () => {
    if (selectedTasks.size === 0 || !bulkAction) return;

    const ids = Array.from(selectedTasks);

    try {
      switch (bulkAction) {
        case 'delete':
          await bulkDeleteTasks.mutateAsync(ids);
          toast.success('Tasks deleted', {
            description: `Successfully deleted ${ids.length} task(s)`,
          });
          break;
        case 'status':
          if (!bulkStatus) {
            toast.error('Status required', {
              description: 'Please select a status',
            });
            return;
          }
          await bulkUpdateStatus.mutateAsync({ ids, status: bulkStatus });
          toast.success('Tasks updated', {
            description: `Successfully updated ${ids.length} task(s) to ${bulkStatus}`,
          });
          break;
        case 'assign':
          if (!bulkAssigneeId) {
            toast.error('Assignee required', {
              description: 'Please select an assignee',
            });
            return;
          }
          await bulkAssignTasks.mutateAsync({ ids, assigneeId: bulkAssigneeId });
          toast.success('Tasks assigned', {
            description: `Successfully assigned ${ids.length} task(s)`,
          });
          break;
      }
      clearSelection();
      refetchTasks();
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'Failed to perform bulk action';
      toast.error('Action failed', {
        description: errorMessage,
      });
    }
  }, [selectedTasks, bulkAction, bulkStatus, bulkAssigneeId, bulkDeleteTasks, bulkUpdateStatus, bulkAssignTasks, clearSelection, refetchTasks]);

  return (
    <Container>
      <div className="space-y-6">
        {/* Page Header */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 className="text-[var(--font-heading-3-size)] font-semibold text-[var(--text)]">Tasks</h1>
            <p className="text-[var(--font-body-size)] text-[var(--muted)] mt-1">
              Manage and track all your tasks
            </p>
          </div>
          <div className="flex items-center gap-3">
            {/* View Mode Toggle */}
            <div className="flex items-center gap-1 border border-[var(--border)] rounded-lg p-1">
              <button
                onClick={() => setViewMode('table')}
                className={`px-3 py-1.5 text-sm rounded transition-colors ${
                  viewMode === 'table'
                    ? 'bg-[var(--primary-button-bg)] text-[var(--primary-button-text)]'
                    : 'text-[var(--muted)] hover:bg-[var(--muted-surface)]'
                }`}
                aria-pressed={viewMode === 'table'}
                aria-label="Table view"
              >
                Table
              </button>
              <button
                onClick={() => setViewMode('card')}
                className={`px-3 py-1.5 text-sm rounded transition-colors ${
                  viewMode === 'card'
                    ? 'bg-[var(--primary-button-bg)] text-[var(--primary-button-text)]'
                    : 'text-[var(--muted)] hover:bg-[var(--muted-surface)]'
                }`}
                aria-pressed={viewMode === 'card'}
                aria-label="Card view"
              >
                Cards
              </button>
              <button
                onClick={() => setViewMode('kanban')}
                className={`px-3 py-1.5 text-sm rounded transition-colors ${
                  viewMode === 'kanban'
                    ? 'bg-[var(--primary-button-bg)] text-[var(--primary-button-text)]'
                    : 'text-[var(--muted)] hover:bg-[var(--muted-surface)]'
                }`}
                aria-pressed={viewMode === 'kanban'}
                aria-label="Kanban view"
              >
                Kanban
              </button>
            </div>
            {/* New Task Button */}
            {(hasTenantPermission('tenant.manage_tasks') || hasTenantPermission('tenant.create_tasks')) && (
              <Button onClick={() => navigate('/app/tasks/create')}>
                New Task
              </Button>
            )}
          </div>
        </div>

        {/* KPI Strip */}
        <KpiStrip
          kpis={kpiItems}
          loading={kpisLoading}
          columns={5}
          period={kpiPeriod}
          onPeriodChange={setKpiPeriod}
          showPeriodSelector={true}
        />

        {/* Alert Bar */}
        <AlertBar
          alerts={alerts}
          loading={alertsLoading}
          error={alertsError as Error | null}
          onDismiss={handleDismissAlert}
          onDismissAll={handleDismissAllAlerts}
        />

        {/* Bulk Actions Toolbar */}
        {selectedTasks.size > 0 && (
          <Card>
            <CardContent className="pt-4">
              <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                  <span className="text-sm text-[var(--text)]">
                    {selectedTasks.size} task{selectedTasks.size !== 1 ? 's' : ''} selected
                  </span>
                  <Button
                    variant="tertiary"
                    size="sm"
                    onClick={clearSelection}
                  >
                    Clear
                  </Button>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                  {bulkAction === null && (
                    <>
                      <Button
                        variant="danger"
                        size="sm"
                        onClick={() => setBulkAction('delete')}
                      >
                        Delete
                      </Button>
                      <Button
                        variant="secondary"
                        size="sm"
                        onClick={() => setBulkAction('status')}
                      >
                        Change Status
                      </Button>
                      <Button
                        variant="secondary"
                        size="sm"
                        onClick={() => setBulkAction('assign')}
                      >
                        Assign
                      </Button>
                    </>
                  )}
                  {bulkAction === 'status' && (
                    <>
                      <select
                        value={bulkStatus}
                        onChange={(e) => setBulkStatus(e.target.value)}
                        className="px-3 py-1.5 text-sm border border-[var(--border)] rounded-lg bg-[var(--surface)] text-[var(--text)]"
                      >
                        <option value="">Select status</option>
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                      </select>
                      <Button
                        variant="primary"
                        size="sm"
                        onClick={handleBulkAction}
                        disabled={!bulkStatus || bulkUpdateStatus.isPending}
                      >
                        {bulkUpdateStatus.isPending ? 'Updating...' : 'Update'}
                      </Button>
                      <Button
                        variant="tertiary"
                        size="sm"
                        onClick={() => {
                          setBulkAction(null);
                          setBulkStatus('');
                        }}
                      >
                        Cancel
                      </Button>
                    </>
                  )}
                  {bulkAction === 'assign' && (
                    <>
                      <Input
                        type="text"
                        placeholder="Assignee ID"
                        value={bulkAssigneeId}
                        onChange={(e) => setBulkAssigneeId(e.target.value)}
                        className="w-32"
                      />
                      <Button
                        variant="primary"
                        size="sm"
                        onClick={handleBulkAction}
                        disabled={!bulkAssigneeId || bulkAssignTasks.isPending}
                      >
                        {bulkAssignTasks.isPending ? 'Assigning...' : 'Assign'}
                      </Button>
                      <Button
                        variant="tertiary"
                        size="sm"
                        onClick={() => {
                          setBulkAction(null);
                          setBulkAssigneeId('');
                        }}
                      >
                        Cancel
                      </Button>
                    </>
                  )}
                  {bulkAction === 'delete' && (
                    <>
                      <Button
                        variant="danger"
                        size="sm"
                        onClick={handleBulkAction}
                        disabled={bulkDeleteTasks.isPending}
                      >
                        {bulkDeleteTasks.isPending ? 'Deleting...' : 'Confirm Delete'}
                      </Button>
                      <Button
                        variant="tertiary"
                        size="sm"
                        onClick={() => setBulkAction(null)}
                      >
                        Cancel
                      </Button>
                    </>
                  )}
                </div>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Search and Filters */}
        <Card>
          <CardContent className="space-y-4 pt-6">
            {/* Search Input */}
            <div className="max-w-md">
              <Input
                type="search"
                placeholder="Search tasks..."
                value={searchInput}
                onChange={(e) => setSearchInput(e.target.value)}
                leadingIcon={<span>🔍</span>}
              />
            </div>

            {/* Smart Filters */}
            <SmartFilters
              filters={filterOptions}
              values={filters}
              onChange={handleFilterChange}
              presets={filterPresets}
            />
          </CardContent>
        </Card>

        {/* Main Content */}
        <Card>
          <CardHeader>
            <CardTitle>
              All Tasks
              {total > 0 && (
                <span className="ml-2 text-sm font-normal text-[var(--muted)]">
                  ({total} {total === 1 ? 'task' : 'tasks'})
                </span>
              )}
            </CardTitle>
          </CardHeader>
          <CardContent>
            {tasksLoading ? (
              <div className="space-y-4">
                {[1, 2, 3].map((i) => (
                  <div key={i} className="animate-pulse">
                    <div className="h-4 bg-[var(--muted-surface)] rounded w-3/4 mb-2"></div>
                    <div className="h-3 bg-[var(--muted-surface)] rounded w-1/2"></div>
                  </div>
                ))}
              </div>
            ) : tasksError ? (
              <div className="text-center text-[var(--muted)] py-8">
                <p className="text-sm">Error loading tasks: {(tasksError as Error).message}</p>
                <Button
                  variant="secondary"
                  onClick={() => window.location.reload()}
                  className="mt-4"
                >
                  Retry
                </Button>
              </div>
            ) : tasksData?.data && tasksData.data.length > 0 ? (
              <>
                {/* Table View */}
                {viewMode === 'table' && (
                  <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                      <thead>
                        <tr className="border-b border-[var(--border)]">
                          <th className="w-12 py-3 px-4">
                            <input
                              type="checkbox"
                              checked={tasksData.data.length > 0 && tasksData.data.every(t => selectedTasks.has(t.id))}
                              onChange={(e) => {
                                if (e.target.checked) {
                                  selectAllTasks();
                                } else {
                                  clearSelection();
                                }
                              }}
                              onClick={(e) => e.stopPropagation()}
                              className="w-4 h-4 rounded border-[var(--border)] text-[var(--accent)] focus:ring-2 focus:ring-[var(--accent)]"
                            />
                          </th>
                          <th className="text-left py-3 px-4 font-semibold text-[var(--text)]">Task</th>
                          <th className="text-left py-3 px-4 font-semibold text-[var(--text)]">Status</th>
                          <th className="text-left py-3 px-4 font-semibold text-[var(--text)]">Priority</th>
                          <th className="text-left py-3 px-4 font-semibold text-[var(--text)]">Due Date</th>
                          <th className="text-right py-3 px-4 font-semibold text-[var(--text)]">Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        {tasksData.data.map((task) => (
                          <tr
                            key={task.id}
                            className={`border-b border-[var(--border)] hover:bg-[var(--muted-surface)] transition-colors ${
                              selectedTasks.has(task.id) ? 'bg-[var(--accent)] bg-opacity-5' : ''
                            }`}
                          >
                            <td className="py-3 px-4" onClick={(e) => e.stopPropagation()}>
                              <input
                                type="checkbox"
                                checked={selectedTasks.has(task.id)}
                                onChange={() => toggleTaskSelection(task.id)}
                                className="w-4 h-4 rounded border-[var(--border)] text-[var(--accent)] focus:ring-2 focus:ring-[var(--accent)]"
                              />
                            </td>
                            <td 
                              className="py-3 px-4 cursor-pointer"
                              onClick={() => navigate(`/app/tasks/${task.id}`)}
                            >
                              <div className="font-semibold text-[var(--text)]">{task.title}</div>
                              {task.description && (
                                <div className="text-xs text-[var(--muted)] mt-1 line-clamp-1">
                                  {task.description}
                                </div>
                              )}
                            </td>
                            <td className="py-3 px-4">
                              <span className={`text-xs px-2 py-1 rounded ${getStatusBadgeClass(task.status)}`}>
                                {task.status.replace('_', ' ')}
                              </span>
                            </td>
                            <td className="py-3 px-4">
                              <span className={`text-xs capitalize ${getPriorityBadgeClass(task.priority)}`}>
                                {task.priority || '—'}
                              </span>
                            </td>
                            <td className="py-3 px-4 text-[var(--muted)]">
                              {task.due_date ? new Date(task.due_date).toLocaleDateString() : '—'}
                            </td>
                            <td className="py-3 px-4 text-right">
                              <Button
                                variant="tertiary"
                                size="sm"
                                onClick={(e) => {
                                  e.stopPropagation();
                                  navigate(`/app/tasks/${task.id}`);
                                }}
                              >
                                View
                              </Button>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}

                {/* Card View */}
                {viewMode === 'card' && (
                  <div>
                    {/* Select All Checkbox */}
                    {tasksData.data.length > 0 && (
                      <div className="mb-4 flex items-center gap-2">
                        <input
                          type="checkbox"
                          checked={tasksData.data.length > 0 && tasksData.data.every(t => selectedTasks.has(t.id))}
                          onChange={(e) => {
                            if (e.target.checked) {
                              selectAllTasks();
                            } else {
                              clearSelection();
                            }
                          }}
                          className="w-4 h-4 rounded border-[var(--border)] text-[var(--accent)] focus:ring-2 focus:ring-[var(--accent)]"
                        />
                        <label className="text-sm text-[var(--text)] cursor-pointer">
                          Select all ({tasksData.data.length} tasks)
                        </label>
                      </div>
                    )}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                      {tasksData.data.map((task) => (
                      <div
                        key={task.id}
                        className={`p-4 border border-[var(--border)] rounded-lg hover:bg-[var(--muted-surface)] transition-colors cursor-pointer relative ${
                          selectedTasks.has(task.id) ? 'bg-[var(--accent)] bg-opacity-5 border-[var(--accent)]' : ''
                        }`}
                      >
                        {/* Checkbox for selection */}
                        <div 
                          className="absolute top-3 right-3"
                          onClick={(e) => {
                            e.stopPropagation();
                            toggleTaskSelection(task.id);
                          }}
                        >
                          <input
                            type="checkbox"
                            checked={selectedTasks.has(task.id)}
                            onChange={() => toggleTaskSelection(task.id)}
                            onClick={(e) => e.stopPropagation()}
                            className="w-4 h-4 rounded border-[var(--border)] text-[var(--accent)] focus:ring-2 focus:ring-[var(--accent)] cursor-pointer"
                          />
                        </div>
                        <div onClick={() => navigate(`/app/tasks/${task.id}`)}>
                          <div className="flex items-start justify-between mb-2 pr-6">
                            <h3 className="font-semibold text-[var(--text)] flex-1">{task.title}</h3>
                            <span className={`text-xs px-2 py-1 rounded ml-2 ${getStatusBadgeClass(task.status)}`}>
                              {task.status.replace('_', ' ')}
                            </span>
                          </div>
                          {task.description && (
                            <p className="text-sm text-[var(--muted)] mb-3 line-clamp-2">
                              {task.description}
                            </p>
                          )}
                          <div className="flex items-center gap-4 text-xs text-[var(--muted)]">
                            {task.due_date && (
                              <span>Due: {new Date(task.due_date).toLocaleDateString()}</span>
                            )}
                            {task.priority && (
                              <span className={`capitalize ${getPriorityBadgeClass(task.priority)}`}>
                                {task.priority}
                              </span>
                            )}
                          </div>
                        </div>
                      </div>
                    ))}
                    </div>
                  </div>
                )}

                {/* Kanban View */}
                {viewMode === 'kanban' && (
                  <>
                    {filters.status && (
                      <div className="mb-4 p-3 bg-[var(--muted-surface)] rounded-lg border border-[var(--border)]">
                        <div className="flex items-center justify-between">
                          <p className="text-sm text-[var(--text)]">
                            Showing only <span className="font-semibold capitalize">{filters.status.replace('_', ' ')}</span> tasks. 
                            <button
                              onClick={() => {
                                setFilters(prev => ({ ...prev, status: '' }));
                                setSearchParams(prev => {
                                  const newParams = new URLSearchParams(prev);
                                  newParams.delete('status');
                                  return newParams;
                                });
                              }}
                              className="ml-2 text-[var(--accent)] hover:underline"
                            >
                              Clear filter to see all columns
                            </button>
                          </p>
                        </div>
                      </div>
                    )}
                    <DragDropContext 
                      onDragStart={handleDragStart}
                      onDragEnd={handleDragEnd}
                    >
                      {(() => {
                        // If status filter is active, only show that column
                        // Otherwise show all columns
                        const statusesToShow = filters.status 
                          ? [filters.status] 
                          : ['pending', 'in_progress', 'completed', 'cancelled'];
                      const colsClass = statusesToShow.length === 1 
                        ? 'grid-cols-1' 
                        : statusesToShow.length === 2 
                        ? 'grid-cols-1 md:grid-cols-2' 
                        : statusesToShow.length === 3
                        ? 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3'
                        : 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4';
                      return (
                        <div className={`grid ${colsClass} gap-4`}>
                          {statusesToShow.map((status) => (
                        <Droppable key={status} droppableId={status}>
                          {(provided, snapshot) => (
                            <div
                              ref={provided.innerRef}
                              {...provided.droppableProps}
                              className={`relative bg-[var(--muted-surface)] rounded-lg p-3 transition-colors ${
                                snapshot.isDraggingOver ? 'bg-[var(--accent)] bg-opacity-10' : ''
                              }`}
                            >
                              <InvalidDropFeedback
                                columnId={status}
                                reason={invalidDropTarget.reason}
                                isActive={invalidDropTarget.columnId === status && snapshot.isDraggingOver}
                              />
                              <div className="flex items-center justify-between mb-3">
                                <h4 className="text-sm font-semibold text-[var(--text)] capitalize">
                                  {status.replace('_', ' ')}
                                </h4>
                                <span className="text-xs text-[var(--muted)] bg-[var(--surface)] px-2 py-1 rounded">
                                  {displayGroupedTasks[status]?.length || 0}
                                </span>
                              </div>
                              <div className="space-y-2 min-h-[100px]">
                                {(displayGroupedTasks[status] || []).map((task, index) => {
                                  // Use stable task ID helper - ensures always string and never null/undefined
                                  const stableTaskId = getStableTaskId(task);
                                  const isUpdating = draggingTaskId === stableTaskId;
                                  
                                  // Tasks without ID should already be filtered out in groupedTasks,
                                  // but double-check here for safety
                                  if (!task.id) {
                                    // This should not happen - task was filtered in groupedTasks
                                    if (process.env.NODE_ENV === 'development') {
                                      console.error('Task missing ID in Kanban render:', task);
                                    }
                                    return null;
                                  }
                                  
                                  return (
                                    <Draggable
                                      key={stableTaskId}
                                      draggableId={stableTaskId}
                                      index={index}
                                      isDragDisabled={isUpdating || updateTask.isPending}
                                    >
                                      {(provided, snapshot) => (
                                        <div
                                          ref={provided.innerRef}
                                          {...provided.draggableProps}
                                          data-task-id={stableTaskId}
                                          {...provided.dragHandleProps}
                                          style={{
                                            ...provided.draggableProps.style,
                                          }}
                                          className={`bg-[var(--surface)] p-3 rounded border border-[var(--border)] transition-all relative cursor-grab active:cursor-grabbing hover:shadow-sm ${
                                            snapshot.isDragging 
                                              ? 'shadow-lg rotate-1 z-50 scale-105' 
                                              : 'hover:shadow-md'
                                          } ${
                                            isUpdating || updateTask.isPending
                                              ? 'opacity-50 cursor-wait'
                                              : ''
                                          }`}
                                        >
                                          {isUpdating && (
                                            <div className="absolute top-2 right-2">
                                              <div className="animate-spin rounded-full h-4 w-4 border-2 border-[var(--accent)] border-t-transparent"></div>
                                            </div>
                                          )}
                                          
                                          {/* Task Status Tooltip - Passive Learning */}
                                          <TaskStatusTooltip 
                                            task={task} 
                                            showOnHover={true}
                                            hoverDelay={1500}
                                          />
                                          
                                          {/* Content area - use Link for navigation */}
                                          <div 
                                            className="cursor-pointer"
                                            onClick={(e) => {
                                              // Only navigate if not dragging
                                              if (!snapshot.isDragging) {
                                                navigate(`/app/tasks/${task.id}`);
                                              }
                                            }}
                                          >
                                            <div className="font-medium text-sm text-[var(--text)] mb-1">
                                              {task.title}
                                            </div>
                                          {task.description && (
                                            <div className="text-xs text-[var(--muted)] line-clamp-2 mb-2">
                                              {task.description}
                                            </div>
                                          )}
                                            <div className="flex items-center justify-between text-xs">
                                              {task.priority && (
                                                <span className={`capitalize ${getPriorityBadgeClass(task.priority)}`}>
                                                  {task.priority}
                                                </span>
                                              )}
                                              {task.due_date && (
                                                <span className="text-[var(--muted)]">
                                                  {new Date(task.due_date).toLocaleDateString()}
                                                </span>
                                              )}
                                            </div>
                                          </div>
                                        </div>
                                      )}
                                    </Draggable>
                                  );
                                })}
                                {provided.placeholder}
                                {(!displayGroupedTasks[status] || displayGroupedTasks[status].length === 0) && (
                                  <div className="text-center py-8">
                                    <div className="text-4xl mb-2 opacity-50">📋</div>
                                    <p className="text-sm text-[var(--muted)]">
                                      No {status.replace('_', ' ')} tasks
                                    </p>
                                  </div>
                                )}
                              </div>
                            </div>
                          )}
                        </Droppable>
                          ))}
                        </div>
                      );
                    })()}
                    </DragDropContext>
                  </>
                )}

                {/* Pagination */}
                {totalPages > 1 && (
                  <div className="flex items-center justify-between mt-6 pt-4 border-t border-[var(--border)]">
                    <div className="text-sm text-[var(--muted)]">
                      Showing page {currentPage} of {totalPages} ({total} total)
                    </div>
                    <div className="flex items-center gap-1">
                      <Button
                        variant="secondary"
                        size="sm"
                        disabled={currentPage <= 1}
                        onClick={() => handlePageChange(currentPage - 1)}
                        aria-label="Previous page"
                      >
                        Previous
                      </Button>
                      {getPageNumbers().map((pageNum, index) => {
                        if (pageNum === '...') {
                          return (
                            <span
                              key={`ellipsis-${index}`}
                              className="px-2 text-[var(--muted)]"
                            >
                              ...
                            </span>
                          );
                        }
                        const page = pageNum as number;
                        const isActive = page === currentPage;
                        return (
                          <Button
                            key={page}
                            variant={isActive ? 'primary' : 'secondary'}
                            size="sm"
                            onClick={() => handlePageChange(page)}
                            aria-label={`Go to page ${page}`}
                            aria-current={isActive ? 'page' : undefined}
                            className={isActive ? 'min-w-[40px]' : 'min-w-[40px]'}
                          >
                            {page}
                          </Button>
                        );
                      })}
                      <Button
                        variant="secondary"
                        size="sm"
                        disabled={currentPage >= totalPages}
                        onClick={() => handlePageChange(currentPage + 1)}
                        aria-label="Next page"
                      >
                        Next
                      </Button>
                    </div>
                  </div>
                )}
              </>
            ) : (
              <EmptyState
                icon="📝"
                title={hasActiveFilters ? 'No tasks match your filters' : 'No tasks yet'}
                description={
                  hasActiveFilters
                    ? 'Try adjusting your filters to see more tasks.'
                    : 'Get started by creating your first task.'
                }
                actionText={hasActiveFilters ? 'Clear filters' : 'Create task'}
                onAction={() => {
                  if (hasActiveFilters) {
                    setFilters({});
                    setSearchParams({});
                  } else {
                    navigate('/app/tasks/create');
                  }
                }}
                secondaryActionText={hasActiveFilters ? undefined : undefined}
              />
            )}
          </CardContent>
        </Card>

        {/* Activity Feed */}
        <ActivityFeed
          activities={activities}
          loading={activityLoading}
          error={activityError as Error | null}
          title="Recent Activity"
          limit={10}
        />

        {/* Reason Modal */}
        <TaskMoveReasonModal
          isOpen={reasonModal.isOpen}
          taskTitle={reasonModal.taskTitle}
          targetStatus={reasonModal.targetStatus}
          onConfirm={handleReasonConfirm}
          onCancel={handleReasonCancel}
        />
        {/* Error Modal */}
        {errorModal.task && (
          <TaskMoveErrorModal
            isOpen={errorModal.isOpen}
            error={errorModal.error}
            task={errorModal.task}
            targetStatus={errorModal.targetStatus}
            onClose={() => setErrorModal({ isOpen: false, error: { code: '', message: '' }, task: null, targetStatus: '' })}
            onAction={handleErrorModalAction}
          />
        )}
      </div>
    </Container>
  );
};

export default TasksListPage;
