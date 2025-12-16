import React from 'react';
import { useProjectCostFlowStatus } from '../hooks';
import { ProjectCostFlowStatusBadge } from './ProjectCostFlowStatusBadge';
import { usePermissions } from '../../../hooks/usePermissions';

interface ProjectCostFlowStatusCellProps {
  projectId: string | number;
  showTooltip?: boolean;
}

/**
 * ProjectCostFlowStatusCell Component
 * 
 * Round 232: Project Cost Flow Status
 * 
 * Fetches and displays cost flow status badge for a project
 * Only shows if user has projects.cost.view permission
 */
export const ProjectCostFlowStatusCell: React.FC<ProjectCostFlowStatusCellProps> = ({
  projectId,
  showTooltip = false,
}) => {
  const { canViewCost } = usePermissions();
  const { data, isLoading, error } = useProjectCostFlowStatus(projectId);

  // Hide if user doesn't have permission
  if (!canViewCost(Number(projectId))) {
    return null;
  }

  if (isLoading) {
    return <span className="text-xs text-[var(--muted)]">Loading...</span>;
  }

  if (error || !data?.data) {
    return <span className="text-xs text-[var(--muted)]">—</span>;
  }

  return (
    <ProjectCostFlowStatusBadge
      status={data.data.status}
      metrics={data.data.metrics}
      showTooltip={showTooltip}
    />
  );
};
