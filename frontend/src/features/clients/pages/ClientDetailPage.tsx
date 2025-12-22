import React, { useState, useCallback, useMemo } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { KpiStrip } from '../../../components/shared/KpiStrip';
import { AlertBar } from '../../../components/shared/AlertBar';
import { ActivityFeed } from '../../../components/shared/ActivityFeed';
import { useClient, useDeleteClient, useClientsActivity, useClientsAlerts } from '../hooks';
import { useProjects } from '../../projects/hooks';
import { useQuotes } from '../../quotes/hooks';
import type { KpiItem } from '../../../components/shared/KpiStrip';
import type { Alert } from '../../../components/shared/AlertBar';
import type { Activity } from '../../../components/shared/ActivityFeed';

type TabId = 'overview' | 'projects' | 'quotes' | 'activity';

interface Tab {
  id: TabId;
  label: string;
  icon?: string;
}

const tabs: Tab[] = [
  { id: 'overview', label: 'Overview', icon: '📊' },
  { id: 'projects', label: 'Projects', icon: '📁' },
  { id: 'quotes', label: 'Quotes', icon: '💰' },
  { id: 'activity', label: 'Activity', icon: '📝' },
];

export const ClientDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [activeTab, setActiveTab] = useState<TabId>('overview');
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
  
  const { data: clientData, isLoading, error } = useClient(id!);
  const deleteClient = useDeleteClient();
  
  // Get projects for this client
  const { data: projectsData, isLoading: projectsLoading } = useProjects(
    { client_id: id } as any,
    { page: 1, per_page: 50 }
  );
  
  // Get quotes for this client
  const { data: quotesData, isLoading: quotesLoading } = useQuotes(
    { client_id: id } as any,
    { page: 1, per_page: 50 }
  );
  
  // Get activity and alerts
  const { data: activityData, isLoading: activityLoading } = useClientsActivity(20);
  const { data: alertsData, isLoading: alertsLoading } = useClientsAlerts();
  
  const client = clientData?.data;

  const handleEdit = useCallback(() => {
    if (id) {
      navigate(`/app/clients/${id}/edit`);
    }
  }, [navigate, id]);

  const handleDelete = useCallback(async () => {
    if (!id) return;
    
    try {
      await deleteClient.mutateAsync(id);
      navigate('/app/clients');
    } catch (error) {
      console.error('Failed to delete client:', error);
      alert('Failed to delete client. Please try again.');
    } finally {
      setShowDeleteConfirm(false);
    }
  }, [id, deleteClient, navigate]);

  if (isLoading) {
    return (
      <Container>
        <div className="animate-pulse">
          <div className="h-8 bg-[var(--muted-surface)] rounded w-1/3 mb-4"></div>
        </div>
      </Container>
    );
  }

  if (error || !client) {
    return (
      <Container>
        <Card>
          <CardContent className="py-8">
            <div className="text-center">
              <p className="text-[var(--muted)] mb-4">
                {error ? `Error: ${(error as Error).message}` : 'Client not found'}
              </p>
              <Button variant="secondary" onClick={() => navigate('/app/clients')}>
                Back to Clients
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
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div className="flex-1">
            <h1 className="text-[var(--font-heading-3-size)] font-semibold text-[var(--text)] mb-2">
              {client.name}
            </h1>
            {client.status && (
              <span className={`text-xs px-2 py-1 rounded capitalize ${
                client.status === 'active' ? 'bg-green-100 text-green-700' :
                client.status === 'inactive' ? 'bg-gray-100 text-gray-700' :
                'bg-yellow-100 text-yellow-700'
              }`}>
                {client.status}
              </span>
            )}
          </div>
          
          <div className="flex items-center gap-2">
            <Button variant="secondary" onClick={handleEdit}>
              Edit
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

        {showDeleteConfirm && (
          <Card style={{ borderColor: 'var(--color-semantic-danger-200)' }}>
            <CardContent className="py-6">
              <h3 className="text-lg font-semibold text-[var(--text)] mb-2">
                Delete Client?
              </h3>
              <p className="text-sm text-[var(--muted)] mb-4">
                Are you sure you want to delete "{client.name}"? This action cannot be undone.
              </p>
              <div className="flex items-center gap-3">
                <Button
                  variant="secondary"
                  onClick={() => setShowDeleteConfirm(false)}
                  disabled={deleteClient.isPending}
                >
                  Cancel
                </Button>
                <Button
                  onClick={handleDelete}
                  disabled={deleteClient.isPending}
                  style={{ backgroundColor: 'var(--color-semantic-danger-600)' }}
                >
                  {deleteClient.isPending ? 'Deleting...' : 'Delete'}
                </Button>
              </div>
            </CardContent>
          </Card>
        )}

        {/* KPI Strip */}
        <KpiStrip
          kpis={useMemo(() => [
            {
              label: 'Projects',
              value: projectsData?.data?.length || 0,
              variant: 'default',
            },
            {
              label: 'Quotes',
              value: quotesData?.data?.length || 0,
              variant: 'info',
            },
            {
              label: 'Status',
              value: client.status || 'N/A',
              variant: client.status === 'active' ? 'success' : 'default',
            },
          ], [projectsData, quotesData, client.status])}
          loading={false}
        />

        {/* Alert Bar */}
        <AlertBar
          alerts={useMemo(() => {
            if (!alertsData?.data) return [];
            return Array.isArray(alertsData.data)
              ? alertsData.data.map((alert: any) => ({
                  id: alert.id,
                  message: alert.message || alert.title || 'Alert',
                  type: alert.type || alert.severity || 'info',
                  priority: alert.priority || 0,
                  created_at: alert.created_at || alert.createdAt,
                }))
              : [];
          }, [alertsData])}
          loading={alertsLoading}
          onDismiss={(id) => console.log('Dismiss alert:', id)}
          onDismissAll={() => console.log('Dismiss all alerts')}
        />

        {/* Tabs */}
        <Card>
          <CardHeader>
            <div className="flex items-center gap-4 border-b border-[var(--border)]">
              {tabs.map((tab) => (
                <button
                  key={tab.id}
                  onClick={() => setActiveTab(tab.id)}
                  className={`px-4 py-2 text-sm font-medium transition-colors ${
                    activeTab === tab.id
                      ? 'text-[var(--text)] border-b-2 border-[var(--primary)]'
                      : 'text-[var(--muted)] hover:text-[var(--text)]'
                  }`}
                >
                  {tab.icon && <span className="mr-2">{tab.icon}</span>}
                  {tab.label}
                </button>
              ))}
            </div>
          </CardHeader>
          <CardContent className="pt-6">
            {activeTab === 'overview' && (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {client.email && (
                  <div>
                    <label className="text-sm font-medium text-[var(--muted)]">Email</label>
                    <p className="text-[var(--text)] mt-1">{client.email}</p>
                  </div>
                )}
                {client.phone && (
                  <div>
                    <label className="text-sm font-medium text-[var(--muted)]">Phone</label>
                    <p className="text-[var(--text)] mt-1">{client.phone}</p>
                  </div>
                )}
                <div>
                  <label className="text-sm font-medium text-[var(--muted)]">Created</label>
                  <p className="text-[var(--text)] mt-1">
                    {new Date(client.created_at).toLocaleDateString()}
                  </p>
                </div>
                <div>
                  <label className="text-sm font-medium text-[var(--muted)]">Last Updated</label>
                  <p className="text-[var(--text)] mt-1">
                    {new Date(client.updated_at).toLocaleDateString()}
                  </p>
                </div>
              </div>
            )}

            {activeTab === 'projects' && (
              <div>
                {projectsLoading ? (
                  <div className="text-center py-8 text-[var(--muted)]">Loading projects...</div>
                ) : projectsData?.data && projectsData.data.length > 0 ? (
                  <div className="space-y-4">
                    {projectsData.data.map((project: any) => (
                      <div
                        key={project.id}
                        className="p-4 border border-[var(--border)] rounded-lg hover:bg-[var(--muted-surface)] transition-colors cursor-pointer"
                        onClick={() => navigate(`/app/projects/${project.id}`)}
                      >
                        <h3 className="font-medium text-[var(--text)]">{project.name}</h3>
                        <p className="text-sm text-[var(--muted)] mt-1">{project.status}</p>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="text-center py-8 text-[var(--muted)]">No projects found</div>
                )}
              </div>
            )}

            {activeTab === 'quotes' && (
              <div>
                {quotesLoading ? (
                  <div className="text-center py-8 text-[var(--muted)]">Loading quotes...</div>
                ) : quotesData?.data && quotesData.data.length > 0 ? (
                  <div className="space-y-4">
                    {quotesData.data.map((quote: any) => (
                      <div
                        key={quote.id}
                        className="p-4 border border-[var(--border)] rounded-lg hover:bg-[var(--muted-surface)] transition-colors cursor-pointer"
                        onClick={() => navigate(`/app/quotes/${quote.id}`)}
                      >
                        <h3 className="font-medium text-[var(--text)]">{quote.title}</h3>
                        <div className="flex items-center gap-4 mt-2">
                          <span className="text-sm text-[var(--muted)]">{quote.status}</span>
                          {quote.amount && (
                            <span className="text-sm font-medium text-[var(--text)]">
                              ${quote.amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                            </span>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="text-center py-8 text-[var(--muted)]">No quotes found</div>
                )}
              </div>
            )}

            {activeTab === 'activity' && (
              <ActivityFeed
                activities={useMemo(() => {
                  if (!activityData?.data) return [];
                  return Array.isArray(activityData.data)
                    ? activityData.data
                        .filter((activity: any) => activity.client_id === id || activity.metadata?.client_id === id)
                        .map((activity: any) => ({
                          id: activity.id,
                          type: activity.type || 'client',
                          action: activity.action,
                          description: activity.description || activity.message || 'Activity',
                          timestamp: activity.timestamp || activity.created_at || activity.createdAt,
                          user: activity.user,
                          metadata: activity.metadata,
                        }))
                    : [];
                }, [activityData, id])}
                loading={activityLoading}
              />
            )}
          </CardContent>
        </Card>
      </div>
    </Container>
  );
};

export default ClientDetailPage;

