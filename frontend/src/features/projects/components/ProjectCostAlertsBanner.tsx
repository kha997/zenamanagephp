import React from 'react';
import { useProjectCostAlerts } from '../hooks';

interface ProjectCostAlertsBannerProps {
  projectId: string | number;
  className?: string;
}

/**
 * ProjectCostAlertsBanner Component
 * 
 * Round 227: Cost Alerts System (Nagging & Attention Flags)
 * 
 * Displays prominent alerts for cost-related issues:
 * - Pending Change Orders Overdue
 * - Approved Certificates but Unpaid
 * - Cost Health Warning
 * - High Pending CO Financial Impact
 * 
 * Shows nothing if no alerts exist.
 */
export const ProjectCostAlertsBanner: React.FC<ProjectCostAlertsBannerProps> = ({
  projectId,
  className = '',
}) => {
  const { data, isLoading, error } = useProjectCostAlerts(projectId);

  // Don't show anything if loading, error, or no data
  if (isLoading || error || !data?.data) {
    return null;
  }

  const alerts = data.data.alerts;
  const details = data.data.details;

  // If no alerts, don't render anything
  if (!alerts || alerts.length === 0) {
    return null;
  }

  // Alert messages mapping
  const getAlertMessage = (alertType: string): string => {
    switch (alertType) {
      case 'pending_change_orders_overdue':
        return `⚠️ ${details.overdue_co_count} pending change order${details.overdue_co_count !== 1 ? 's' : ''} overdue (older than ${details.threshold_days} days)`;
      case 'approved_certificates_unpaid':
        return `💰 ${details.unpaid_certificates_count} approved certificate${details.unpaid_certificates_count !== 1 ? 's' : ''} unpaid (older than ${details.threshold_days} days)`;
      case 'cost_health_warning':
        return `🚨 Cost health: ${details.cost_health_status === 'AT_RISK' ? 'At Risk' : 'Over Budget'}`;
      case 'pending_co_high_impact':
        return `📊 High pending CO impact: ${typeof details.pending_change_orders_total === 'string' 
          ? parseFloat(details.pending_change_orders_total).toLocaleString() 
          : details.pending_change_orders_total.toLocaleString()} (${((parseFloat(String(details.pending_change_orders_total)) / parseFloat(String(details.budget_total))) * 100).toFixed(1)}% of budget)`;
      default:
        return '';
    }
  };

  return (
    <div
      className={`rounded-lg border-l-4 border-[var(--color-semantic-warning-600)] bg-[var(--color-semantic-warning-50)] p-4 ${className}`}
      role="alert"
      aria-live="polite"
    >
      <div className="space-y-2">
        <div className="flex items-center gap-2">
          <span className="text-lg font-semibold text-[var(--color-semantic-warning-900)]">
            Cost Alerts
          </span>
        </div>
        <div className="space-y-1.5">
          {alerts.map((alertType, index) => {
            const message = getAlertMessage(alertType);
            if (!message) return null;
            
            return (
              <div
                key={index}
                className="text-sm text-[var(--color-semantic-warning-800)]"
              >
                {message}
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
};
