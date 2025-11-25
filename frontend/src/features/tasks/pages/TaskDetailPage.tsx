import React, { useState, useCallback, useMemo } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { ActivityFeed } from '../../../components/shared/ActivityFeed';
import { TaskComments } from '../components/TaskComments';
import { TaskAttachments } from '../components/TaskAttachments';
import { useTask, useDeleteTask, useTasksActivity, useTaskDocuments, useTaskHistory } from '../hooks';
import { useAuthStore } from '../../auth/store';
import type { Activity } from '../../../components/shared/ActivityFeed';

type TabId = 'overview' | 'comments' | 'attachments' | 'documents' | 'history' | 'activity';

interface Tab {
  id: TabId;
  label: string;
  icon?: string;
}

const tabs: Tab[] = [
  { id: 'overview', label: 'Overview', icon: '📊' },
  { id: 'comments', label: 'Comments', icon: '💬' },
  { id: 'attachments', label: 'Attachments', icon: '📎' },
  { id: 'documents', label: 'Documents', icon: '📄' },
  { id: 'history', label: 'History', icon: '🕐' },
  { id: 'activity', label: 'Activity', icon: '📝' },
];

export const TaskDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user, hasTenantPermission } = useAuthStore();
  const [activeTab, setActiveTab] = useState<TabId>('overview');
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
  
  const { data: taskData, isLoading, error } = useTask(id!);
  const { data: activityData, isLoading: activityLoading, error: activityError } = useTasksActivity(20);
  const { data: documentsData, isLoading: documentsLoading, error: documentsError } = useTaskDocuments(id!);
  const { data: historyData, isLoading: historyLoading, error: historyError } = useTaskHistory(id!);
  const deleteTask = useDeleteTask();
  
  const task = taskData?.data;

  const handleEdit = useCallback(() => {
    if (id) {
      navigate(`/app/tasks/${id}/edit`);
    }
  }, [navigate, id]);

  const handleDelete = useCallback(async () => {
    if (!id) return;
    
    try {
      await deleteTask.mutateAsync(id);
      // Redirect to project if task has project_id, otherwise to tasks list
      if (task?.project_id) {
        navigate(`/app/projects/${task.project_id}`);
      } else {
        navigate('/app/tasks');
      }
    } catch (error) {
      console.error('Failed to delete task:', error);
      alert('Failed to delete task. Please try again.');
    } finally {
      setShowDeleteConfirm(false);
    }
  }, [id, deleteTask, navigate, task]);

  // Transform activity data
  const activities: Activity[] = useMemo(() => {
    if (!activityData?.data) return [];
    return Array.isArray(activityData.data)
      ? activityData.data
          .filter((activity: any) => activity.task_id === id || activity.metadata?.task_id === id)
          .map((activity: any) => ({
            id: activity.id,
            type: activity.type || 'task',
            action: activity.action,
            description: activity.description || activity.message || 'Activity',
            timestamp: activity.timestamp || activity.created_at || activity.createdAt,
            user: activity.user,
            metadata: activity.metadata,
          }))
      : [];
  }, [activityData, id]);

  const getStatusBadgeClass = (status: string) => {
    switch (status) {
      case 'completed':
        return 'bg-green-100 text-green-700';
      case 'in_progress':
        return 'bg-blue-100 text-blue-700';
      case 'pending':
        return 'bg-yellow-100 text-yellow-700';
      case 'cancelled':
        return 'bg-gray-100 text-gray-500';
      default:
        return 'bg-gray-100 text-gray-500';
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
                Error loading task: {(error as Error).message}
              </p>
              <Button variant="secondary" onClick={() => navigate('/app/tasks')}>
                Back to Tasks
              </Button>
            </div>
          </CardContent>
        </Card>
      </Container>
    );
  }

  if (!task) {
    return (
      <Container>
        <Card>
          <CardContent className="py-8">
            <div className="text-center">
              <p className="text-[var(--muted)] mb-4">Task not found</p>
              <Button variant="secondary" onClick={() => navigate('/app/tasks')}>
                Back to Tasks
              </Button>
            </div>
          </CardContent>
        </Card>
      </Container>
    );
  }

  return (
    <Container>
      <div className="space-y-6">
        {/* Page Header */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div className="flex-1">
            <div className="flex items-center gap-3 mb-2">
              <h1 className="text-[var(--font-heading-3-size)] font-semibold text-[var(--text)]">
                {task.title}
              </h1>
              <span className={`text-xs px-2 py-1 rounded ${getStatusBadgeClass(task.status)}`}>
                {task.status.replace('_', ' ')}
              </span>
              {task.priority && (
                <span className={`text-xs capitalize ${getPriorityBadgeClass(task.priority)}`}>
                  {task.priority}
                </span>
              )}
            </div>
            {task.description && (
              <p className="text-[var(--font-body-size)] text-[var(--muted)]">
                {task.description}
              </p>
            )}
          </div>
          
          {/* Quick Actions */}
          <div className="flex items-center gap-2">
            {(hasTenantPermission('tenant.manage_tasks') || hasTenantPermission('tenant.update_own_tasks')) && (
              <Button variant="secondary" onClick={handleEdit}>
                Edit
              </Button>
            )}
            {hasTenantPermission('tenant.manage_tasks') && (
              <Button
                variant="secondary"
                onClick={() => setShowDeleteConfirm(true)}
                style={{ color: 'var(--color-semantic-danger-600)' }}
              >
                Delete
              </Button>
            )}
          </div>
        </div>

        {/* Delete Confirmation Modal */}
        {showDeleteConfirm && (
          <Card style={{ borderColor: 'var(--color-semantic-danger-200)' }}>
            <CardContent className="py-6">
              <h3 className="text-lg font-semibold text-[var(--text)] mb-2">
                Delete Task?
              </h3>
              <p className="text-sm text-[var(--muted)] mb-4">
                Are you sure you want to delete "{task.title}"? This action cannot be undone.
              </p>
              <div className="flex items-center gap-3">
                <Button
                  variant="secondary"
                  onClick={() => setShowDeleteConfirm(false)}
                  disabled={deleteTask.isPending}
                >
                  Cancel
                </Button>
                <Button
                  onClick={handleDelete}
                  disabled={deleteTask.isPending}
                  style={{ backgroundColor: 'var(--color-semantic-danger-600)' }}
                >
                  {deleteTask.isPending ? 'Deleting...' : 'Delete'}
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
            <div className="space-y-6">
              {/* Task Info Card */}
              <Card>
                <CardHeader>
                  <CardTitle>Task Information</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                      <label className="text-sm font-medium text-[var(--muted)]">Status</label>
                      <p className="text-[var(--text)] mt-1 capitalize">{task.status.replace('_', ' ')}</p>
                    </div>
                    {task.priority && (
                      <div>
                        <label className="text-sm font-medium text-[var(--muted)]">Priority</label>
                        <p className={`text-[var(--text)] mt-1 capitalize ${getPriorityBadgeClass(task.priority)}`}>
                          {task.priority}
                        </p>
                      </div>
                    )}
                    {task.due_date && (
                      <div>
                        <label className="text-sm font-medium text-[var(--muted)]">Due Date</label>
                        <p className="text-[var(--text)] mt-1">
                          {new Date(task.due_date).toLocaleDateString()}
                        </p>
                      </div>
                    )}
                    {task.project_id && (
                      <div>
                        <label className="text-sm font-medium text-[var(--muted)]">Project</label>
                        <p className="text-[var(--text)] mt-1">
                          <button
                            onClick={() => navigate(`/app/projects/${task.project_id}`)}
                            className="text-[var(--accent)] hover:underline"
                          >
                            View Project
                          </button>
                        </p>
                      </div>
                    )}
                    <div>
                      <label className="text-sm font-medium text-[var(--muted)]">Created</label>
                      <p className="text-[var(--text)] mt-1">
                        {new Date(task.created_at).toLocaleDateString()}
                      </p>
                    </div>
                    <div>
                      <label className="text-sm font-medium text-[var(--muted)]">Last Updated</label>
                      <p className="text-[var(--text)] mt-1">
                        {new Date(task.updated_at).toLocaleDateString()}
                      </p>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </div>
          )}

          {/* Comments Tab */}
          {activeTab === 'comments' && (
            <TaskComments taskId={id!} currentUserId={user?.id} />
          )}

          {/* Attachments Tab */}
          {activeTab === 'attachments' && (
            <TaskAttachments taskId={id!} currentUserId={user?.id} />
          )}

          {/* Documents Tab */}
          {activeTab === 'documents' && (
            <Card>
              <CardHeader>
                <CardTitle>Task Documents</CardTitle>
              </CardHeader>
              <CardContent>
                {documentsLoading ? (
                  <div className="text-center py-8">
                    <p className="text-[var(--muted)]">Loading documents...</p>
                  </div>
                ) : documentsError ? (
                  <div className="text-center py-8">
                    <p className="text-[var(--color-semantic-danger-600)]">
                      Error loading documents: {(documentsError as Error).message}
                    </p>
                  </div>
                ) : !documentsData?.data || documentsData.data.length === 0 ? (
                  <div className="text-center py-8">
                    <p className="text-[var(--muted)]">No documents found for this task.</p>
                  </div>
                ) : (
                  <div className="space-y-4">
                    {documentsData.data.map((doc: any) => (
                      <div key={doc.id} className="flex items-center justify-between p-4 border border-[var(--border)] rounded-lg">
                        <div className="flex items-center gap-3">
                          <span className="text-2xl">📄</span>
                          <div>
                            <p className="font-medium text-[var(--text)]">{doc.name || doc.filename || 'Document'}</p>
                            {doc.description && (
                              <p className="text-sm text-[var(--muted)]">{doc.description}</p>
                            )}
                            {doc.created_at && (
                              <p className="text-xs text-[var(--muted)]">
                                Uploaded {new Date(doc.created_at).toLocaleDateString()}
                              </p>
                            )}
                          </div>
                        </div>
                        {doc.url && (
                          <a
                            href={doc.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-[var(--accent)] hover:underline"
                          >
                            View
                          </a>
                        )}
                      </div>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>
          )}

          {/* History Tab */}
          {activeTab === 'history' && (
            <Card>
              <CardHeader>
                <CardTitle>Task History</CardTitle>
              </CardHeader>
              <CardContent>
                {historyLoading ? (
                  <div className="text-center py-8">
                    <p className="text-[var(--muted)]">Loading history...</p>
                  </div>
                ) : historyError ? (
                  <div className="text-center py-8">
                    <p className="text-[var(--color-semantic-danger-600)]">
                      Error loading history: {(historyError as Error).message}
                    </p>
                  </div>
                ) : !historyData?.data || historyData.data.length === 0 ? (
                  <div className="text-center py-8">
                    <p className="text-[var(--muted)]">No history available for this task.</p>
                  </div>
                ) : (
                  <div className="space-y-4">
                    {historyData.data.map((entry: any, index: number) => (
                      <div key={entry.id || index} className="flex gap-4 p-4 border-l-2 border-[var(--border)]">
                        <div className="flex-shrink-0">
                          <div className="w-2 h-2 rounded-full bg-[var(--accent)] mt-2"></div>
                        </div>
                        <div className="flex-1">
                          <p className="text-[var(--text)]">{entry.description || entry.action || 'History entry'}</p>
                          {entry.user && (
                            <p className="text-sm text-[var(--muted)] mt-1">
                              by {entry.user.name || entry.user.email || 'Unknown'}
                            </p>
                          )}
                          {entry.created_at && (
                            <p className="text-xs text-[var(--muted)] mt-1">
                              {new Date(entry.created_at).toLocaleString()}
                            </p>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>
          )}

          {/* Activity Tab */}
          {activeTab === 'activity' && (
            <ActivityFeed
              activities={activities}
              loading={activityLoading}
              error={activityError as Error | null}
              title="Task Activity"
              limit={20}
            />
          )}
        </div>
      </div>
    </Container>
  );
};

export default TaskDetailPage;
