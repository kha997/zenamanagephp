import React from 'react';
import { Badge, BadgeTone } from '../../../shared/ui/badge';

export type CostHealthStatus = 'UNDER_BUDGET' | 'ON_BUDGET' | 'AT_RISK' | 'OVER_BUDGET';

interface CostHealthBadgeProps {
  status: CostHealthStatus;
  showTooltip?: boolean;
  className?: string;
}

/**
 * CostHealthBadge Component
 * 
 * Round 226: Project Cost Health Status + Alert Indicators
 * 
 * Displays a color-coded badge indicating project cost health status:
 * - GREEN (success) → UNDER_BUDGET
 * - BLUE (info) → ON_BUDGET
 * - ORANGE (warning) → AT_RISK
 * - RED (danger) → OVER_BUDGET
 */
export const CostHealthBadge: React.FC<CostHealthBadgeProps> = ({
  status,
  showTooltip = false,
  className,
}) => {
  const getTone = (): BadgeTone => {
    switch (status) {
      case 'UNDER_BUDGET':
        return 'success';
      case 'ON_BUDGET':
        return 'info';
      case 'AT_RISK':
        return 'warning';
      case 'OVER_BUDGET':
        return 'danger';
      default:
        return 'neutral';
    }
  };

  const getLabel = (): string => {
    switch (status) {
      case 'UNDER_BUDGET':
        return 'Under Budget';
      case 'ON_BUDGET':
        return 'On Budget';
      case 'AT_RISK':
        return 'At Risk';
      case 'OVER_BUDGET':
        return 'Over Budget';
      default:
        return 'Unknown';
    }
  };

  const getTooltipText = (): string => {
    switch (status) {
      case 'UNDER_BUDGET':
        return 'Project is under budget with more than 5% variance buffer';
      case 'ON_BUDGET':
        return 'Project is on budget with no pending change orders';
      case 'AT_RISK':
        return 'Project has pending change orders and less than 5% budget buffer';
      case 'OVER_BUDGET':
        return 'Project forecast exceeds budget';
      default:
        return 'Unknown cost health status';
    }
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
