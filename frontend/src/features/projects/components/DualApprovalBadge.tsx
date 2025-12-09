import React from 'react';
import { Badge } from '../../../shared/ui/badge';

/**
 * DualApprovalBadge Component
 * 
 * Round 242: FE/UX for Cost Dual Approval
 * 
 * Displays a badge when an entity requires dual approval and is awaiting second approval.
 */
export interface DualApprovalBadgeProps {
  requiresDualApproval?: boolean | null;
  secondApprovedBy?: string | null;
}

export const DualApprovalBadge: React.FC<DualApprovalBadgeProps> = ({
  requiresDualApproval,
  secondApprovedBy,
}) => {
  // Show badge if dual approval is required and second approval hasn't been done
  if (requiresDualApproval && !secondApprovedBy) {
    return (
      <Badge tone="warning" className="ml-2">
        Awaiting second approval
      </Badge>
    );
  }

  return null;
};
