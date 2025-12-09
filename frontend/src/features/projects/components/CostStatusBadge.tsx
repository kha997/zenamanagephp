import React from 'react';
import { Badge } from '../../../shared/ui/badge';

/**
 * CostStatusBadge
 * 
 * Round 230: Workflow/Approval for Change Orders, Payment Certificates, and Payments
 * 
 * Displays status badges for cost entities with appropriate colors
 */
interface CostStatusBadgeProps {
  status: string;
  entityType: 'change_order' | 'certificate' | 'payment';
}

export const CostStatusBadge: React.FC<CostStatusBadgeProps> = ({ status, entityType }) => {
  const getStatusConfig = () => {
    if (entityType === 'change_order') {
      switch (status) {
        case 'draft':
          return { tone: 'neutral' as const, label: 'Draft' };
        case 'proposed':
          return { tone: 'info' as const, label: 'Proposed' };
        case 'approved':
          return { tone: 'success' as const, label: 'Approved' };
        case 'rejected':
          return { tone: 'danger' as const, label: 'Rejected' };
        default:
          return { tone: 'neutral' as const, label: status };
      }
    } else if (entityType === 'certificate') {
      switch (status) {
        case 'draft':
          return { tone: 'neutral' as const, label: 'Draft' };
        case 'submitted':
          return { tone: 'info' as const, label: 'Submitted' };
        case 'approved':
          return { tone: 'success' as const, label: 'Approved' };
        default:
          return { tone: 'neutral' as const, label: status };
      }
    } else if (entityType === 'payment') {
      switch (status) {
        case 'planned':
          return { tone: 'neutral' as const, label: 'Planned' };
        case 'paid':
          return { tone: 'success' as const, label: 'Paid' };
        default:
          return { tone: 'neutral' as const, label: status };
      }
    }
    return { tone: 'neutral' as const, label: status };
  };

  const config = getStatusConfig();

  return <Badge tone={config.tone}>{config.label}</Badge>;
};
