import React from 'react';
import { useProjectCostHealth } from '../hooks';
import { CostHealthBadge } from './CostHealthBadge';

interface ProjectCostHealthCellProps {
  projectId: string | number;
  showTooltip?: boolean;
}

/**
 * ProjectCostHealthCell Component
 * 
 * Round 226: Project Cost Health Status + Alert Indicators
 * 
 * Fetches and displays cost health badge for a project
 */
export const ProjectCostHealthCell: React.FC<ProjectCostHealthCellProps> = ({
  projectId,
  showTooltip = false,
}) => {
  const { data, isLoading, error } = useProjectCostHealth(projectId);

  if (isLoading) {
    return <span className="text-xs text-[var(--muted)]">Loading...</span>;
  }

  if (error || !data?.data) {
    return <span className="text-xs text-[var(--muted)]">—</span>;
  }

  return (
    <CostHealthBadge
      status={data.data.cost_health_status}
      showTooltip={showTooltip}
    />
  );
};
