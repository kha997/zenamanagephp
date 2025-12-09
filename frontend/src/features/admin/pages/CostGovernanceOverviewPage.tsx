import React from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { LoadingSpinner } from '../../../components/shared/LoadingSpinner';
import { useCostGovernanceOverview } from '../hooks';
// Helper function to format currency
const formatCurrency = (amount: number | null): string => {
  if (amount === null || amount === undefined) {
    return 'N/A';
  }
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
  }).format(amount);
};

/**
 * CostGovernanceOverviewPage - Cost governance overview dashboard
 * Round 243: Admin Cost Governance Dashboard / Overview
 */
export const CostGovernanceOverviewPage: React.FC = () => {
  const { data, isLoading, error } = useCostGovernanceOverview();
  const navigate = useNavigate();

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <LoadingSpinner />
      </div>
    );
  }

  if (error) {
    return (
      <div className="p-6">
        <Card>
          <CardContent className="p-6">
            <p className="text-red-600">Error loading cost governance overview: {(error as Error).message}</p>
          </CardContent>
        </Card>
      </div>
    );
  }

  if (!data) {
    return (
      <div className="p-6">
        <Card>
          <CardContent className="p-6">
            <p>No data available</p>
          </CardContent>
        </Card>
      </div>
    );
  }

  const { summary, top_projects_by_risk, recent_policy_events } = data;

  const handleProjectClick = (projectId: string) => {
    // TODO: Navigate to project detail / cost dashboard when route is confirmed
    console.log('Navigate to project:', projectId);
    // navigate(`/app/projects/${projectId}/cost`);
  };

  const handleEntityClick = (type: string, entityId: string) => {
    // TODO: Navigate to entity detail when routes are confirmed
    console.log('Navigate to entity:', type, entityId);
  };

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">Cost Governance Overview</h1>
        <p className="text-[var(--color-text-secondary)] mt-1">
          Monitor change orders, certificates, payments, and policy compliance
        </p>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        {/* Change Orders Card */}
        <Card>
          <CardHeader>
            <CardTitle>Change Orders</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-2">
              <div className="flex justify-between">
                <span className="text-[var(--color-text-secondary)]">Total</span>
                <span className="font-semibold">{summary.change_orders.total}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-[var(--color-text-secondary)]">Pending Approval</span>
                <span className="font-semibold text-yellow-600">{summary.change_orders.pending_approval}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-[var(--color-text-secondary)]">Awaiting Dual Approval</span>
                <span className="font-semibold text-orange-600">{summary.change_orders.awaiting_dual_approval}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-[var(--color-text-secondary)]">Blocked by Policy</span>
                <span className="font-semibold text-red-600">{summary.change_orders.blocked_by_policy}</span>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Certificates Card */}
        <Card>
          <CardHeader>
            <CardTitle>Certificates</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-2">
              <div className="flex justify-between">
                <span className="text-[var(--color-text-secondary)]">Total</span>
                <span className="font-semibold">{summary.certificates.total}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-[var(--color-text-secondary)]">Pending Approval</span>
                <span className="font-semibold text-yellow-600">{summary.certificates.pending_approval}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-[var(--color-text-secondary)]">Awaiting Dual Approval</span>
                <span className="font-semibold text-orange-600">{summary.certificates.awaiting_dual_approval}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-[var(--color-text-secondary)]">Blocked by Policy</span>
                <span className="font-semibold text-red-600">{summary.certificates.blocked_by_policy}</span>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Payments Card */}
        <Card>
          <CardHeader>
            <CardTitle>Payments</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-2">
              <div className="flex justify-between">
                <span className="text-[var(--color-text-secondary)]">Total</span>
                <span className="font-semibold">{summary.payments.total}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-[var(--color-text-secondary)]">Pending Approval</span>
                <span className="font-semibold text-yellow-600">{summary.payments.pending_approval}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-[var(--color-text-secondary)]">Awaiting Dual Approval</span>
                <span className="font-semibold text-orange-600">{summary.payments.awaiting_dual_approval}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-[var(--color-text-secondary)]">Blocked by Policy</span>
                <span className="font-semibold text-red-600">{summary.payments.blocked_by_policy}</span>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Top Projects by Risk */}
      <Card>
        <CardHeader>
          <CardTitle>Top Projects by Cost Risk</CardTitle>
        </CardHeader>
        <CardContent>
          {top_projects_by_risk.length === 0 ? (
            <p className="text-[var(--color-text-secondary)]">No projects with risk indicators</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full border-collapse">
                <thead>
                  <tr className="border-b border-[var(--color-border-primary)]">
                    <th className="text-left p-3 text-[var(--color-text-secondary)] font-semibold">Project</th>
                    <th className="text-right p-3 text-[var(--color-text-secondary)] font-semibold">Pending CO</th>
                    <th className="text-right p-3 text-[var(--color-text-secondary)] font-semibold">Pending Certificates</th>
                    <th className="text-right p-3 text-[var(--color-text-secondary)] font-semibold">Pending Payments</th>
                    <th className="text-right p-3 text-[var(--color-text-secondary)] font-semibold">Awaiting Dual Approval</th>
                    <th className="text-right p-3 text-[var(--color-text-secondary)] font-semibold">Policy-Blocked Items</th>
                    <th className="text-right p-3 text-[var(--color-text-secondary)] font-semibold">Over Budget (%)</th>
                  </tr>
                </thead>
                <tbody>
                  {top_projects_by_risk.map((project) => (
                    <tr
                      key={project.project_id}
                      className="border-b border-[var(--color-border-primary)] hover:bg-[var(--color-surface-muted)] cursor-pointer"
                      onClick={() => handleProjectClick(project.project_id)}
                    >
                      <td className="p-3">
                        <span className="font-medium text-[var(--color-text-primary)]">{project.project_name}</span>
                      </td>
                      <td className="p-3 text-right">{project.pending_co}</td>
                      <td className="p-3 text-right">{project.pending_certificates}</td>
                      <td className="p-3 text-right">{project.pending_payments}</td>
                      <td className="p-3 text-right">
                        <span className={project.awaiting_dual_approval > 0 ? 'text-orange-600 font-semibold' : ''}>
                          {project.awaiting_dual_approval}
                        </span>
                      </td>
                      <td className="p-3 text-right">
                        <span className={project.policy_blocked_items > 0 ? 'text-red-600 font-semibold' : ''}>
                          {project.policy_blocked_items}
                        </span>
                      </td>
                      <td className="p-3 text-right">
                        {project.over_budget_percent !== null ? (
                          <span className={project.over_budget_percent > 0 ? 'text-red-600 font-semibold' : 'text-green-600'}>
                            {project.over_budget_percent.toFixed(2)}%
                          </span>
                        ) : (
                          <span className="text-[var(--color-text-secondary)]">N/A</span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Recent Policy Events */}
      <Card>
        <CardHeader>
          <CardTitle>Recent Policy Events</CardTitle>
        </CardHeader>
        <CardContent>
          {recent_policy_events.length === 0 ? (
            <p className="text-[var(--color-text-secondary)]">No recent policy events</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full border-collapse">
                <thead>
                  <tr className="border-b border-[var(--color-border-primary)]">
                    <th className="text-left p-3 text-[var(--color-text-secondary)] font-semibold">Time</th>
                    <th className="text-left p-3 text-[var(--color-text-secondary)] font-semibold">Type</th>
                    <th className="text-left p-3 text-[var(--color-text-secondary)] font-semibold">Project</th>
                    <th className="text-right p-3 text-[var(--color-text-secondary)] font-semibold">Amount vs Threshold</th>
                    <th className="text-left p-3 text-[var(--color-text-secondary)] font-semibold">Code</th>
                    <th className="text-left p-3 text-[var(--color-text-secondary)] font-semibold">Action</th>
                  </tr>
                </thead>
                <tbody>
                  {recent_policy_events.map((event, index) => (
                    <tr
                      key={`${event.entity_id}-${index}`}
                      className="border-b border-[var(--color-border-primary)] hover:bg-[var(--color-surface-muted)]"
                    >
                      <td className="p-3 text-[var(--color-text-secondary)]">
                        {new Date(event.created_at).toLocaleString()}
                      </td>
                      <td className="p-3">
                        <span className="px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800">
                          {event.type.toUpperCase()}
                        </span>
                      </td>
                      <td className="p-3">
                        <span className="text-[var(--color-text-primary)]">{event.project_name || 'N/A'}</span>
                      </td>
                      <td className="p-3 text-right">
                        {event.amount !== null && event.threshold !== null ? (
                          <span className="text-[var(--color-text-primary)]">
                            {formatCurrency(event.amount)} / {formatCurrency(event.threshold)}
                          </span>
                        ) : (
                          <span className="text-[var(--color-text-secondary)]">N/A</span>
                        )}
                      </td>
                      <td className="p-3">
                        <span className="text-xs font-mono text-[var(--color-text-secondary)]">{event.code}</span>
                      </td>
                      <td className="p-3">
                        <button
                          onClick={() => handleEntityClick(event.type, event.entity_id)}
                          className="text-blue-600 hover:text-blue-800 text-sm"
                        >
                          View
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
};
