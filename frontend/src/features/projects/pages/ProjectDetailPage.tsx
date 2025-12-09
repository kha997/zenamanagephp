import React, { useState, useCallback, useRef, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { Input } from '../../../components/ui/primitives/Input';
import { ActivityFeed } from '../../../components/shared/ActivityFeed';
import { KpiStrip, type KpiItem } from '../../../components/shared/KpiStrip';
import { AlertBar } from '../../../components/shared/AlertBar';
import { useAuthStore } from '../../../features/auth/store';
import { useProject, useDeleteProject, useProjectsActivity, useProjectTasks, useProjectDocuments, useArchiveProject, useAddTeamMember, useRemoveTeamMember, useUploadProjectDocument, useProjectKpis, useProjectAlerts, useProjectOverview } from '../hooks';
import { ProjectCostHealthHeader } from '../components/ProjectCostHealthHeader';
import { ProjectCostFlowStatusHeader } from '../components/ProjectCostFlowStatusHeader';
import { ProjectCostAlertsIcon } from '../components/ProjectCostAlertsIcon';
import { ProjectDocumentsSection } from '../components/ProjectDocumentsSection';
import { ProjectHistorySection } from '../components/ProjectHistorySection';
import { ApplyTemplateToProjectModal } from '../components/ApplyTemplateToProjectModal';
import { ProjectHealthHistoryCard } from '../components/ProjectHealthHistoryCard';
import { ProjectTaskList } from '../components/ProjectTaskList';
import { ProjectCostDashboardSection } from '../components/ProjectCostDashboardSection';
import { useDeleteTask, useUpdateTask } from '../../tasks/hooks';
import { useUsers } from '../../users/hooks';
import { MoneyCell } from '../../reports/components/MoneyCell';
import { getOverallStatusLabel, getScheduleStatusLabel, getCostStatusLabel } from '../healthStatus';
import type { Activity } from '../../../components/shared/ActivityFeed';
import type { Task } from '../../tasks/types';
import { ToastProvider } from '../../../shared/ui/toast'; // Round 162: Add ToastProvider for ApplyTemplateToProjectModal

type TabId = 'overview' | 'tasks' | 'documents' | 'team' | 'activity' | 'cost';

interface Tab {
  id: TabId;
  label: string;
  icon?: string;
}

const tabs: Tab[] = [
  { id: 'overview', label: 'Overview', icon: '📊' },
  { id: 'tasks', label: 'Tasks', icon: '✅' },
  { id: 'documents', label: 'Documents', icon: '📄' },
  { id: 'team', label: 'Team', icon: '👥' },
  { id: 'cost', label: 'Cost', icon: '💰' },
  { id: 'activity', label: 'Activity', icon: '📝' },
];

/**
 * Key Task Item with Quick Actions
 * 
 * Round 71: Quick actions for key_tasks in Project Overview
 */
interface KeyTaskItemProps {
  task: {
    id: string | number;
    name: string;
    status: string;
    priority?: string;
    end_date?: string | null;
    assignee?: { id: string; name: string } | null;
  };
  priorityColorClass: string;
  onNavigate: (taskId: string | number) => void;
  canManageTasks: boolean;
  currentUserId?: string | number;
  projectId?: string | number;
}

const KeyTaskItem: React.FC<KeyTaskItemProps> = ({
  task,
  priorityColorClass,
  onNavigate,
  canManageTasks,
  currentUserId,
  projectId,
}) => {
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);
  const queryClient = useQueryClient();
  const updateTaskMutation = useUpdateTask();

  // Close menu when clicking outside
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(event.target as Node)) {
        setIsMenuOpen(false);
      }
    };

    if (isMenuOpen) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => document.removeEventListener('mousedown', handleClickOutside);
    }
  }, [isMenuOpen]);

  const handleRowClick = (e: React.MouseEvent) => {
    // Don't navigate if clicking on the menu button or menu
    if ((e.target as HTMLElement).closest('.task-menu-button') || 
        (e.target as HTMLElement).closest('.task-menu')) {
      return;
    }
    onNavigate(task.id);
  };

  const handleMenuToggle = (e: React.MouseEvent) => {
    e.stopPropagation();
    setIsMenuOpen(!isMenuOpen);
  };

  const handleAction = async (action: 'in_progress' | 'done' | 'assign_to_me') => {
    setIsMenuOpen(false);
    
    let updateData: Partial<Task> = {};
    
    if (action === 'in_progress') {
      updateData = { status: 'in_progress' };
    } else if (action === 'done') {
      updateData = { status: 'done' };
    } else if (action === 'assign_to_me' && currentUserId) {
      updateData = { assignee_id: currentUserId };
    }

    try {
      await updateTaskMutation.mutateAsync({
        id: task.id,
        data: updateData,
      });
      
      // Manually invalidate project overview to refresh key tasks
      if (projectId) {
        queryClient.invalidateQueries({ queryKey: ['project-overview', projectId] });
      }
    } catch (error) {
      console.error('Failed to update task:', error);
    }
  };

  // Determine which actions to show
  const canMarkInProgress = task.status !== 'in_progress' && 
                            task.status !== 'done' && 
                            task.status !== 'completed' && 
                            task.status !== 'canceled' && 
                            task.status !== 'cancelled';
  
  const canMarkDone = task.status !== 'done' && 
                     task.status !== 'completed' && 
                     task.status !== 'canceled' && 
                     task.status !== 'cancelled';
  
  const canAssignToMe = currentUserId && task.assignee?.id !== currentUserId;

  return (
    <li
      className="text-xs cursor-pointer hover:bg-[var(--muted-surface)] rounded px-2 py-1 flex flex-col gap-0.5 relative"
      onClick={handleRowClick}
    >
      <div className="flex items-center justify-between gap-2">
        <span className="font-medium line-clamp-1 flex-1">
          {task.name}
        </span>
        <div className="flex items-center gap-1">
          {task.priority && (
            <span className={`text-[10px] uppercase px-1.5 py-0.5 rounded-full ${priorityColorClass}`}>
              {task.priority}
            </span>
          )}
          {canManageTasks && (
            <div className="relative" ref={menuRef}>
              <button
                type="button"
                className="task-menu-button p-0.5 hover:bg-[var(--muted-surface)] rounded text-[var(--muted)] hover:text-[var(--text)] transition-colors"
                onClick={handleMenuToggle}
                aria-label="Task actions"
                aria-expanded={isMenuOpen}
              >
                <span className="text-sm">⋯</span>
              </button>
              {isMenuOpen && (
                <div
                  className="task-menu absolute right-0 top-full mt-1 z-50 min-w-[160px] bg-[var(--surface)] border border-[var(--border)] rounded-lg shadow-lg py-1"
                  role="menu"
                  onClick={(e) => e.stopPropagation()}
                >
                  {canMarkInProgress && (
                    <button
                      type="button"
                      className="w-full text-left px-3 py-2 text-xs hover:bg-[var(--muted-surface)] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                      onClick={() => handleAction('in_progress')}
                      disabled={updateTaskMutation.isPending}
                    >
                      {updateTaskMutation.isPending ? 'Đang cập nhật...' : 'Chuyển sang đang làm'}
                    </button>
                  )}
                  {canMarkDone && (
                    <button
                      type="button"
                      className="w-full text-left px-3 py-2 text-xs hover:bg-[var(--muted-surface)] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                      onClick={() => handleAction('done')}
                      disabled={updateTaskMutation.isPending}
                    >
                      {updateTaskMutation.isPending ? 'Đang cập nhật...' : 'Đánh dấu đã xong'}
                    </button>
                  )}
                  {canAssignToMe && (
                    <button
                      type="button"
                      className="w-full text-left px-3 py-2 text-xs hover:bg-[var(--muted-surface)] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                      onClick={() => handleAction('assign_to_me')}
                      disabled={updateTaskMutation.isPending}
                    >
                      {updateTaskMutation.isPending ? 'Đang cập nhật...' : 'Giao cho tôi'}
                    </button>
                  )}
                  <div className="border-t border-[var(--border)] my-1"></div>
                  <button
                    type="button"
                    className="w-full text-left px-3 py-2 text-xs hover:bg-[var(--muted-surface)] transition-colors"
                    onClick={() => onNavigate(task.id)}
                  >
                    Xem chi tiết
                  </button>
                </div>
              )}
            </div>
          )}
        </div>
      </div>
      <div className="flex items-center justify-between text-[11px] text-[var(--muted)]">
        <span>Hạn: {task.end_date ?? '—'}</span>
        {task.assignee && (
          <span>Assignee: {task.assignee.name}</span>
        )}
      </div>
      {updateTaskMutation.isError && (
        <p className="mt-1 text-[10px] text-[var(--color-semantic-danger-600)]">
          Không cập nhật được task, vui lòng thử lại.
        </p>
      )}
    </li>
  );
};

