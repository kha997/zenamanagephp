import React from 'react';
import { useProjectCostFlowStatus } from '../hooks';
import { ProjectCostFlowStatusBadge } from './ProjectCostFlowStatusBadge';
import { usePermissions } from '../../../hooks/usePermissions';

interface ProjectCostFlowStatusHeaderProps {
  projectId: string | number;
}

/**
 * ProjectCostFlowStatusHeader Component
 * 
 * Round 232: Project Cost Flow Status
 * 
 * Fetches and displays cost flow status badge in project detail header
 * Only shows if user has projects.cost.view permission
 */
export const ProjectCostFlowStatusHeader: React.FC<ProjectCostFlowStatusHeaderProps> = ({
  projectId,
}) => {
  const { canViewCost } = usePermissions();
  const { data, isLoading, error } = useProjectCostFlowStatus(projectId);

  // Hide if user doesn't have permission
  if (!canViewCost(Number(projectId))) {
    return null;
  }

  if (isLoading || error || !data?.data) {
    return null;
  }

  return (
    <ProjectCostFlowStatusBadge
      status={data.data.status}
      metrics={data.data.metrics}
      showTooltip={true}
    />
  );
};
