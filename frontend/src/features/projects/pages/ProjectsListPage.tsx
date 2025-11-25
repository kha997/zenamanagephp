import React, { useState, useMemo, useCallback, useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { DragDropContext, Droppable, Draggable, DropResult } from '@hello-pangea/dnd';
import toast from 'react-hot-toast';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { KpiStrip } from '../../../components/shared/KpiStrip';
import { AlertBar } from '../../../components/shared/AlertBar';
import { ActivityFeed } from '../../../components/shared/ActivityFeed';
import { SmartFilters, type FilterPreset } from '../../../components/shared/SmartFilters';
import { Button } from '../../../components/ui/primitives/Button';
import { Input } from '../../../components/ui/primitives/Input';
import { Badge } from '../../../shared/ui/badge';
import { useTheme } from '../../../shared/theme/ThemeProvider';
import { useAuthStore } from '../../../features/auth/store';
import { useProjects, useProjectsKpis, useProjectsActivity, useProjectsAlerts, useUpdateProject } from '../hooks';
import { useProjectHealthPortfolio } from '../../../features/reports/hooks';
import { getOverallStatusLabel, getOverallStatusTone } from '../healthStatus';
import type { ProjectOverviewHealth } from '../api';
import type { KpiItem } from '../../../components/shared/KpiStrip';
import type { Alert } from '../../../components/shared/AlertBar';
import type { Activity } from '../../../components/shared/ActivityFeed';
import type { ProjectFilters } from '../types';
import type { Project } from '../types';

type ViewMode = 'table' | 'card' | 'kanban';
type OverallHealthFilter = 'all' | 'good' | 'warning' | 'critical';

export const ProjectsListPage: React.FC = () => {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const { theme } = useTheme();
  const { hasTenantPermission } = useAuthStore();
  
  // Permission check for health features
  const canViewReports = hasTenantPermission('tenant.view_reports');
  
  // View mode state - persist to localStorage
  const [viewMode, setViewMode] = useState<ViewMode>(() => {
    const saved = localStorage.getItem('projects-view-mode');
    return (saved === 'table' || saved === 'card' || saved === 'kanban') ? saved : 'card';
  });
  
  // Persist view mode to localStorage
  useEffect(() => {
    localStorage.setItem('projects-view-mode', viewMode);
  }, [viewMode]);
  
  // Filters state
  const [filters, setFilters] = useState<ProjectFilters>({
    search: searchParams.get('search') || '',
    status: searchParams.get('status') || '',
    priority: searchParams.get('priority') || '',
  });
  
  // Health filter state (client-side only)
  const [overallHealthFilter, setOverallHealthFilter] = useState<OverallHealthFilter>('all');
  
  // Search input state (for debouncing)
  const [searchInput, setSearchInput] = useState(filters.search || '');
  
  // Pagination state
  const [page, setPage] = useState(parseInt(searchParams.get('page') || '1', 10));
  const [perPage] = useState(12);
  
  // Sync filters state with URL params when they change
  useEffect(() => {
    const newFilters: ProjectFilters = {
      search: searchParams.get('search') || '',
      status: searchParams.get('status') || '',
      priority: searchParams.get('priority') || '',
    };
    setFilters(newFilters);
    setSearchInput(newFilters.search || '');
    setPage(parseInt(searchParams.get('page') || '1', 10));
  }, [searchParams]);
  
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
  const { data: projectsData, isLoading: projectsLoading, error: projectsError, refetch: refetchProjects } = useProjects(
    filters,
    { page, per_page: perPage }
  );
  
  // Fetch health data (only if user has permission)
  const {
    data: healthItems,
    isLoading: isHealthLoading,
    isError: isHealthError,
    error: healthError,
  } = useProjectHealthPortfolio({ enabled: canViewReports });
  
  // Build map from projectId → health for O(1) lookup
  const healthByProjectId = useMemo(() => {
    const map = new Map<string | number, ProjectOverviewHealth>();
    (healthItems ?? []).forEach((item) => {
      if (item?.project?.id && item.health) {
        map.set(item.project.id, item.health);
      }
    });
    return map;
  }, [healthItems]);
  
  // KPI period state
  const [kpiPeriod, setKpiPeriod] = useState<'week' | 'month'>('week');
  const { data: kpisData, isLoading: kpisLoading } = useProjectsKpis(kpiPeriod);
  const { data: activityData, isLoading: activityLoading, error: activityError } = useProjectsActivity(10);
  const { data: alertsData, isLoading: alertsLoading, error: alertsError } = useProjectsAlerts();
  const updateProject = useUpdateProject();
  
  // Track dismissed alerts locally (since these are temporary alerts)
  const [dismissedAlerts, setDismissedAlerts] = useState<Set<string | number>>(new Set());
  
  // Filter out dismissed alerts
  const activeAlerts = useMemo(() => {
    if (!alertsData?.data) return [];
    return alertsData.data.filter(alert => !dismissedAlerts.has(alert.id));
  }, [alertsData, dismissedAlerts]);
  
  // Handle dismiss single alert
  const handleAlertClick = useCallback((alert: Alert) => {
    if (alert.metadata?.project_id) {
      navigate(`/app/projects/${alert.metadata.project_id}`);
    }
  }, [navigate]);

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
    if (!kpisData?.data) return [];
    
    const kpis = kpisData.data;
    const trends = kpis.trends || {};
    const period = kpis.period || kpiPeriod;
    
    // Helper để format trend với percentage
    const formatTrend = (trend?: { value?: number; direction?: string }) => {
      if (!trend || trend.value === undefined || trend.value === 0) return undefined;
      const sign = trend.direction === 'up' ? '+' : trend.direction === 'down' ? '-' : '';
      return `${sign}${trend.value}%`;
    };
    
    // Helper để determine variant based on value và trend
    const getVariantForOverdue = (value: number, trend?: { direction?: string }) => {
      if (value > 0) return 'danger';
      return 'default';
    };
    
    const getVariantForActive = (trend?: { direction?: string }) => {
      if (trend?.direction === 'down') return 'warning';
      return 'success';
    };
    
    return [
      {
        label: 'Total Projects',
        value: kpis.total_projects || kpis.total || 0,
        variant: 'default',
        change: formatTrend(trends.total_projects),
        trend: trends.total_projects?.direction || 'neutral',
        periodLabel: `vs previous ${period}`,
        onClick: () => {
          navigate('/app/projects');
        },
        actionLabel: 'View all',
      },
      {
        label: 'Active Projects',
        value: kpis.active_projects || kpis.active || 0,
        variant: getVariantForActive(trends.active_projects),
        change: formatTrend(trends.active_projects),
        trend: trends.active_projects?.direction || 'neutral',
        periodLabel: `vs previous ${period}`,
        onClick: () => {
          navigate('/app/projects?status=active');
        },
        actionLabel: 'View active',
      },
      {
        label: 'Completed Projects',
        value: kpis.completed_projects || kpis.completed || 0,
        variant: 'info',
        change: formatTrend(trends.completed_projects),
        trend: trends.completed_projects?.direction || 'neutral',
        periodLabel: `vs previous ${period}`,
        onClick: () => {
          navigate('/app/projects?status=completed');
        },
        actionLabel: 'View completed',
      },
      {
        label: 'Overdue Projects',
        value: kpis.overdue_projects || kpis.overdue || 0,
        variant: getVariantForOverdue(
          kpis.overdue_projects || kpis.overdue || 0,
          trends.overdue_projects
        ),
        change: formatTrend(trends.overdue_projects),
        trend: trends.overdue_projects?.direction || 'neutral',
        periodLabel: `vs previous ${period}`,
        onClick: () => {
          navigate('/app/projects?status=overdue');
        },
        actionLabel: (kpis.overdue_projects || kpis.overdue || 0) > 0 ? 'View overdue' : undefined,
      },
    ];
  }, [kpisData, kpiPeriod, navigate]);

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
      metadata: alert.metadata, // Preserve metadata for navigation
    }));
  }, [activeAlerts]);

  // Transform activity data to Activity format
  const activities: Activity[] = useMemo(() => {
    if (!activityData?.data) return [];
    return Array.isArray(activityData.data)
      ? activityData.data.map((activity: any) => ({
          id: activity.id,
          type: activity.type || 'project',
          action: activity.action,
          description: activity.description || activity.message || 'Activity',
          timestamp: activity.timestamp || activity.created_at || activity.createdAt,
          user: activity.user,
          metadata: activity.metadata,
        }))
      : [];
  }, [activityData]);

  // Helper function to check if a project is overdue
  const isProjectOverdue = useCallback((project: Project): boolean => {
    if (!project.end_date) return false;
    const endDate = new Date(project.end_date);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    endDate.setHours(0, 0, 0, 0);
    // Project is overdue if end_date < today and status is active or on_hold
    return endDate < today && (project.status === 'active' || project.status === 'on_hold');
  }, []);

  // Filter presets
  const filterPresets: FilterPreset[] = useMemo(() => [
    {
      id: 'active',
      name: 'Active',
      filters: { status: 'active' },
      icon: '▶️',
    },
    {
      id: 'completed',
      name: 'Completed',
      filters: { status: 'completed' },
      icon: '✅',
    },
    {
      id: 'on_hold',
      name: 'On Hold',
      filters: { status: 'on_hold' },
      icon: '⏸️',
    },
  ], []);

  // Filter options
  const filterOptions = useMemo(() => ({
    status: [
      { id: 'planning', label: 'Planning', value: 'planning' },
      { id: 'active', label: 'Active', value: 'active' },
      { id: 'on_hold', label: 'On Hold', value: 'on_hold' },
      { id: 'completed', label: 'Completed', value: 'completed' },
      { id: 'cancelled', label: 'Cancelled', value: 'cancelled' },
    ],
    priority: [
      { id: 'low', label: 'Low', value: 'low' },
      { id: 'medium', label: 'Medium', value: 'medium' },
      { id: 'normal', label: 'Normal', value: 'normal' },
      { id: 'high', label: 'High', value: 'high' },
      { id: 'urgent', label: 'Urgent', value: 'urgent' },
    ],
  }), []);

  const handleFilterChange = useCallback((newFilters: ProjectFilters) => {
    setFilters(newFilters);
    setSearchParams(prev => {
      const newParams = new URLSearchParams(prev);
      Object.entries(newFilters).forEach(([key, value]) => {
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


  // Pagination helpers
  const paginationMeta = projectsData?.meta;
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

  // Apply health filter to projects (client-side)
  const filteredProjects = useMemo(() => {
    if (!projectsData?.data) return [];
    
    let projects = projectsData.data;
    
    // Apply health filter if user has permission and filter is not 'all'
    if (canViewReports && overallHealthFilter !== 'all') {
      projects = projects.filter((project) => {
        const health = healthByProjectId.get(project.id);
        if (!health) return false; // If no health data, it doesn't match any specific status
        return health.overall_status === overallHealthFilter;
      });
    }
    
    return projects;
  }, [projectsData?.data, canViewReports, overallHealthFilter, healthByProjectId]);
  
  // Group projects by status for kanban view (using filtered projects)
  const groupedProjects = useMemo(() => {
    if (!filteredProjects || filteredProjects.length === 0) return {};
    const grouped: Record<string, typeof filteredProjects> = {
      planning: [],
      active: [],
      on_hold: [],
      completed: [],
      cancelled: [],
    };
    filteredProjects.forEach(project => {
      const status = project.status || 'planning';
      if (grouped[status]) {
        grouped[status].push(project);
      }
    });
    
    // Sort each group by order (ascending) for consistent Kanban ordering
    Object.keys(grouped).forEach(status => {
      grouped[status].sort((a, b) => {
        const orderA = a.order ?? 0;
        const orderB = b.order ?? 0;
        return orderA - orderB;
      });
    });
    
    return grouped;
  }, [filteredProjects]);

  // Get status badge classes based on theme
  const getStatusBadgeClass = useCallback((status: string) => {
    const isDark = theme === 'dark';
    switch (status) {
      case 'active':
        return isDark 
          ? 'bg-green-900 text-green-300' 
          : 'bg-green-100 text-green-700';
      case 'completed':
        return isDark 
          ? 'bg-gray-700 text-gray-300' 
          : 'bg-gray-100 text-gray-700';
      case 'on_hold':
        return isDark 
          ? 'bg-yellow-900 text-yellow-300' 
          : 'bg-yellow-100 text-yellow-700';
      case 'planning':
        return isDark 
          ? 'bg-blue-900 text-blue-300' 
          : 'bg-blue-100 text-blue-700';
      case 'cancelled':
        return isDark 
          ? 'bg-red-900 text-red-300' 
          : 'bg-red-100 text-red-700';
      default:
        return isDark 
          ? 'bg-gray-700 text-gray-300' 
          : 'bg-gray-100 text-gray-500';
    }
  }, [theme]);

  // Handle drag and drop for Kanban view
  const handleDragEnd = useCallback(async (result: DropResult) => {
    const { destination, source, draggableId } = result;

    // If dropped outside a droppable area, do nothing
    if (!destination) {
      return;
    }

    // If dropped in the same position, do nothing
    if (
      destination.droppableId === source.droppableId &&
      destination.index === source.index
    ) {
      return;
    }

    // Get the new status from destination droppableId
    const newStatus = destination.droppableId as Project['status'];
    const projectId = draggableId;

    // Find the project being moved
    const project = projectsData?.data?.find(p => String(p.id) === projectId);
    if (!project) {
      return;
    }

    const projectName = project.name || 'Project';
    const isSameStatus = project.status === newStatus;
    const newOrder = destination.index;

    try {
      if (isSameStatus) {
        // Moving within the same column - update order only
        await updateProject.mutateAsync({
          id: projectId,
          data: { order: newOrder },
        });

        toast.success(`${projectName} order updated`, {
          duration: 2000,
        });
      } else {
        // Moving to different column - update status and order
        await updateProject.mutateAsync({
          id: projectId,
          data: { 
            status: newStatus,
            order: newOrder,
          },
        });

        // Show success toast
        const statusLabel = newStatus.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
        toast.success(`${projectName} moved to ${statusLabel}`, {
          duration: 3000,
        });
      }

      // Refetch projects to ensure consistency
      refetchProjects();
    } catch (error: any) {
      console.error('Failed to update project:', error);
      
      // Show error toast
      const errorMessage = error?.response?.data?.message 
        || error?.message 
        || `Failed to update project. Please try again.`;
      toast.error(errorMessage, {
        duration: 4000,
      });
      
      // Refetch to restore original state
      refetchProjects();
    }
  }, [projectsData, updateProject, refetchProjects]);

  return (
    <Container>
      <div className="space-y-6">
        {/* Page Header */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 className="text-[var(--font-heading-3-size)] font-semibold text-[var(--text)]">Projects</h1>
            <p className="text-[var(--font-body-size)] text-[var(--muted)] mt-1">
              Manage and track all your projects
            </p>
          </div>
          <div className="flex items-center gap-3">
            {/* View Mode Toggle */}
            <div className="flex items-center gap-1 border border-[var(--border)] rounded-lg p-1">
              <button
                type="button"
                onClick={(e) => {
                  e.preventDefault();
                  setViewMode('table');
                }}
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
                type="button"
                onClick={(e) => {
                  e.preventDefault();
                  setViewMode('card');
                }}
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
                type="button"
                onClick={(e) => {
                  e.preventDefault();
                  setViewMode('kanban');
                }}
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
            {/* New Project Button - Only show if user has tenant.manage_projects permission */}
            {hasTenantPermission('tenant.manage_projects') && (
              <Button onClick={() => navigate('/app/projects/create')}>
                New Project
              </Button>
            )}
          </div>
        </div>

        {/* KPI Strip */}
        <KpiStrip
          kpis={kpiItems}
          loading={kpisLoading}
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
          onAlertClick={handleAlertClick}
        />

        {/* Search and Filters */}
        <Card>
          <CardContent className="p-3 md:p-4">
            <div className="flex flex-col gap-3">
              <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3">
                {/* Search Input - Compact */}
                <div className="flex-1 min-w-0 sm:max-w-xs">
                  <Input
                    type="search"
                    placeholder="Search projects..."
                    value={searchInput}
                    onChange={(e) => setSearchInput(e.target.value)}
                    leadingIcon={<span>🔍</span>}
                    className="w-full"
                  />
                </div>

                {/* Smart Filters - Inline with search */}
                <div className="flex-1 min-w-0">
                  <SmartFilters
                    filters={filterOptions}
                    values={filters}
                    onChange={handleFilterChange}
                    presets={filterPresets}
                  />
                </div>
              </div>
              
              {/* Health Filter - Only show if user has permission */}
              {canViewReports && (
                <div className="flex flex-wrap items-center gap-2" data-testid="health-filter">
                  <button
                    type="button"
                    onClick={() => setOverallHealthFilter('all')}
                    data-testid="health-filter-all"
                    className={`px-3 py-1.5 text-sm rounded transition-colors ${
                      overallHealthFilter === 'all'
                        ? 'bg-[var(--primary-button-bg)] text-[var(--primary-button-text)]'
                        : 'bg-[var(--muted-surface)] text-[var(--muted)] hover:bg-[var(--border)]'
                    }`}
                  >
                    Tất cả
                  </button>
                  <button
                    type="button"
                    onClick={() => setOverallHealthFilter('good')}
                    data-testid="health-filter-good"
                    className={`px-3 py-1.5 text-sm rounded transition-colors ${
                      overallHealthFilter === 'good'
                        ? 'bg-[var(--primary-button-bg)] text-[var(--primary-button-text)]'
                        : 'bg-[var(--muted-surface)] text-[var(--muted)] hover:bg-[var(--border)]'
                    }`}
                  >
                    Tốt
                  </button>
                  <button
                    type="button"
                    onClick={() => setOverallHealthFilter('warning')}
                    data-testid="health-filter-warning"
                    className={`px-3 py-1.5 text-sm rounded transition-colors ${
                      overallHealthFilter === 'warning'
                        ? 'bg-[var(--primary-button-bg)] text-[var(--primary-button-text)]'
                        : 'bg-[var(--muted-surface)] text-[var(--muted)] hover:bg-[var(--border)]'
                    }`}
                  >
                    Cảnh báo
                  </button>
                  <button
                    type="button"
                    onClick={() => setOverallHealthFilter('critical')}
                    data-testid="health-filter-critical"
                    className={`px-3 py-1.5 text-sm rounded transition-colors ${
                      overallHealthFilter === 'critical'
                        ? 'bg-[var(--primary-button-bg)] text-[var(--primary-button-text)]'
                        : 'bg-[var(--muted-surface)] text-[var(--muted)] hover:bg-[var(--border)]'
                    }`}
                  >
                    Nguy cấp
                  </button>
                </div>
              )}
              
              {/* Health error hint */}
              {canViewReports && isHealthError && (
                <p className="text-xs text-[var(--muted)] mt-1" data-testid="health-error-hint">
                  Không tải được dữ liệu health của dự án.
                </p>
              )}
            </div>
          </CardContent>
        </Card>

        {/* Main Content */}
        <Card>
          <CardHeader>
            <CardTitle>
              All Projects
              {filteredProjects.length > 0 && (
                <span className="ml-2 text-sm font-normal text-[var(--muted)]">
                  ({filteredProjects.length} {filteredProjects.length === 1 ? 'project' : 'projects'})
                  {canViewReports && overallHealthFilter !== 'all' && projectsData?.data && filteredProjects.length !== projectsData.data.length && (
                    <span className="ml-1">
                      {' '}(filtered from {projectsData.data.length} total)
                    </span>
                  )}
                </span>
              )}
            </CardTitle>
          </CardHeader>
          <CardContent>
            {projectsLoading ? (
              <div className="space-y-4">
                {[1, 2, 3].map((i) => (
                  <div key={i} className="animate-pulse">
                    <div className="h-4 bg-[var(--muted-surface)] rounded w-3/4 mb-2"></div>
                    <div className="h-3 bg-[var(--muted-surface)] rounded w-1/2"></div>
                  </div>
                ))}
              </div>
            ) : projectsError ? (
              <div className="text-center text-[var(--muted)] py-8">
                <p className="text-sm">Error loading projects: {(projectsError as Error).message}</p>
                <Button
                  variant="secondary"
                  onClick={() => window.location.reload()}
                  className="mt-4"
                >
                  Retry
                </Button>
              </div>
            ) : filteredProjects && filteredProjects.length > 0 ? (
              <>
                {/* Table View */}
                {viewMode === 'table' && filteredProjects.length > 0 && (
                  <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                      <thead>
                        <tr className="border-b border-[var(--border)]">
                          <th className="text-left py-3 px-4 font-semibold text-[var(--text)]">Project</th>
                          <th className="text-left py-3 px-4 font-semibold text-[var(--text)]">Status</th>
                          {canViewReports && <th className="text-left py-3 px-4 font-semibold text-[var(--text)]">Health</th>}
                          <th className="text-left py-3 px-4 font-semibold text-[var(--text)]">Priority</th>
                          <th className="text-left py-3 px-4 font-semibold text-[var(--text)]">Start Date</th>
                          <th className="text-left py-3 px-4 font-semibold text-[var(--text)]">End Date</th>
                          <th className="text-right py-3 px-4 font-semibold text-[var(--text)]">Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        {filteredProjects.map((project) => {
                          const health = canViewReports ? healthByProjectId.get(project.id) : null;
                          const healthLabel = health ? getOverallStatusLabel(health.overall_status) : null;
                          const healthTone = health ? getOverallStatusTone(health.overall_status) : 'neutral';
                          
                          return (
                          <tr
                            key={project.id}
                            className={`border-b hover:bg-[var(--muted-surface)] transition-colors cursor-pointer ${
                              isProjectOverdue(project) 
                                ? 'border-red-300 dark:border-red-800/50' 
                                : 'border-[var(--border)]'
                            }`}
                            onClick={() => navigate(`/app/projects/${project.id}`)}
                          >
                            <td className="py-3 px-4">
                              <div className={`font-semibold ${isProjectOverdue(project) ? 'text-[var(--text)] dark:text-red-500' : 'text-[var(--text)]'}`}>
                                {project.name}
                              </div>
                              {project.description && (
                                <div className={`text-xs mt-1 line-clamp-1 ${isProjectOverdue(project) ? 'text-[var(--muted)] dark:text-red-600/80' : 'text-[var(--muted)]'}`}>
                                  {project.description}
                                </div>
                              )}
                            </td>
                            <td className="py-3 px-4">
                              <div className="flex items-center gap-2">
                                <span className={`text-xs px-2 py-1 rounded ${getStatusBadgeClass(project.status || 'planning')}`}>
                                  {project.status}
                                </span>
                                {isProjectOverdue(project) && (
                                  <span className="text-xs px-2 py-1 rounded bg-red-100 text-red-800 dark:bg-transparent dark:text-red-500 dark:border dark:border-red-800/50 font-semibold">
                                    Overdue
                                  </span>
                                )}
                              </div>
                            </td>
                            {canViewReports && (
                              <td className="py-3 px-4">
                                {health ? (
                                  <Badge
                                    tone={healthTone}
                                    data-testid={`project-health-${project.id}`}
                                  >
                                    {healthLabel}
                                  </Badge>
                                ) : (
                                  <span data-testid={`project-health-empty-${project.id}`}>—</span>
                                )}
                              </td>
                            )}
                            <td className="py-3 px-4 text-[var(--muted)]">
                              {project.priority || '—'}
                            </td>
                            <td className="py-3 px-4 text-[var(--muted)]">
                              {project.start_date ? new Date(project.start_date).toLocaleDateString() : '—'}
                            </td>
                            <td className="py-3 px-4 text-[var(--muted)]">
                              {project.end_date ? new Date(project.end_date).toLocaleDateString() : '—'}
                            </td>
                            <td className="py-3 px-4 text-right">
                              <Button
                                variant="tertiary"
                                size="sm"
                                onClick={(e) => {
                                  e.stopPropagation();
                                  navigate(`/app/projects/${project.id}`);
                                }}
                              >
                                View
                              </Button>
                            </td>
                          </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>
                )}

                {/* Card View */}
                {viewMode === 'card' && filteredProjects.length > 0 && (
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    {filteredProjects.map((project) => {
                      const health = canViewReports ? healthByProjectId.get(project.id) : null;
                      const healthLabel = health ? getOverallStatusLabel(health.overall_status) : null;
                      const healthTone = health ? getOverallStatusTone(health.overall_status) : 'neutral';
                      
                      return (
                      <div
                        key={project.id}
                        className={`p-4 border rounded-lg hover:bg-[var(--muted-surface)] transition-colors cursor-pointer ${
                          isProjectOverdue(project) 
                            ? 'border-red-300 dark:border-red-800/50' 
                            : 'border-[var(--border)]'
                        }`}
                        onClick={() => navigate(`/app/projects/${project.id}`)}
                      >
                        <div className="flex items-start justify-between mb-2">
                          <h3 className={`font-semibold flex-1 ${isProjectOverdue(project) ? 'text-[var(--text)] dark:text-red-500' : 'text-[var(--text)]'}`}>
                            {project.name}
                          </h3>
                          <div className="flex items-center gap-2">
                            <span className={`text-xs px-2 py-1 rounded ${getStatusBadgeClass(project.status || 'planning')}`}>
                              {project.status}
                            </span>
                            {isProjectOverdue(project) && (
                              <span className="text-xs px-2 py-1 rounded bg-red-100 text-red-800 dark:bg-transparent dark:text-red-500 dark:border dark:border-red-800/50 font-semibold">
                                Overdue
                              </span>
                            )}
                            {canViewReports && health && (
                              <Badge
                                tone={healthTone}
                                data-testid={`project-health-${project.id}`}
                              >
                                {healthLabel}
                              </Badge>
                            )}
                          </div>
                        </div>
                        {project.description && (
                          <p className={`text-sm mb-3 line-clamp-2 ${isProjectOverdue(project) ? 'text-[var(--muted)] dark:text-red-600/80' : 'text-[var(--muted)]'}`}>
                            {project.description}
                          </p>
                        )}
                        <div className="flex items-center gap-4 text-xs text-[var(--muted)]">
                          {project.start_date && (
                            <span>Start: {new Date(project.start_date).toLocaleDateString()}</span>
                          )}
                          {project.end_date && (
                            <span>End: {new Date(project.end_date).toLocaleDateString()}</span>
                          )}
                        </div>
                      </div>
                      );
                    })}
                  </div>
                )}

                {/* Kanban View */}
                {viewMode === 'kanban' && (
                  <DragDropContext 
                    onDragEnd={handleDragEnd}
                    isDragDisabled={updateProject.isPending}
                  >
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                      {['planning', 'active', 'on_hold', 'completed', 'cancelled'].map((status) => {
                        const projectsInStatus = groupedProjects[status] || [];
                        return (
                          <Droppable key={status} droppableId={status}>
                            {(provided, snapshot) => (
                              <div
                                ref={provided.innerRef}
                                {...provided.droppableProps}
                                className={`bg-[var(--muted-surface)] rounded-lg p-3 min-h-[200px] transition-all duration-200 ${
                                  snapshot.isDraggingOver 
                                    ? 'bg-[var(--accent)] bg-opacity-20 border-2 border-[var(--accent)] border-dashed' 
                                    : 'border-2 border-transparent'
                                } ${updateProject.isPending ? 'opacity-50 pointer-events-none' : ''}`}
                              >
                                <div className="flex items-center justify-between mb-3">
                                  <h4 className="text-sm font-semibold text-[var(--text)] capitalize">
                                    {status.replace('_', ' ')}
                                  </h4>
                                  <span className="text-xs text-[var(--muted)] bg-[var(--surface)] px-2 py-1 rounded">
                                    {projectsInStatus.length}
                                  </span>
                                </div>
                                <div className="space-y-2">
                                  {projectsInStatus.length > 0 ? (
                                    projectsInStatus.map((project, index) => (
                                      <Draggable
                                        key={String(project.id)}
                                        draggableId={String(project.id)}
                                        index={index}
                                      >
                                        {(provided, snapshot) => (
                                          <div
                                            ref={provided.innerRef}
                                            {...provided.draggableProps}
                                            {...provided.dragHandleProps}
                                            className={`bg-[var(--surface)] p-3 rounded border border-[var(--border)] cursor-grab active:cursor-grabbing hover:shadow-sm transition-all duration-200 ${
                                              snapshot.isDragging 
                                                ? 'shadow-xl rotate-2 scale-105 z-50' 
                                                : 'hover:shadow-md'
                                            } ${updateProject.isPending ? 'opacity-60' : ''}`}
                                            onClick={(e) => {
                                              // Only navigate if not dragging
                                              if (!snapshot.isDragging) {
                                                navigate(`/app/projects/${project.id}`);
                                              }
                                            }}
                                          >
                                            <div className="font-medium text-sm text-[var(--text)] mb-1">
                                              {project.name}
                                            </div>
                                            {project.description && (
                                              <div className="text-xs text-[var(--muted)] line-clamp-2">
                                                {project.description}
                                              </div>
                                            )}
                                          </div>
                                        )}
                                      </Draggable>
                                    ))
                                  ) : (
                                    <div className="text-xs text-[var(--muted)] text-center py-4">
                                      No projects
                                    </div>
                                  )}
                                  {provided.placeholder}
                                </div>
                              </div>
                            )}
                          </Droppable>
                        );
                      })}
                    </div>
                  </DragDropContext>
                )}

                {/* Pagination */}
                {totalPages > 1 && (
                  <div className="flex items-center justify-between mt-6 pt-4 border-t border-[var(--border)]">
                    <div className="text-sm text-[var(--muted)]">
                      Showing page {currentPage} of {totalPages}
                    </div>
                    <div className="flex items-center gap-2">
                      <Button
                        variant="secondary"
                        size="sm"
                        disabled={currentPage <= 1}
                        onClick={() => handlePageChange(currentPage - 1)}
                      >
                        Previous
                      </Button>
                      <Button
                        variant="secondary"
                        size="sm"
                        disabled={currentPage >= totalPages}
                        onClick={() => handlePageChange(currentPage + 1)}
                      >
                        Next
                      </Button>
                    </div>
                  </div>
                )}
              </>
            ) : (
              <div className="text-center text-[var(--muted)] py-12">
                <p className="text-sm mb-2">No projects found</p>
                <Button
                  variant="secondary"
                  onClick={() => navigate('/app/projects/create')}
                >
                  Create your first project
                </Button>
              </div>
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
      </div>
    </Container>
  );
};

export default ProjectsListPage;