export const ProjectDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user: currentUser } = useAuthStore();
  // Use reactive selector to ensure re-render when permissions change
  const canManageTasks = useAuthStore(
    (s) => {
      const perms = s.currentTenantPermissions ?? [];
      const hasPermission = perms.includes('tenant.manage_tasks');
      console.log('[ProjectDetailPage] canManageTasks check:', { perms, hasPermission });
      return hasPermission;
    }
  );
  const canCreateTasks = useAuthStore(
    (s) => (s.currentTenantPermissions ?? []).includes('tenant.create_tasks')
  );
  const canViewReports = useAuthStore(
    (s) => (s.currentTenantPermissions ?? []).includes('tenant.view_reports')
  );
  
  // Debug: Log canManageTasks value
  React.useEffect(() => {
    console.log('[ProjectDetailPage] canManageTasks:', canManageTasks, 'canCreateTasks:', canCreateTasks);
  }, [canManageTasks, canCreateTasks]);
  const [activeTab, setActiveTab] = useState<TabId>('overview');
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
  const [showAddMemberModal, setShowAddMemberModal] = useState(false);
  const [showUploadDocumentModal, setShowUploadDocumentModal] = useState(false);
  const [showApplyTemplateModal, setShowApplyTemplateModal] = useState(false);
  const [selectedUserId, setSelectedUserId] = useState<string>('');
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [documentName, setDocumentName] = useState<string>('');
  const [documentDescription, setDocumentDescription] = useState<string>('');
  const [documentCategory, setDocumentCategory] = useState<string>('general');
  
  // Debug: Log id to check if it's being parsed correctly
  React.useEffect(() => {
    console.log('[ProjectDetailPage] Project ID from params:', id);
  }, [id]);
  
  // Note: Permissions should be loaded by App.tsx checkAuth on mount
  // If permissions are missing, they will be loaded automatically
  
  const { data: projectData, isLoading, error, refetch: refetchProject } = useProject(id!);
  const { data: activityData, isLoading: activityLoading, error: activityError } = useProjectsActivity(20);
  const { data: kpisData, isLoading: kpisLoading } = useProjectKpis(id!);
  const { data: alertsData, isLoading: alertsLoading } = useProjectAlerts(id!);
  const { data: overviewData, isLoading: isOverviewLoading, error: overviewError } = useProjectOverview(id);
  const deleteProject = useDeleteProject();
  const archiveProject = useArchiveProject();
  const { data: tasksData, isLoading: tasksLoading, refetch: refetchTasks } = useProjectTasks(id!, {}, { page: 1, per_page: 50 });
  const { data: documentsData, isLoading: documentsLoading, refetch: refetchDocuments } = useProjectDocuments(id!, {}, { page: 1, per_page: 50 });
  // Get all users for team member selection (with high per_page to get all users)
  const { data: usersData } = useUsers(undefined, { per_page: 1000 }); // For displaying assignee names in tasks and adding team members
  const deleteTask = useDeleteTask();
  const addTeamMember = useAddTeamMember();
  const removeTeamMember = useRemoveTeamMember();
  const uploadDocument = useUploadProjectDocument();
  
  const project = projectData?.data;
  
  // Extract projectId for navigation (Round 73)
  // Priority: overviewData?.data?.project?.id > route params
  const projectId = overviewData?.data?.project?.id ?? id ?? null;
  
  // Transform KPIs data for KpiStrip component
  const kpiItems: KpiItem[] = React.useMemo(() => {
    if (!kpisData?.data) return [];
    const data = kpisData.data;
    return [
      { 
        label: 'Total Tasks', 
        value: data.total_tasks || 0,
        variant: 'default'
      },
      { 
        label: 'Completed Tasks', 
        value: data.completed_tasks || 0,
        variant: 'success'
      },
      { 
        label: 'Team Members', 
        value: data.team_members || 0,
        variant: 'info'
      },
      { 
        label: 'Documents', 
        value: data.documents_count || 0,
        variant: 'default'
      },
      { 
        label: 'Progress', 
        value: `${data.progress_percentage || 0}%`,
        variant: data.progress_percentage >= 80 ? 'success' : data.progress_percentage >= 50 ? 'info' : 'warning'
      },
    ];
  }, [kpisData]);
  
  // Transform alerts data for AlertBar component
  const alerts = React.useMemo(() => {
    if (!alertsData?.data) return [];
    return alertsData.data.map((alert: any) => ({
      id: alert.id,
      message: alert.message,
      type: alert.type || 'warning',
      priority: alert.priority || 5,
      created_at: alert.created_at,
      metadata: alert.metadata,
    }));
  }, [alertsData]);
  
  const handleDismissAlert = useCallback((alertId: string | number) => {
    // TODO: Implement dismiss API call if needed
    console.log('Dismiss alert:', alertId);
  }, []);
  
  const handleDismissAllAlerts = useCallback(() => {
    // TODO: Implement dismiss all API call if needed
    console.log('Dismiss all alerts');
  }, []);
  
  // Get users not already in team
  const availableUsers = React.useMemo(() => {
    // usersData is already the response object with { data: User[], meta?: {...} }
    const users = Array.isArray(usersData?.data) ? usersData.data : [];
    const projectUsers = Array.isArray(project?.users) ? project.users : [];
    if (!Array.isArray(users) || projectUsers.length === 0) return users;
    const teamMemberIds = new Set(projectUsers.map(u => String(u.id)));
    return users.filter(u => !teamMemberIds.has(String(u.id)));
  }, [usersData, project?.users]);

  const handleEdit = useCallback(() => {
    navigate(`/app/projects/${id}/edit`);
  }, [navigate, id]);

  const handleDelete = useCallback(async () => {
    if (!id) return;
    
    try {
      await deleteProject.mutateAsync(id);
      navigate('/app/projects');
    } catch (error: any) {
      console.error('Failed to delete project:', error);
      
      // Handle specific error: project has tasks
      if (error?.response?.status === 409 || error?.status === 409) {
        const errorMessage = error?.response?.data?.message || 
                            error?.message || 
                            'Không thể xoá dự án vì vẫn còn công việc đang tồn tại. Vui lòng xoá hoặc hoàn thành tất cả công việc trước khi xoá dự án.';
        alert(errorMessage);
      } else {
        alert('Không thể xoá dự án. Vui lòng thử lại sau.');
      }
    } finally {
      setShowDeleteConfirm(false);
    }
  }, [id, deleteProject, navigate]);

  const handleArchive = useCallback(async () => {
    if (!id) return;
    
    if (!confirm(`Are you sure you want to archive "${project?.name}"?`)) {
      return;
    }
    
    try {
      await archiveProject.mutateAsync(id);
      // Optionally refresh project data
      // navigate('/app/projects');
    } catch (error) {
      console.error('Failed to archive project:', error);
      alert('Failed to archive project. Please try again.');
    }
  }, [id, project, archiveProject]);

  const handleAddTeamMember = useCallback(async () => {
    if (!id || !selectedUserId) return;
    
    try {
      await addTeamMember.mutateAsync({ projectId: id, userId: selectedUserId });
      setShowAddMemberModal(false);
      setSelectedUserId('');
      refetchProject();
    } catch (error: any) {
      console.error('Failed to add team member:', error);
      alert(error?.response?.data?.error?.message || 'Failed to add team member. Please try again.');
    }
  }, [id, selectedUserId, addTeamMember, refetchProject]);

  const handleRemoveTeamMember = useCallback(async (userId: string | number) => {
    if (!id) return;
    
    if (!confirm('Are you sure you want to remove this team member?')) {
      return;
    }
    
    try {
      await removeTeamMember.mutateAsync({ projectId: id, userId });
      refetchProject();
    } catch (error: any) {
      console.error('Failed to remove team member:', error);
      alert(error?.response?.data?.error?.message || 'Failed to remove team member. Please try again.');
    }
  }, [id, removeTeamMember, refetchProject]);

  const handleFileSelect = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      setSelectedFile(file);
      if (!documentName) {
        setDocumentName(file.name);
      }
    }
  }, [documentName]);

  const handleUploadDocument = useCallback(async () => {
    if (!id || !selectedFile) return;
    
    try {
      await uploadDocument.mutateAsync({
        projectId: id,
        file: selectedFile,
        data: {
          name: documentName || selectedFile.name,
          description: documentDescription || undefined,
          category: documentCategory || 'general',
        },
      });
      setShowUploadDocumentModal(false);
      setSelectedFile(null);
      setDocumentName('');
      setDocumentDescription('');
      setDocumentCategory('general');
      refetchDocuments();
    } catch (error: any) {
      console.error('Failed to upload document:', error);
      alert(error?.response?.data?.error?.message || 'Failed to upload document. Please try again.');
    }
  }, [id, selectedFile, documentName, documentDescription, documentCategory, uploadDocument, refetchDocuments]);

  // Transform activity data
  const activities: Activity[] = React.useMemo(() => {
    if (!activityData?.data) return [];
    return Array.isArray(activityData.data)
      ? activityData.data
          .filter((activity: any) => activity.project_id === id || activity.metadata?.project_id === id)
          .map((activity: any) => ({
            id: activity.id,
            type: activity.type || 'project',
            action: activity.action,
            description: activity.description || activity.message || 'Activity',
            timestamp: activity.timestamp || activity.created_at || activity.createdAt,
            user: activity.user,
            metadata: activity.metadata,
          }))
      : [];
  }, [activityData, id]);

  if (isLoading) {
    return (
      <Container>
        <div className="space-y-6">
          <div className="animate-pulse">
            <div className="h-8 bg-[var(--muted-surface)] rounded w-1/3 mb-4"></div>
            <div className="h-4 bg-[var(--muted-surface)] rounded w-1/2"></div>
          </div>
        </div>
      </Container>
    );
  }

  if (error) {
    return (
      <Container>
        <Card>
          <CardContent className="py-8">
            <div className="text-center">
              <p className="text-[var(--muted)] mb-4">
                Error loading project: {(error as Error).message}
              </p>
              <div className="flex gap-3 justify-center">
                <Button variant="secondary" onClick={() => navigate('/app/projects')}>
                  Back to Projects
                </Button>
                <Button variant="primary" onClick={() => refetchProject()}>
                  Retry
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>
      </Container>
    );
  }

  if (!project) {
    return (
      <Container>
        <Card>
          <CardContent className="py-8">
            <div className="text-center">
              <p className="text-[var(--muted)] mb-4">Project not found</p>
              <Button variant="secondary" onClick={() => navigate('/app/projects')}>
                Back to Projects
              </Button>
            </div>
          </CardContent>
        </Card>
      </Container>
    );
  }

  return (
    <ToastProvider>
      <Container>
        <div className="space-y-6" data-testid="project-detail-page" data-can-manage-tasks={String(canManageTasks)} data-active-tab={activeTab}>
        {/* Page Header */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div className="flex-1">
            <div className="flex items-center gap-3 mb-2 flex-wrap">
              <h1 className="text-[var(--font-heading-3-size)] font-semibold text-[var(--text)]">
                {project.name}
              </h1>
              <span className={`text-xs px-2 py-1 rounded ${
                project.status === 'active'
                  ? 'bg-green-100 text-green-700'
                  : project.status === 'completed'
                  ? 'bg-gray-100 text-gray-700'
                  : project.status === 'on_hold'
                  ? 'bg-yellow-100 text-yellow-700'
                  : project.status === 'planning'
                  ? 'bg-blue-100 text-blue-700'
                  : 'bg-gray-100 text-gray-500'
              }`}>
                {project.status}
              </span>
              {id && <ProjectCostHealthHeader projectId={id} />}
              {id && <ProjectCostFlowStatusHeader projectId={id} />}
              {id && <ProjectCostAlertsIcon projectId={id} className="ml-2" />}
            </div>
            {project.description && (
              <p className="text-[var(--font-body-size)] text-[var(--muted)]">
                {project.description}
              </p>
            )}
          </div>
          
          {/* Quick Actions */}
          <div className="flex items-center gap-2">
            <Button variant="secondary" onClick={handleEdit}>
              Edit
            </Button>
            <Button variant="secondary" onClick={handleArchive}>
              Archive
            </Button>
            <Button
              variant="secondary"
              onClick={() => setShowDeleteConfirm(true)}
              style={{ color: 'var(--color-semantic-danger-600)' }}
            >
              Delete
            </Button>
          </div>
        </div>

        {/* KPI Strip */}
        <KpiStrip 
          kpis={kpiItems} 
          loading={kpisLoading}
          columns={5}
        />

        {/* Alert Bar */}
        <AlertBar
          alerts={alerts}
          loading={alertsLoading}
          onDismiss={handleDismissAlert}
          onDismissAll={handleDismissAllAlerts}
          maxDisplay={3}
        />

        {/* Delete Confirmation Modal */}
        {showDeleteConfirm && (
          <Card style={{ borderColor: 'var(--color-semantic-danger-200)' }}>
            <CardContent className="py-6">
              <h3 className="text-lg font-semibold text-[var(--text)] mb-2">
                Delete Project?
              </h3>
              <p className="text-sm text-[var(--muted)] mb-4">
                Are you sure you want to delete "{project.name}"? This action cannot be undone.
              </p>
              <div className="flex items-center gap-3">
                <Button
                  variant="secondary"
                  onClick={() => setShowDeleteConfirm(false)}
                  disabled={deleteProject.isPending}
                >
                  Cancel
                </Button>
                <Button
                  onClick={handleDelete}
                  disabled={deleteProject.isPending}
                  style={{ backgroundColor: 'var(--color-semantic-danger-600)' }}
                >
                  {deleteProject.isPending ? 'Deleting...' : 'Delete'}
                </Button>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Tabs */}
        <div className="border-b border-[var(--border)]">
          <div className="flex items-center gap-1 overflow-x-auto">
            {tabs.map((tab) => (
              <button
                key={tab.id}
                data-testid={`project-tab-${tab.id}`}
                onClick={() => setActiveTab(tab.id)}
                className={`px-4 py-2 text-sm font-medium transition-colors whitespace-nowrap border-b-2 ${
                  activeTab === tab.id
                    ? 'border-[var(--accent)] text-[var(--accent)]'
                    : 'border-transparent text-[var(--muted)] hover:text-[var(--text)]'
                }`}
                aria-pressed={activeTab === tab.id}
                aria-label={tab.label}
              >
                {tab.icon && <span className="mr-2">{tab.icon}</span>}
                {tab.label}
              </button>
            ))}
          </div>
        </div>

        {/* Tab Content */}
        <div>
          {/* Overview Tab */}
          {activeTab === 'overview' && (
            <>
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
              {/* Main Content */}
              <div className="lg:col-span-2 space-y-6">
                {/* Project Information Card */}
                <Card>
                  <CardHeader>
                    <CardTitle>Project Information</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                      <div>
                        <label className="text-sm font-medium text-[var(--muted)]">Project Name</label>
                        <p className="text-[var(--text)] mt-1">{project.name}</p>
                      </div>
                      {project.code && (
                        <div>
                          <label className="text-sm font-medium text-[var(--muted)]">Project Code</label>
                          <p className="text-[var(--text)] mt-1">{project.code}</p>
                        </div>
                      )}
                      <div>
                        <label className="text-sm font-medium text-[var(--muted)]">Status</label>
                        <p className="text-[var(--text)] mt-1 capitalize">{project.status}</p>
                      </div>
                      {project.priority && (
                        <div>
                          <label className="text-sm font-medium text-[var(--muted)]">Priority</label>
                          <p className="text-[var(--text)] mt-1 capitalize">{project.priority}</p>
                        </div>
                      )}
                      {(project as any).client && (
                        <div>
                          <label className="text-sm font-medium text-[var(--muted)]">Client</label>
                          <p className="text-[var(--text)] mt-1">{(project as any).client?.name || 'No client assigned'}</p>
                        </div>
                      )}
                      {(project as any).owner && (
                        <div>
                          <label className="text-sm font-medium text-[var(--muted)]">Project Manager</label>
                          <p className="text-[var(--text)] mt-1">{(project as any).owner?.name || 'No manager assigned'}</p>
                        </div>
                      )}
                      {project.start_date && (
                        <div>
                          <label className="text-sm font-medium text-[var(--muted)]">Start Date</label>
                          <p className="text-[var(--text)] mt-1">
                            {new Date(project.start_date).toLocaleDateString()}
                          </p>
                        </div>
                      )}
                      {project.end_date && (
                        <div>
                          <label className="text-sm font-medium text-[var(--muted)]">End Date</label>
                          <p className="text-[var(--text)] mt-1">
                            {new Date(project.end_date).toLocaleDateString()}
                          </p>
                        </div>
                      )}
                      {project.budget_total && (
                        <div>
                          <label className="text-sm font-medium text-[var(--muted)]">Total Budget</label>
                          <p className="text-[var(--text)] mt-1">
                            ${project.budget_total.toLocaleString()}
                          </p>
                        </div>
                      )}
                    </div>
                    {project.description && (
                      <div className="mt-6">
                        <label className="text-sm font-medium text-[var(--muted)] mb-2 block">Description</label>
                        <p className="text-[var(--text)] whitespace-pre-wrap">{project.description}</p>
                      </div>
                    )}
                  </CardContent>
                </Card>

                {/* Project Progress Card */}
                <Card>
                  <CardHeader>
                    <CardTitle>Project Progress</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className="mb-4">
                      <div className="flex justify-between text-sm text-[var(--muted)] mb-2">
                        <span>Overall Progress</span>
                        <span className="font-semibold text-[var(--text)]">
                          {kpisData?.data?.progress_percentage || 0}%
                        </span>
                      </div>
                      <div className="w-full bg-[var(--muted-surface)] rounded-full h-3">
                        <div 
                          className="bg-[var(--accent)] h-3 rounded-full transition-all duration-300" 
                          style={{ width: `${kpisData?.data?.progress_percentage || 0}%` }}
                        ></div>
                      </div>
                    </div>
                    
                    {/* Quick Actions */}
                    <div className="flex flex-wrap gap-3 mt-6">
                      {project.status !== 'active' && (
                        <Button
                          variant="secondary"
                          onClick={async () => {
                            // TODO: Implement status update API call
                            console.log('Start project');
                          }}
                        >
                          Start Project
                        </Button>
                      )}
                      {project.status === 'active' && (
                        <Button
                          variant="secondary"
                          onClick={async () => {
                            // TODO: Implement status update API call
                            console.log('Pause project');
                          }}
                        >
                          Pause Project
                        </Button>
                      )}
                      {project.status !== 'completed' && (
                        <Button
                          variant="secondary"
                          onClick={async () => {
                            // TODO: Implement status update API call
                            console.log('Complete project');
                          }}
                        >
                          Complete Project
                        </Button>
                      )}
                    </div>
                  </CardContent>
                </Card>
              </div>

              {/* Sidebar */}
              <div className="space-y-6">
                {/* Project Stats Card */}
                <Card>
                  <CardHeader>
                    <CardTitle>Project Stats</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className="space-y-4">
                      <div className="flex justify-between items-center">
                        <span className="text-sm text-[var(--muted)]">Total Tasks</span>
                        <span className="text-sm font-semibold text-[var(--text)]">
                          {kpisData?.data?.total_tasks || 0}
                        </span>
                      </div>
                      <div className="flex justify-between items-center">
                        <span className="text-sm text-[var(--muted)]">Completed Tasks</span>
                        <span className="text-sm font-semibold text-[var(--text)]">
                          {kpisData?.data?.completed_tasks || 0}
                        </span>
                      </div>
                      <div className="flex justify-between items-center">
                        <span className="text-sm text-[var(--muted)]">Team Members</span>
                        <span className="text-sm font-semibold text-[var(--text)]">
                          {kpisData?.data?.team_members || 0}
                        </span>
                      </div>
                      <div className="flex justify-between items-center">
                        <span className="text-sm text-[var(--muted)]">Documents</span>
                        <span className="text-sm font-semibold text-[var(--text)]">
                          {kpisData?.data?.documents_count || 0}
                        </span>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              </div>
            </div>

            {/* Project Health Summary Card */}
            {!isOverviewLoading && !overviewError && overviewData?.data?.health && (
              <Card className="mt-6">
                <CardHeader>
                  <CardTitle>Sức khỏe dự án</CardTitle>
                </CardHeader>
                <CardContent>
                  {(() => {
                    const health = overviewData.data.health;
                    
                    // Overall status badge
                    const getOverallStatusConfig = (status: string) => {
                      const label = getOverallStatusLabel(status);
                      switch (status) {
                        case 'good':
                          return { label, className: 'bg-green-100 text-green-700' };
                        case 'warning':
                          return { label, className: 'bg-yellow-100 text-yellow-700' };
                        case 'critical':
                          return { label, className: 'bg-red-100 text-red-700' };
                        default:
                          return { label, className: 'bg-gray-100 text-gray-700' };
                      }
                    };

                    const overallConfig = getOverallStatusConfig(health.overall_status);

                    // Schedule status text & color
                    const getScheduleStatusConfig = (status: string) => {
                      const text = getScheduleStatusLabel(status);
                      switch (status) {
                        case 'on_track':
                          return { text, className: 'text-green-700' };
                        case 'at_risk':
                          return { text, className: 'text-yellow-700' };
                        case 'delayed':
                          return { text, className: 'text-red-700' };
                        case 'no_tasks':
                          return { text, className: 'text-slate-500' };
                        default:
                          return { text, className: 'text-gray-700' };
                      }
                    };

                    const scheduleConfig = getScheduleStatusConfig(health.schedule_status);

                    // Round 73: Determine if we should show navigation links
                    const shouldShowScheduleLink = projectId && 
                      (health.schedule_status === 'delayed' || health.schedule_status === 'at_risk');
                    const shouldShowCostLink = projectId && 
                      (health.cost_status === 'over_budget' || health.cost_status === 'at_risk');

                    return (
                      <div className="space-y-4">
                        {/* Overall status pill */}
                        <div className="flex items-center gap-2">
                          <span className="text-sm font-medium text-[var(--muted)]">Trạng thái tổng thể:</span>
                          <span className={`px-3 py-1 rounded-full text-sm font-semibold ${overallConfig.className}`}>
                            {overallConfig.label}
                          </span>
                        </div>

                        {/* Task completion & schedule info */}
                        <div className="space-y-2">
                          <div className="text-sm text-[var(--muted)]">
                            <span className="font-medium">Tiến độ & task:</span>{' '}
                            {health.tasks_completion_rate !== null ? (
                              <>Hoàn thành: <span className="font-semibold text-[var(--text)]">{Math.round(health.tasks_completion_rate * 100)}%</span></>
                            ) : (
                              <>Hoàn thành: <span className="font-semibold text-[var(--muted)]">—</span></>
                            )}
                            {' · '}
                            {health.blocked_tasks_ratio !== null ? (
                              <>Blocked: <span className="font-semibold text-[var(--text)]">{Math.round(health.blocked_tasks_ratio * 100)}%</span></>
                            ) : (
                              <>Blocked: <span className="font-semibold text-[var(--muted)]">—</span></>
                            )}
                            {' · '}
                            Task quá hạn: <span className="font-semibold text-[var(--text)]">{health.overdue_tasks}</span>
                          </div>
                          <div className="text-sm">
                            <span className="font-medium text-[var(--muted)]">Tiến độ:</span>{' '}
                            <span className={`font-semibold ${scheduleConfig.className}`}>
                              {scheduleConfig.text}
                            </span>
                            {/* Round 73: Deep link to Tasks when schedule is at risk or delayed */}
                            {shouldShowScheduleLink && (
                              <>
                                {' · '}
                                <button
                                  type="button"
                                  onClick={() => navigate(`/app/tasks?project_id=${projectId}`)}
                                  className="text-xs text-[var(--color-primary-600)] hover:underline"
                                >
                                  Xem danh sách task quá hạn / sắp đến hạn
                                </button>
                              </>
                            )}
                          </div>
                        </div>

                        {/* Cost info */}
                        <div className="text-sm">
                          <span className="font-medium text-[var(--muted)]">Chi phí:</span>{' '}
                          <span className="font-semibold text-[var(--text)]">
                            {getCostStatusLabel(health.cost_status)}
                          </span>
                          {health.cost_overrun_percent !== null && health.cost_overrun_percent > 0 && (
                            <>
                              {' · '}
                              <span className="text-[var(--color-semantic-danger-600)]">
                                Overrun: {health.cost_overrun_percent.toFixed(health.cost_overrun_percent >= 10 ? 0 : 1)}%
                              </span>
                            </>
                          )}
                          {/* Round 73: Deep link to Project Cost Report when cost is at risk or over budget */}
                          {shouldShowCostLink && (
                            <>
                              {' · '}
                              <button
                                type="button"
                                onClick={() => navigate(`/app/reports/portfolio/projects?project_id=${projectId}`)}
                                className="text-xs text-[var(--color-primary-600)] hover:underline"
                              >
                                Xem chi tiết chi phí dự án
                              </button>
                            </>
                          )}
                        </div>
                      </div>
                    );
                  })()}
                </CardContent>
              </Card>
            )}

            {/* Project Health History Card */}
            {projectId && (
              <ProjectHealthHistoryCard
                projectId={projectId}
                canViewReports={canViewReports}
              />
            )}

            {/* Project Overview Cockpit - Execution & Financials */}
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3 mt-6">
              {/* Execution snapshot */}
              <Card>
                <CardHeader>
                  <CardTitle>Tiến độ công việc</CardTitle>
                  <p className="text-sm text-[var(--muted)] mt-1">
                    Tổng quan task trong dự án
                  </p>
                </CardHeader>
                <CardContent>
                  {isOverviewLoading && (
                    <div className="space-y-2">
                      <div className="h-4 bg-[var(--muted-surface)] rounded animate-pulse"></div>
                      <div className="h-4 bg-[var(--muted-surface)] rounded animate-pulse"></div>
                    </div>
                  )}
                  {overviewError && (
                    <div className="text-sm text-[var(--color-semantic-danger-600)]">
                      Không tải được tổng quan công việc
                    </div>
                  )}
                  {overviewData?.data && (
                    <div className="space-y-2">
                      <div className="text-sm text-[var(--muted)]">
                        Tổng task: <span className="font-semibold text-[var(--text)]">{overviewData.data.tasks.total}</span>
                      </div>
                      <ul className="text-sm grid grid-cols-2 gap-y-1">
                        <li>Backlog: <span className="font-semibold">{overviewData.data.tasks.by_status.backlog ?? 0}</span></li>
                        <li>Đang làm: <span className="font-semibold">{overviewData.data.tasks.by_status.in_progress ?? 0}</span></li>
                        <li>Bị chặn: <span className="font-semibold text-[var(--color-semantic-danger-600)]">{overviewData.data.tasks.by_status.blocked ?? 0}</span></li>
                        <li>Hoàn thành: <span className="font-semibold text-[var(--color-semantic-success-600)]">{overviewData.data.tasks.by_status.done ?? 0}</span></li>
                        <li>Hủy: <span className="font-semibold text-[var(--muted)]">{overviewData.data.tasks.by_status.canceled ?? 0}</span></li>
                      </ul>
                      <div className="mt-3 text-sm space-y-1">
                        <div>
                          Quá hạn:{' '}
                          <span className="font-semibold text-[var(--color-semantic-danger-600)]">
                            {overviewData.data.tasks.overdue}
                          </span>
                        </div>
                        <div>
                          Sắp đến hạn (3 ngày):{' '}
                          <span className="font-semibold text-[var(--color-semantic-warning-600)]">
                            {overviewData.data.tasks.due_soon}
                          </span>
                        </div>
                      </div>
                    </div>
                  )}
                  {/* Round 73: Deep link to Tasks board */}
                  {projectId && overviewData?.data && (
                    <div className="mt-4 pt-3 border-t border-[var(--border)]">
                      <button
                        type="button"
                        onClick={() => navigate(`/app/tasks?project_id=${projectId}`)}
                        className="text-xs text-[var(--color-primary-600)] hover:underline"
                      >
                        Xem bảng task chi tiết
                      </button>
                    </div>
                  )}
                </CardContent>
              </Card>

              {/* Financial snapshot */}
              <Card className="md:col-span-1 xl:col-span-2">
                <CardHeader>
                  <CardTitle>Tài chính dự án</CardTitle>
                  <p className="text-sm text-[var(--muted)] mt-1">
                    Hợp đồng, ngân sách, chi phí & overrun
                  </p>
                </CardHeader>
                <CardContent>
                  {isOverviewLoading && (
                    <div className="space-y-2">
                      <div className="h-4 bg-[var(--muted-surface)] rounded animate-pulse"></div>
                      <div className="h-4 bg-[var(--muted-surface)] rounded animate-pulse"></div>
                    </div>
                  )}
                  {overviewError && (
                    <div className="text-sm text-[var(--color-semantic-danger-600)]">
                      Không tải được tổng quan tài chính
                    </div>
                  )}
                  {overviewData?.data && (
                    <>
                      {!overviewData.data.financials.has_financial_data && (
                        <div className="text-sm text-[var(--muted)]">
                          Chưa có dữ liệu hợp đồng cho dự án này.
                        </div>
                      )}
                      {overviewData.data.financials.has_financial_data && (
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4 text-sm">
                          <div>
                            <div className="text-[var(--muted)] mb-1">Số hợp đồng</div>
                            <div className="text-lg font-semibold text-[var(--text)]">
                              {overviewData.data.financials.contracts_count}
                            </div>
                          </div>

                          <div>
                            <div className="text-[var(--muted)] mb-1">Giá trị hợp đồng</div>
                            <MoneyCell
                              value={overviewData.data.financials.contracts_value_total ?? null}
                              currency={overviewData.data.financials.currency ?? undefined}
                              fallback="-"
                              tone="normal"
                            />
                          </div>

                          <div>
                            <div className="text-[var(--muted)] mb-1">Ngân sách</div>
                            <MoneyCell
                              value={overviewData.data.financials.budget_total ?? null}
                              currency={overviewData.data.financials.currency ?? undefined}
                              fallback="-"
                              tone="normal"
                            />
                          </div>

                          <div>
                            <div className="text-[var(--muted)] mb-1">Chi phí thực tế</div>
                            <MoneyCell
                              value={overviewData.data.financials.actual_total ?? null}
                              currency={overviewData.data.financials.currency ?? undefined}
                              fallback="-"
                              tone="normal"
                            />
                          </div>

                          <div className="md:col-span-2 xl:col-span-2">
                            <div className="text-[var(--muted)] mb-1">Overrun (Actual – Contract)</div>
                            <MoneyCell
                              value={overviewData.data.financials.overrun_amount_total ?? null}
                              currency={overviewData.data.financials.currency ?? undefined}
                              fallback="-"
                              showPlusWhenPositive
                              tone={
                                overviewData.data.financials.overrun_amount_total && overviewData.data.financials.overrun_amount_total > 0
                                  ? 'danger'
                                  : 'muted'
                              }
                            />
                            <div className="mt-1 text-xs text-[var(--muted)]">
                              Hợp đồng vượt ngân sách: {overviewData.data.financials.over_budget_contracts_count} | 
                              Hợp đồng bị overrun: {overviewData.data.financials.overrun_contracts_count}
                            </div>
                          </div>
                        </div>
                      )}
                      {/* Round 73: Deep links to Reports */}
                      {projectId && (
                        <div className="mt-4 pt-3 border-t border-[var(--border)] space-y-2">
                          {overviewData.data.financials.has_financial_data && (
                            <div>
                              <button
                                type="button"
                                onClick={() => navigate(`/app/reports/portfolio/projects?project_id=${projectId}`)}
                                className="text-sm font-medium text-[var(--color-primary-600)] hover:underline"
                              >
                                Xem báo cáo chi tiết chi phí
                              </button>
                            </div>
                          )}
                          {overviewData.data.financials.has_financial_data && 
                           overviewData.data.financials.overrun_amount_total && 
                           overviewData.data.financials.overrun_amount_total > 0 && (
                            <div>
                              <button
                                type="button"
                                onClick={() => navigate(`/app/reports/cost-overruns?project_id=${projectId}`)}
                                className="text-xs text-[var(--color-semantic-danger-600)] hover:underline"
                              >
                                Xem chi tiết hợp đồng vượt chi phí
                              </button>
                            </div>
                          )}
                        </div>
                      )}
                    </>
                  )}
                </CardContent>
              </Card>
            </div>

            {/* Key Tasks Card */}
            <Card className="mt-4">
              <CardHeader>
                <CardTitle>Task quan trọng cần xử lý</CardTitle>
                <p className="text-sm text-[var(--muted)] mt-1">
                  Overdue, sắp đến hạn và đang bị chặn trong dự án này
                </p>
              </CardHeader>
              <CardContent>
                {isOverviewLoading && (
                  <div className="space-y-3 animate-pulse">
                    <div className="h-20 bg-[var(--muted-surface)] rounded"></div>
                    <div className="h-20 bg-[var(--muted-surface)] rounded"></div>
                    <div className="h-20 bg-[var(--muted-surface)] rounded"></div>
                  </div>
                )}

                {!isOverviewLoading && overviewError && (
                  <p className="text-sm text-[var(--color-semantic-danger-600)]">
                    Không tải được danh sách task quan trọng.
                  </p>
                )}

                {!isOverviewLoading && !overviewError && overviewData?.data && (() => {
                  const rawTasks = overviewData?.data?.tasks;
                  const keyTasks = rawTasks?.key_tasks ?? {
                    overdue: [],
                    due_soon: [],
                    blocked: [],
                  };
                  const overdueKeyTasks = keyTasks.overdue ?? [];
                  const dueSoonKeyTasks = keyTasks.due_soon ?? [];
                  const blockedKeyTasks = keyTasks.blocked ?? [];

                  return (
                    <div className="grid gap-4 md:grid-cols-3">
                      {/* Overdue column */}
                      <div>
                        <div className="flex items-center justify-between mb-2">
                          <span className="font-semibold text-[var(--color-semantic-danger-600)]">
                            Overdue
                          </span>
                          <span className="text-xs text-[var(--muted)]">
                            {overdueKeyTasks.length} task
                          </span>
                        </div>

                        {overdueKeyTasks.length === 0 ? (
                          <p className="text-xs text-[var(--muted)]">
                            Không có task quá hạn nào 🎉
                          </p>
                        ) : (
                          <ul className="space-y-2">
                            {overdueKeyTasks.map((task) => (
                              <KeyTaskItem
                                key={task.id}
                                task={task}
                                priorityColorClass="bg-red-100 text-red-700"
                                onNavigate={(taskId) => navigate(`/app/tasks/${taskId}`)}
                                canManageTasks={canManageTasks}
                                currentUserId={currentUser?.id}
                                projectId={id}
                              />
                            ))}
                          </ul>
                        )}
                      </div>

                      {/* Due soon column */}
                      <div>
                        <div className="flex items-center justify-between mb-2">
                          <span className="font-semibold text-[var(--color-semantic-warning-600)]">
                            Sắp đến hạn (≤ 3 ngày)
                          </span>
                          <span className="text-xs text-[var(--muted)]">
                            {dueSoonKeyTasks.length} task
                          </span>
                        </div>

                        {dueSoonKeyTasks.length === 0 ? (
                          <p className="text-xs text-[var(--muted)]">
                            Không có task sắp đến hạn nào 🎉
                          </p>
                        ) : (
                          <ul className="space-y-2">
                            {dueSoonKeyTasks.map((task) => (
                              <KeyTaskItem
                                key={task.id}
                                task={task}
                                priorityColorClass="bg-orange-100 text-orange-700"
                                onNavigate={(taskId) => navigate(`/app/tasks/${taskId}`)}
                                canManageTasks={canManageTasks}
                                currentUserId={currentUser?.id}
                                projectId={id}
                              />
                            ))}
                          </ul>
                        )}
                      </div>

                      {/* Blocked column */}
                      <div>
                        <div className="flex items-center justify-between mb-2">
                          <span className="font-semibold text-[var(--muted)]">
                            Đang bị chặn (blocked)
                          </span>
                          <span className="text-xs text-[var(--muted)]">
                            {blockedKeyTasks.length} task
                          </span>
                        </div>

                        {blockedKeyTasks.length === 0 ? (
                          <p className="text-xs text-[var(--muted)]">
                            Không có task bị chặn nào 🎉
                          </p>
                        ) : (
                          <ul className="space-y-2">
                            {blockedKeyTasks.map((task) => (
                              <KeyTaskItem
                                key={task.id}
                                task={task}
                                priorityColorClass="bg-purple-100 text-purple-700"
                                onNavigate={(taskId) => navigate(`/app/tasks/${taskId}`)}
                                canManageTasks={canManageTasks}
                                currentUserId={currentUser?.id}
                                projectId={id}
                              />
                            ))}
                          </ul>
                        )}
                      </div>
                    </div>
                    );
                })()}
              </CardContent>
            </Card>
            </>
          )}

          {/* Tasks Tab */}
          {activeTab === 'tasks' && (
            <div data-testid="project-tasks-panel">
            <Card>
              <CardHeader>
                <div className="flex items-center justify-between">
                  <CardTitle>
                    Tasks
                    {(() => {
                      const tasks = Array.isArray(tasksData?.data) ? tasksData.data : [];
                      return tasks.length > 0 && (
                        <span className="ml-2 text-sm font-normal text-[var(--muted)]">
                          ({tasks.length})
                        </span>
                      );
                    })()}
                  </CardTitle>
                  <div className="flex items-center gap-2">
                    {canManageTasks && (
                      <Button
                        data-testid="apply-template-button"
                        variant="secondary"
                        size="sm"
                        onClick={() => setShowApplyTemplateModal(true)}
                      >
                        Áp dụng mẫu công việc
                      </Button>
                    )}
                    {(canManageTasks || canCreateTasks) && (
                      <Button
                        variant="secondary"
                        size="sm"
                        onClick={() => navigate(`/app/tasks/create?project_id=${id}`)}
                      >
                        Add Task
                      </Button>
                    )}
                  </div>
                </div>
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
                ) : (() => {
                  const tasks = Array.isArray(tasksData?.data) ? tasksData.data : [];
                  return tasks.length > 0 ? (
                    <div className="space-y-3">
                      {tasks.map((task: Task) => (
                      <div
                        key={task.id}
                        className="p-4 border border-[var(--border)] rounded-lg hover:bg-[var(--muted-surface)] transition-colors"
                      >
                        <div className="flex items-start justify-between">
                          <div className="flex-1">
                            <div className="flex items-center gap-2 mb-2">
                              <h4
                                className="font-semibold text-[var(--text)] cursor-pointer hover:text-[var(--accent)]"
                                onClick={() => navigate(`/app/tasks/${task.id}`)}
                              >
                                {task.title}
                              </h4>
                              <span className={`text-xs px-2 py-1 rounded ${
                                task.status === 'completed'
                                  ? 'bg-green-100 text-green-700'
                                  : task.status === 'in_progress'
                                  ? 'bg-blue-100 text-blue-700'
                                  : task.status === 'pending'
                                  ? 'bg-yellow-100 text-yellow-700'
                                  : 'bg-gray-100 text-gray-500'
                              }`}>
                                {task.status.replace('_', ' ')}
                              </span>
                              {task.priority && (
                                <span className={`text-xs capitalize ${
                                  task.priority === 'urgent'
                                    ? 'text-red-600 font-semibold'
                                    : task.priority === 'high'
                                    ? 'text-orange-600 font-medium'
                                    : task.priority === 'medium'
                                    ? 'text-yellow-600'
                                    : 'text-gray-500'
                                }`}>
                                  {task.priority}
                                </span>
                              )}
                            </div>
                            {task.description && (
                              <p className="text-sm text-[var(--muted)] mb-2 line-clamp-2">
                                {task.description}
                              </p>
                            )}
                            <div className="flex items-center gap-4 text-xs text-[var(--muted)]">
                              {task.due_date && (
                                <span>Due: {new Date(task.due_date).toLocaleDateString()}</span>
                              )}
                              {task.assignee_id && (() => {
                                const users = Array.isArray(usersData?.data) ? usersData.data : [];
                                const assignee = users.find(u => u.id === task.assignee_id);
                                return assignee && (
                                  <span>
                                    Assigned to: {assignee.name || 'Unknown'}
                                  </span>
                                );
                              })()}
                            </div>
                          </div>
                          <div className="flex items-center gap-2 ml-4">
                            <Button
                              variant="tertiary"
                              size="sm"
                              onClick={() => navigate(`/app/tasks/${task.id}/edit`)}
                            >
                              Edit
                            </Button>
                            <Button
                              variant="tertiary"
                              size="sm"
                              onClick={async () => {
                                if (confirm(`Are you sure you want to delete task "${task.title}"?`)) {
                                  try {
                                    await deleteTask.mutateAsync(task.id);
                                    refetchTasks();
                                  } catch (error) {
                                    console.error('Failed to delete task:', error);
                                    alert('Failed to delete task. Please try again.');
                                  }
                                }
                              }}
                              style={{ color: 'var(--color-semantic-danger-600)' }}
                            >
                              Delete
                            </Button>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="text-center py-8 text-[var(--muted)]">
                    <p className="text-sm mb-2">No tasks found for this project</p>
                    <Button
                      variant="secondary"
                      size="sm"
                      onClick={() => navigate(`/app/tasks/create?project_id=${id}`)}
                    >
                      Create First Task
                    </Button>
                  </div>
                );
              })()}
              </CardContent>
            </Card>

            {/* ProjectTasks Checklist (from templates) */}
            <div className="mt-6">
              <ProjectTaskList projectId={id!} />
            </div>
            </div>
          )}

          {/* Documents Tab */}
          {activeTab === 'documents' && (
            <ProjectDocumentsSection
              projectId={id!}
              onUploadClick={() => setShowUploadDocumentModal(true)}
              showUploadButton={true}
            />
          )}

          {/* Cost Tab */}
          {activeTab === 'cost' && (
            <div className="space-y-6">
              <div className="flex items-center justify-between">
                <h2 className="text-xl font-semibold text-[var(--text)]">Cost Dashboard</h2>
                <Button
                  variant="primary"
                  onClick={() => navigate(`/app/projects/${id}/contracts`)}
                >
                  View Contracts
                </Button>
              </div>
              <ProjectCostDashboardSection projectId={id!} />
            </div>
          )}

          {/* Team Tab */}
          {activeTab === 'team' && (
            <>
              <Card>
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <CardTitle>
                      Team Members
                      {(() => {
                        const projectUsers = Array.isArray(project?.users) ? project.users : [];
                        return projectUsers.length > 0 && (
                          <span className="ml-2 text-sm font-normal text-[var(--muted)]">
                            ({projectUsers.length})
                          </span>
                        );
                      })()}
                    </CardTitle>
                    {/* Only show Add Member button if user has tenant.manage_projects permission */}
                    {useAuthStore((s) => (s.currentTenantPermissions ?? []).includes('tenant.manage_projects')) && (
                      <Button 
                        variant="secondary" 
                        size="sm"
                        onClick={() => setShowAddMemberModal(true)}
                        disabled={!Array.isArray(availableUsers) || availableUsers.length === 0}
                      >
                        Add Member
                      </Button>
                    )}
                  </div>
                </CardHeader>
                <CardContent>
                  {(() => {
                    const projectUsers = Array.isArray(project?.users) ? project.users : [];
                    return projectUsers.length > 0 ? (
                      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {projectUsers.map((user) => (
                        <div
                          key={user.id}
                          className="p-4 border border-[var(--border)] rounded-lg hover:bg-[var(--muted-surface)] transition-colors"
                        >
                          <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3 flex-1 min-w-0">
                              <div className="w-10 h-10 rounded-full bg-[var(--primary-button-bg)] flex items-center justify-center text-[var(--primary-button-text)] font-semibold flex-shrink-0">
                                {user.name.charAt(0).toUpperCase()}
                              </div>
                              <div className="flex-1 min-w-0">
                                <h4 className="font-semibold text-[var(--text)] truncate">{user.name}</h4>
                                <p className="text-sm text-[var(--muted)] truncate">{user.email}</p>
                                {user.role && (
                                  <span className="text-xs text-[var(--muted)] capitalize mt-1 inline-block">
                                    {user.role}
                                  </span>
                                )}
                              </div>
                            </div>
                            <Button
                              variant="tertiary"
                              size="sm"
                              onClick={() => handleRemoveTeamMember(user.id)}
                              disabled={removeTeamMember.isPending}
                              style={{ color: 'var(--color-semantic-danger-600)', marginLeft: '8px' }}
                            >
                              Remove
                            </Button>
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <div className="text-center py-8 text-[var(--muted)]">
                      <p className="text-sm mb-2">No team members found</p>
                      <Button 
                        variant="secondary" 
                        size="sm"
                        onClick={() => setShowAddMemberModal(true)}
                        disabled={!Array.isArray(availableUsers) || availableUsers.length === 0}
                      >
                        Add First Member
                      </Button>
                    </div>
                  );
                })()}
                </CardContent>
              </Card>

              {/* Add Member Modal */}
              {showAddMemberModal && (
                <Card style={{ borderColor: 'var(--accent)' }}>
                  <CardHeader>
                    <CardTitle>Add Team Member</CardTitle>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div>
                      <label
                        htmlFor="user-select"
                        className="block text-sm font-medium text-[var(--text)] mb-2"
                      >
                        Select User
                      </label>
                      <select
                        id="user-select"
                        value={selectedUserId}
                        onChange={(e) => setSelectedUserId(e.target.value)}
                        className="w-full px-3 py-2 border border-[var(--border)] rounded-lg focus:ring-2 focus:ring-[var(--accent)] focus:border-[var(--accent)] outline-none"
                        style={{
                          backgroundColor: 'var(--surface)',
                          color: 'var(--text)',
                          height: '40px',
                        }}
                      >
                        <option value="">Select a user...</option>
                        {Array.isArray(availableUsers) && availableUsers.map((user) => (
                          <option key={user.id} value={user.id}>
                            {user.name} ({user.email})
                          </option>
                        ))}
                      </select>
                    </div>
                    <div className="flex items-center justify-end gap-3 pt-4 border-t border-[var(--border)]">
                      <Button
                        type="button"
                        variant="secondary"
                        onClick={() => {
                          setShowAddMemberModal(false);
                          setSelectedUserId('');
                        }}
                        disabled={addTeamMember.isPending}
                      >
                        Cancel
                      </Button>
                      <Button
                        type="button"
                        onClick={handleAddTeamMember}
                        disabled={addTeamMember.isPending || !selectedUserId}
                      >
                        {addTeamMember.isPending ? 'Adding...' : 'Add Member'}
                      </Button>
                    </div>
                  </CardContent>
                </Card>
              )}
            </>
          )}

          {/* Upload Document Modal */}
          {showUploadDocumentModal && (
            <Card style={{ borderColor: 'var(--accent)' }}>
              <CardHeader>
                <CardTitle>Upload Document</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div>
                  <label
                    htmlFor="file-input"
                    className="block text-sm font-medium text-[var(--text)] mb-2"
                  >
                    File <span className="text-red-500">*</span>
                  </label>
                  <input
                    id="file-input"
                    type="file"
                    onChange={handleFileSelect}
                    className="w-full px-3 py-2 border border-[var(--border)] rounded-lg focus:ring-2 focus:ring-[var(--accent)] focus:border-[var(--accent)] outline-none"
                    style={{
                      backgroundColor: 'var(--surface)',
                      color: 'var(--text)',
                    }}
                    accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.gif,.zip,.rar"
                  />
                  {selectedFile && (
                    <p className="mt-2 text-sm text-[var(--muted)]">
                      Selected: {selectedFile.name} ({(selectedFile.size / 1024 / 1024).toFixed(2)} MB)
                    </p>
                  )}
                </div>
                <div>
                  <label
                    htmlFor="document-name"
                    className="block text-sm font-medium text-[var(--text)] mb-2"
                  >
                    Document Name
                  </label>
                  <Input
                    id="document-name"
                    type="text"
                    value={documentName}
                    onChange={(e) => setDocumentName(e.target.value)}
                    placeholder="Enter document name"
                  />
                </div>
                <div>
                  <label
                    htmlFor="document-description"
                    className="block text-sm font-medium text-[var(--text)] mb-2"
                  >
                    Description
                  </label>
                  <textarea
                    id="document-description"
                    value={documentDescription}
                    onChange={(e) => setDocumentDescription(e.target.value)}
                    placeholder="Enter document description (optional)"
                    rows={3}
                    className="w-full px-3 py-2 border border-[var(--border)] rounded-lg focus:ring-2 focus:ring-[var(--accent)] focus:border-[var(--accent)] outline-none resize-none"
                    style={{
                      backgroundColor: 'var(--surface)',
                      color: 'var(--text)',
                    }}
                  />
                </div>
                <div>
                  <label
                    htmlFor="document-category"
                    className="block text-sm font-medium text-[var(--text)] mb-2"
                  >
                    Category
                  </label>
                  <select
                    id="document-category"
                    value={documentCategory}
                    onChange={(e) => setDocumentCategory(e.target.value)}
                    className="w-full px-3 py-2 border border-[var(--border)] rounded-lg focus:ring-2 focus:ring-[var(--accent)] focus:border-[var(--accent)] outline-none"
                    style={{
                      backgroundColor: 'var(--surface)',
                      color: 'var(--text)',
                      height: '40px',
                    }}
                  >
                    <option value="general">General</option>
                    <option value="contract">Contract</option>
                    <option value="drawing">Drawing</option>
                    <option value="specification">Specification</option>
                    <option value="report">Report</option>
                    <option value="other">Other</option>
                  </select>
                </div>
                <div className="flex items-center justify-end gap-3 pt-4 border-t border-[var(--border)]">
                  <Button
                    type="button"
                    variant="secondary"
                    onClick={() => {
                      setShowUploadDocumentModal(false);
                      setSelectedFile(null);
                      setDocumentName('');
                      setDocumentDescription('');
                      setDocumentCategory('general');
                    }}
                    disabled={uploadDocument.isPending}
                  >
                    Cancel
                  </Button>
                  <Button
                    type="button"
                    onClick={handleUploadDocument}
                    disabled={uploadDocument.isPending || !selectedFile}
                  >
                    {uploadDocument.isPending ? 'Uploading...' : 'Upload Document'}
                  </Button>
                </div>
              </CardContent>
            </Card>
          )}

          {/* Activity Tab - Project History */}
          {activeTab === 'activity' && (
            <ProjectHistorySection
              projectId={id!}
              filters={{ limit: 50 }}
            />
          )}
        </div>
      </div>

      {/* Apply Template Modal */}
      {canManageTasks && id && (
        <ApplyTemplateToProjectModal
          projectId={id}
          open={showApplyTemplateModal}
          onOpenChange={setShowApplyTemplateModal}
          onApplied={() => {
            // Refresh tasks and overview
            refetchTasks();
            refetchProject();
          }}
        />
      )}
      </Container>
    </ToastProvider>
  );
};

export default ProjectDetailPage;
