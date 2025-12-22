import React from 'react';
import { Badge, BadgeTone } from '../../../shared/ui/badge';
import type { ProjectCostFlowStatusResponse } from '../api';

export type CostFlowStatus = 'OK' | 'PENDING_APPROVAL' | 'DELAYED' | 'BLOCKED';

interface ProjectCostFlowStatusBadgeProps {
  status: CostFlowStatus;
  metrics?: ProjectCostFlowStatusResponse['metrics'];
  showTooltip?: boolean;
  className?: string;
}

/**
 * ProjectCostFlowStatusBadge Component
 * 
 * Round 232: Project Cost Flow Status
 * 
 * Displays a color-coded badge indicating project cost flow approval status:
 * - GREEN (success) → OK: All approvals resolved
 * - BLUE (info) → PENDING_APPROVAL: Pending approvals within threshold
 * - ORANGE (warning) → DELAYED: Pending approvals > threshold days
 * - RED (danger) → BLOCKED: Any rejected items
 */
export const ProjectCostFlowStatusBadge: React.FC<ProjectCostFlowStatusBadgeProps> = ({
  status,
  metrics,
  showTooltip = false,
  className,
}) => {
  const getTone = (): BadgeTone => {
    switch (status) {
      case 'OK':
        return 'success';
      case 'PENDING_APPROVAL':
        return 'info';
      case 'DELAYED':
        return 'warning';
      case 'BLOCKED':
        return 'danger';
      default:
        return 'neutral';
    }
  };

  const getLabel = (): string => {
    switch (status) {
      case 'OK':
        return 'Flow OK';
      case 'PENDING_APPROVAL':
        return 'Pending Approval';
      case 'DELAYED':
        return 'Delayed';
      case 'BLOCKED':
        return 'Blocked';
      default:
        return 'Unknown';
    }
  };

  const getTooltipText = (): string => {
    if (!metrics) {
      return getLabel();
    }

    const parts: string[] = [];
    
    if (metrics.rejected_change_orders > 0 || metrics.rejected_certificates > 0) {
      parts.push(`Rejected: ${metrics.rejected_change_orders} CO, ${metrics.rejected_certificates} Cert`);
    }
    
    if (metrics.delayed_change_orders > 0 || metrics.delayed_certificates > 0) {
      parts.push(`Delayed: ${metrics.delayed_change_orders} CO, ${metrics.delayed_certificates} Cert`);
    }
    
    if (metrics.pending_change_orders > 0 || metrics.pending_certificates > 0) {
      parts.push(`Pending: ${metrics.pending_change_orders} CO, ${metrics.pending_certificates} Cert`);
    }

    if (parts.length === 0) {
      return 'All cost approvals are resolved';
    }

    return parts.join(' | ');
  };

  const badge = (
    <Badge tone={getTone()} className={className}>
      {getLabel()}
    </Badge>
  );

  if (showTooltip) {
    return (
      <span title={getTooltipText()}>
        {badge}
      </span>
    );
  }

  return badge;
};
