import React from 'react';
import { useProjectCostAlerts } from '../hooks';

interface ProjectCostAlertsIconProps {
  projectId: string | number;
  className?: string;
}

/**
 * ProjectCostAlertsIcon Component
 * 
 * Round 227: Cost Alerts System (Nagging & Attention Flags)
 * 
 * Displays a small alert icon if the project has cost alerts.
 * Shows nothing if no alerts exist.
 */
export const ProjectCostAlertsIcon: React.FC<ProjectCostAlertsIconProps> = ({
  projectId,
  className = '',
}) => {
  const { data, isLoading, error } = useProjectCostAlerts(projectId);

  // Don't show anything if loading, error, or no data
  if (isLoading || error || !data?.data) {
    return null;
  }

  const alerts = data.data.alerts;

  // If no alerts, don't render anything
  if (!alerts || alerts.length === 0) {
    return null;
  }

  return (
    <span
      title="This project has cost alerts"
      className={`inline-flex items-center justify-center ${className}`}
      role="img"
      aria-label="Cost alerts"
    >
      ⚠️
    </span>
  );
};
