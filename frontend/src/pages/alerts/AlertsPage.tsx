import React, { useState, useMemo, useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import toast from 'react-hot-toast';
import { Card, CardContent, CardHeader, CardTitle } from '../../shared/ui/card';
import { Button } from '../../shared/ui/button';
import { Badge } from '../../shared/ui/badge';
import { AlertSkeleton } from '../../shared/ui/skeleton';
import { useDashboardAlerts, useMarkAlertAsRead, useMarkAllAlertsAsRead } from '../../entities/dashboard/hooks';
import { useQueryClient } from '@tanstack/react-query';
import { dashboardKeys } from '../../entities/dashboard/hooks';
import { useI18n } from '../../app/i18n-context';
import type { AlertSeverity } from '../../entities/dashboard/types';

type AlertFilter = 'all' | 'unread' | 'read' | 'critical' | 'high' | 'medium' | 'low';

const AlertsPage: React.FC = () => {
  const { t } = useI18n();
  const [searchParams, setSearchParams] = useSearchParams();
  const [filter, setFilter] = useState<AlertFilter>('all');
  const [selectedAlerts, setSelectedAlerts] = useState<Set<string>>(new Set());
  const queryClient = useQueryClient();
  
  const { data: alerts, isLoading, error } = useDashboardAlerts();
  const markAlertAsReadMutation = useMarkAlertAsRead();
  const markAllAlertsAsReadMutation = useMarkAllAlertsAsRead();

  // Sync filter with URL params
  useEffect(() => {
    const urlFilter = searchParams.get('filter') as AlertFilter;
    if (urlFilter && ['all', 'unread', 'read', 'critical', 'high', 'medium', 'low'].includes(urlFilter)) {
      setFilter(urlFilter);
    }
  }, [searchParams]);

  const handleFilterChange = (newFilter: AlertFilter) => {
    setFilter(newFilter);
    setSearchParams({ filter: newFilter });
  };

  const handleRefresh = () => {
    // Invalidate alerts query to trigger refetch
    queryClient.invalidateQueries({ queryKey: dashboardKeys.alerts() });
    toast.success(t('alerts.refreshSuccess', { defaultValue: 'Alerts refreshed successfully' }));
  };

  const filteredAlerts = useMemo(() => {
    if (!alerts?.data) return [];
    
    return alerts.data.filter((alert) => {
      switch (filter) {
        case 'unread':
          return alert.status === 'unread';
        case 'read':
          return alert.status === 'read';
        case 'critical':
          return alert.severity === 'critical';
        case 'high':
          return alert.severity === 'high';
        case 'medium':
          return alert.severity === 'medium';
        case 'low':
          return alert.severity === 'low';
        default:
          return true;
      }
    });
  }, [alerts?.data, filter]);

  const handleMarkAsRead = async (alertId: string) => {
    try {
      await markAlertAsReadMutation.mutateAsync(alertId);
      toast.success(t('alerts.markReadSuccess', { defaultValue: 'Alert marked as read' }));
    } catch (error) {
      console.error('Failed to mark alert as read:', error);
      toast.error(t('alerts.markReadError', { defaultValue: 'Failed to mark alert as read' }));
    }
  };

  const handleMarkAllAsRead = async () => {
    try {
      await markAllAlertsAsReadMutation.mutateAsync();
      toast.success(t('alerts.markAllReadSuccess', { defaultValue: 'All alerts marked as read' }));
    } catch (error) {
      console.error('Failed to mark all alerts as read:', error);
      toast.error(t('alerts.markAllReadError', { defaultValue: 'Failed to mark all alerts as read' }));
    }
  };

  const handleSelectAlert = (alertId: string) => {
    const newSelected = new Set(selectedAlerts);
    if (newSelected.has(alertId)) {
      newSelected.delete(alertId);
    } else {
      newSelected.add(alertId);
    }
    setSelectedAlerts(newSelected);
  };

  const handleSelectAll = () => {
    if (selectedAlerts.size === filteredAlerts.length) {
      setSelectedAlerts(new Set());
    } else {
      setSelectedAlerts(new Set(filteredAlerts.map(alert => alert.id)));
    }
  };

  const handleBulkMarkAsRead = async () => {
    try {
      const promises = Array.from(selectedAlerts).map(alertId => 
        markAlertAsReadMutation.mutateAsync(alertId)
      );
      await Promise.all(promises);
      setSelectedAlerts(new Set());
      toast.success(t('alerts.bulkMarkReadSuccess', { defaultValue: 'Selected alerts marked as read' }));
    } catch (error) {
      console.error('Failed to bulk mark alerts as read:', error);
      toast.error(t('alerts.bulkMarkReadError', { defaultValue: 'Failed to mark selected alerts as read' }));
    }
  };

  const getSeverityColor = (severity: AlertSeverity) => {
    switch (severity) {
      case 'critical':
        return 'danger';
      case 'high':
        return 'warning';
      case 'medium':
        return 'info';
      case 'low':
        return 'neutral';
      default:
        return 'neutral';
    }
  };

  const getSeverityIcon = (severity: AlertSeverity) => {
    switch (severity) {
      case 'critical':
        return '🚨';
      case 'high':
        return '⚠️';
      case 'medium':
        return 'ℹ️';
      case 'low':
        return '📝';
      default:
        return '📄';
    }
  };

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">
              {t('alerts.title', { defaultValue: 'Alerts Center' })}
            </h1>
            <p className="text-[var(--color-text-muted)]">
              {t('alerts.description', { defaultValue: 'Manage and monitor system alerts' })}
            </p>
          </div>
        </div>
        <AlertSkeleton />
      </div>
    );
  }

  if (error) {
    return (
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">
              {t('alerts.title', { defaultValue: 'Alerts Center' })}
            </h1>
            <p className="text-[var(--color-text-muted)]">
              {t('alerts.description', { defaultValue: 'Manage and monitor system alerts' })}
            </p>
          </div>
        </div>
        <Card>
          <CardContent className="p-6">
            <div className="text-center text-[var(--color-text-muted)]">
              <p className="text-lg font-medium mb-2">
                {t('alerts.errorTitle', { defaultValue: 'Failed to load alerts' })}
              </p>
              <p className="text-sm mb-4">
                {t('alerts.errorDescription', { defaultValue: 'Please try refreshing the page' })}
              </p>
              <Button variant="outline" size="sm">
                {t('alerts.retry', { defaultValue: 'Retry' })}
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  const alertsData = alerts?.data || [];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">
            {t('alerts.title', { defaultValue: 'Alerts Center' })}
          </h1>
          <p className="text-[var(--color-text-muted)]">
            {t('alerts.description', { defaultValue: 'Manage and monitor system alerts' })}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button 
            variant="outline" 
            size="sm"
            onClick={handleMarkAllAsRead}
            disabled={markAllAlertsAsReadMutation.isPending || alertsData.length === 0}
          >
            {t('alerts.markAllRead', { defaultValue: 'Mark All Read' })}
          </Button>
          <Button variant="outline" size="sm" onClick={handleRefresh}>
            {t('alerts.refresh', { defaultValue: 'Refresh' })}
          </Button>
        </div>
      </div>

      {/* Filters */}
      <Card>
        <CardHeader>
          <CardTitle className="text-lg">{t('alerts.filters', { defaultValue: 'Filters' })}</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="flex flex-wrap gap-2">
            {[
              { key: 'all', label: t('alerts.filterAll', { defaultValue: 'All' }) },
              { key: 'unread', label: t('alerts.filterUnread', { defaultValue: 'Unread' }) },
              { key: 'read', label: t('alerts.filterRead', { defaultValue: 'Read' }) },
              { key: 'critical', label: t('alerts.filterCritical', { defaultValue: 'Critical' }) },
              { key: 'high', label: t('alerts.filterHigh', { defaultValue: 'High' }) },
              { key: 'medium', label: t('alerts.filterMedium', { defaultValue: 'Medium' }) },
              { key: 'low', label: t('alerts.filterLow', { defaultValue: 'Low' }) },
            ].map(({ key, label }) => (
              <Button
                key={key}
                variant={filter === key ? 'primary' : 'outline'}
                size="sm"
                onClick={() => handleFilterChange(key as AlertFilter)}
              >
                {label}
              </Button>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Bulk Actions */}
      {selectedAlerts.size > 0 && (
        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <span className="text-sm text-[var(--color-text-muted)]">
                {t('alerts.selectedCount', { defaultValue: 'Selected' })}: {selectedAlerts.size}
              </span>
              <div className="flex items-center gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={handleBulkMarkAsRead}
                  disabled={markAlertAsReadMutation.isPending}
                >
                  {t('alerts.bulkMarkRead', { defaultValue: 'Mark as Read' })}
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setSelectedAlerts(new Set())}
                >
                  {t('alerts.clearSelection', { defaultValue: 'Clear Selection' })}
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Alerts List */}
      <div className="space-y-4">
        {filteredAlerts.length === 0 ? (
          <Card>
            <CardContent className="p-8">
              <div className="text-center">
                <div className="text-4xl mb-4">📭</div>
                <h3 className="text-lg font-semibold text-[var(--color-text-primary)] mb-2">
                  {t('alerts.noAlerts', { defaultValue: 'No alerts found' })}
                </h3>
                <p className="text-[var(--color-text-muted)]">
                  {filter === 'all' 
                    ? t('alerts.noAlertsDescription', { defaultValue: 'No alerts at this time' })
                    : t('alerts.noFilteredAlerts', { defaultValue: 'No alerts match the current filter' })
                  }
                </p>
              </div>
            </CardContent>
          </Card>
        ) : (
          <>
            {/* Select All */}
            <div className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={selectedAlerts.size === filteredAlerts.length && filteredAlerts.length > 0}
                onChange={handleSelectAll}
                className="rounded border-[var(--color-border-default)]"
              />
              <span className="text-sm text-[var(--color-text-muted)]">
                {t('alerts.selectAll', { defaultValue: 'Select all' })}
              </span>
            </div>

            {/* Alerts */}
            {filteredAlerts.map((alert) => (
              <Card key={alert.id} className={`transition-all duration-200 ${
                alert.status === 'unread' ? 'border-l-4 border-l-[var(--color-semantic-primary-500)]' : ''
              }`}>
                <CardContent className="p-4">
                  <div className="flex items-start gap-4">
                    <input
                      type="checkbox"
                      checked={selectedAlerts.has(alert.id)}
                      onChange={() => handleSelectAlert(alert.id)}
                      className="mt-1 rounded border-[var(--color-border-default)]"
                    />
                    
                    <div className="flex-1 min-w-0">
                      <div className="flex items-start justify-between gap-2">
                        <div className="flex items-center gap-2">
                          <span className="text-lg">{getSeverityIcon(alert.severity)}</span>
                          <h3 className="text-sm font-semibold text-[var(--color-text-primary)] truncate">
                            {alert.title}
                          </h3>
                          <Badge tone={getSeverityColor(alert.severity)}>
                            {alert.severity}
                          </Badge>
                        </div>
                        <div className="flex items-center gap-2">
                          {alert.status === 'unread' && (
                            <Badge tone="primary" className="text-xs">
                              {t('alerts.unread', { defaultValue: 'Unread' })}
                            </Badge>
                          )}
                          <span className="text-xs text-[var(--color-text-muted)]">
                            {new Date(alert.createdAt).toLocaleDateString()}
                          </span>
                        </div>
                      </div>
                      
                      <p className="text-sm text-[var(--color-text-secondary)] mt-2">
                        {alert.message}
                      </p>
                      
                      <div className="flex items-center justify-between mt-3">
                        <div className="flex items-center gap-2 text-xs text-[var(--color-text-muted)]">
                          <span>{t('alerts.source', { defaultValue: 'Source' })}: {alert.source}</span>
                          <span>•</span>
                          <span>{t('alerts.type', { defaultValue: 'Type' })}: {alert.type}</span>
                        </div>
                        
                        <div className="flex items-center gap-2">
                          {alert.status === 'unread' && (
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => handleMarkAsRead(alert.id)}
                              disabled={markAlertAsReadMutation.isPending}
                            >
                              {t('alerts.markRead', { defaultValue: 'Mark as Read' })}
                            </Button>
                          )}
                        </div>
                      </div>
                    </div>
                  </div>
                </CardContent>
              </Card>
            ))}
          </>
        )}
      </div>
    </div>
  );
};

export default AlertsPage;
