import React, { useState, useMemo, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { KpiStrip } from '../../../components/shared/KpiStrip';
import { AlertBar } from '../../../components/shared/AlertBar';
import { ActivityFeed } from '../../../components/shared/ActivityFeed';
import { Button } from '../../../components/ui/primitives/Button';
import { Input } from '../../../components/ui/primitives/Input';
import { useChangeRequests, useChangeRequestsKpis, useChangeRequestsActivity, useChangeRequestsAlerts } from '../hooks';
import type { KpiItem } from '../../../components/shared/KpiStrip';
import type { Alert } from '../../../components/shared/AlertBar';
import type { Activity } from '../../../components/shared/ActivityFeed';
import type { ChangeRequestFilters } from '../api';

export const ChangeRequestsListPage: React.FC = () => {
  const navigate = useNavigate();
  const [filters, setFilters] = useState<ChangeRequestFilters>({});
  const [searchInput, setSearchInput] = useState('');
  const [page, setPage] = useState(1);
  const [perPage] = useState(12);
  
  // Fetch data
  const { data: changeRequestsData, isLoading, error } = useChangeRequests(filters, { page, per_page: perPage });
  const { data: kpisData, isLoading: kpisLoading } = useChangeRequestsKpis();
  const { data: activityData, isLoading: activityLoading } = useChangeRequestsActivity(10);
  const { data: alertsData, isLoading: alertsLoading } = useChangeRequestsAlerts();
  
  const changeRequests = changeRequestsData?.data || [];
  const meta = changeRequestsData?.meta;
  
  // Debounce search input
  React.useEffect(() => {
    const timer = setTimeout(() => {
      setFilters(prev => ({ ...prev, search: searchInput || undefined, page: 1 }));
      setPage(1);
    }, 300);
    return () => clearTimeout(timer);
  }, [searchInput]);
  
  // Transform KPI data to KpiItem format
  const kpiItems: KpiItem[] = useMemo(() => {
    if (!kpisData?.data) return [];
    const kpis = kpisData.data;
    return [
      {
        label: 'Total',
        value: kpis.total || changeRequests.length || 0,
        variant: 'default',
      },
      {
        label: 'Pending',
        value: kpis.pending || changeRequests.filter(cr => cr.status === 'awaiting_approval').length || 0,
        variant: 'warning',
      },
      {
        label: 'Approved',
        value: kpis.approved || changeRequests.filter(cr => cr.status === 'approved').length || 0,
        variant: 'success',
      },
      {
        label: 'Rejected',
        value: kpis.rejected || changeRequests.filter(cr => cr.status === 'rejected').length || 0,
        variant: 'danger',
      },
    ];
  }, [kpisData, changeRequests]);
  
  // Transform alerts data to Alert format
  const alerts: Alert[] = useMemo(() => {
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
  }, [alertsData]);
  
  // Transform activity data to Activity format
  const activities: Activity[] = useMemo(() => {
    if (!activityData?.data) return [];
    return Array.isArray(activityData.data)
      ? activityData.data.map((activity: any) => ({
          id: activity.id,
          type: activity.type || 'change_request',
          action: activity.action,
          description: activity.description || activity.message || 'Activity',
          timestamp: activity.timestamp || activity.created_at || activity.createdAt,
          user: activity.user,
          metadata: activity.metadata,
        }))
      : [];
  }, [activityData]);
  
  const handleDismissAlert = useCallback((id: string | number) => {
    console.log('Dismiss alert:', id);
  }, []);
  
  const handleDismissAllAlerts = useCallback(() => {
    console.log('Dismiss all alerts');
  }, []);
  
  const getStatusBadgeClass = (status: string) => {
    switch (status) {
      case 'approved':
        return 'bg-green-100 text-green-700';
      case 'awaiting_approval':
        return 'bg-yellow-100 text-yellow-700';
      case 'rejected':
        return 'bg-red-100 text-red-700';
      case 'draft':
        return 'bg-gray-100 text-gray-700';
      default:
        return 'bg-gray-100 text-gray-700';
    }
  };
  
  return (
    <Container>
      <div className="space-y-6">
        {/* Page Title */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-[var(--font-heading-3-size)] font-semibold text-[var(--text)]">
              Change Requests
            </h1>
            <p className="text-sm text-[var(--muted)] mt-1">
              Manage project change requests
            </p>
          </div>
          <Button onClick={() => navigate('/app/change-requests/create')}>
            Create Change Request
          </Button>
        </div>
        
        {/* KPI Strip */}
        <KpiStrip
          kpis={kpiItems}
          loading={kpisLoading}
        />
        
        {/* Alert Bar */}
        <AlertBar
          alerts={alerts}
          loading={alertsLoading}
          onDismiss={handleDismissAlert}
          onDismissAll={handleDismissAllAlerts}
        />
        
        {/* Main Content */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Documents List */}
          <div className="lg:col-span-2">
            <Card>
              <CardHeader>
                <div className="flex items-center justify-between">
                  <CardTitle>All Change Requests</CardTitle>
                  <Input
                    type="text"
                    placeholder="Search..."
                    value={searchInput}
                    onChange={(e) => setSearchInput(e.target.value)}
                    className="w-64"
                  />
                </div>
              </CardHeader>
              <CardContent>
                {isLoading ? (
                  <div className="space-y-4">
                    {[1, 2, 3].map((i) => (
                      <div key={i} className="animate-pulse">
                        <div className="h-4 bg-[var(--muted-surface)] rounded w-3/4 mb-2"></div>
                        <div className="h-3 bg-[var(--muted-surface)] rounded w-1/2"></div>
                      </div>
                    ))}
                  </div>
                ) : error ? (
                  <div className="text-center text-[var(--muted)] py-4">
                    <p className="text-sm">Error loading change requests: {(error as Error).message}</p>
                  </div>
                ) : changeRequests.length > 0 ? (
                  <div className="space-y-4">
                    {changeRequests.map((cr) => (
                      <div
                        key={cr.id}
                        className="p-4 border border-[var(--border)] rounded-lg hover:bg-[var(--muted-surface)] transition-colors cursor-pointer"
                        onClick={() => navigate(`/app/change-requests/${cr.id}`)}
                      >
                        <div className="flex items-start justify-between">
                          <div className="flex-1">
                            <div className="flex items-center gap-2 mb-1">
                              <h3 className="font-medium text-[var(--text)]">{cr.title}</h3>
                              <span className={`text-xs px-2 py-1 rounded capitalize ${getStatusBadgeClass(cr.status)}`}>
                                {cr.status.replace('_', ' ')}
                              </span>
                            </div>
                            {cr.description && (
                              <p className="text-sm text-[var(--muted)] line-clamp-2">{cr.description}</p>
                            )}
                            <div className="flex items-center gap-4 mt-2 text-xs text-[var(--muted)]">
                              {cr.change_number && <span>#{cr.change_number}</span>}
                              {cr.priority && <span className="capitalize">{cr.priority}</span>}
                              <span>{new Date(cr.created_at).toLocaleDateString()}</span>
                            </div>
                          </div>
                        </div>
                      </div>
                    ))}
                    
                    {/* Pagination */}
                    {meta && meta.last_page > 1 && (
                      <div className="flex items-center justify-between mt-4">
                        <p className="text-sm text-[var(--muted)]">
                          Showing {((page - 1) * perPage) + 1} to {Math.min(page * perPage, meta.total)} of {meta.total}
                        </p>
                        <div className="flex items-center gap-2">
                          <Button
                            variant="secondary"
                            onClick={() => setPage(p => Math.max(1, p - 1))}
                            disabled={page === 1}
                          >
                            Previous
                          </Button>
                          <Button
                            variant="secondary"
                            onClick={() => setPage(p => Math.min(meta.last_page, p + 1))}
                            disabled={page === meta.last_page}
                          >
                            Next
                          </Button>
                        </div>
                      </div>
                    )}
                  </div>
                ) : (
                  <div className="text-center py-8">
                    <p className="text-[var(--muted)] mb-4">No change requests found</p>
                    <Button onClick={() => navigate('/app/change-requests/create')}>
                      Create First Change Request
                    </Button>
                  </div>
                )}
              </CardContent>
            </Card>
          </div>
          
          {/* Activity Feed */}
          <div>
            <Card>
              <CardHeader>
                <CardTitle>Recent Activity</CardTitle>
              </CardHeader>
              <CardContent>
                <ActivityFeed activities={activities} loading={activityLoading} />
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </Container>
  );
};

export default ChangeRequestsListPage;

