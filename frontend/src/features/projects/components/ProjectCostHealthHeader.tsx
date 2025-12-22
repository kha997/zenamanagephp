import React from 'react';
import { useProjectCostHealth } from '../hooks';
import { CostHealthBadge } from './CostHealthBadge';

interface ProjectCostHealthHeaderProps {
  projectId: string | number;
}

/**
 * ProjectCostHealthHeader Component
 * 
 * Round 226: Project Cost Health Status + Alert Indicators
 * 
 * Fetches and displays cost health badge in project detail header
 */
export const ProjectCostHealthHeader: React.FC<ProjectCostHealthHeaderProps> = ({
  projectId,
}) => {
  const { data, isLoading, error } = useProjectCostHealth(projectId);

  if (isLoading || error || !data?.data) {
    return null;
  }

  return (
    <CostHealthBadge
      status={data.data.cost_health_status}
      showTooltip={true}
    />
  );
};
