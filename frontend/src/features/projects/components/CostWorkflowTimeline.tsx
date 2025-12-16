import React from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';

/**
 * Round 231: Cost Workflow Timeline Component
 * 
 * Displays a vertical timeline of workflow actions for cost entities
 * (Change Orders, Payment Certificates, Payments)
 */

export interface CostWorkflowTimelineItem {
  id: string;
  timestamp: string;
  action: string;
  action_label?: string;
  userName?: string;
  userEmail?: string;
  metadata?: Record<string, any>;
  description?: string;
}

export interface CostWorkflowTimelineProps {
  items: CostWorkflowTimelineItem[];
  compact?: boolean;
  title?: string;
}

/**
 * Map action codes to human-readable labels
 */
const getActionLabel = (action: string, actionLabel?: string): string => {
  if (actionLabel) return actionLabel;
  
  const actionMap: Record<string, string> = {
    'change_order_proposed': 'Change Order Proposed',
    'change_order_approved': 'Change Order Approved',
    'change_order_rejected': 'Change Order Rejected',
    'certificate_submitted': 'Certificate Submitted',
    'certificate_approved': 'Certificate Approved',
    'payment_marked_paid': 'Payment Marked Paid',
  };
  
  return actionMap[action] || action.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

/**
 * Get action color/tone for visual distinction
 */
const getActionTone = (action: string): 'success' | 'warning' | 'danger' | 'neutral' => {
  if (action.includes('approved') || action.includes('paid')) return 'success';
  if (action.includes('rejected')) return 'danger';
  if (action.includes('proposed') || action.includes('submitted')) return 'warning';
  return 'neutral';
};

/**
 * Format timestamp to readable date/time
 */
const formatTimestamp = (timestamp: string): string => {
  try {
    const date = new Date(timestamp);
    return date.toLocaleString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch {
    return timestamp;
  }
};

/**
 * Extract metadata summary for display
 */
const getMetadataSummary = (metadata?: Record<string, any>): string | null => {
  if (!metadata || typeof metadata !== 'object') return null;
  
  const parts: string[] = [];
  
  // Status changes
  if (metadata.status_before && metadata.status_after) {
    parts.push(`Status: ${metadata.status_before} → ${metadata.status_after}`);
  }
  
  // Amount changes
  if (metadata.amount !== undefined || metadata.amount_delta !== undefined) {
    const amount = metadata.amount_delta ?? metadata.amount;
    if (typeof amount === 'number') {
      const sign = amount > 0 ? '+' : '';
      parts.push(`Amount: ${sign}${amount.toLocaleString()}`);
    }
  }
  
  // Reason (for rejections)
  if (metadata.reason) {
    parts.push(`Reason: ${metadata.reason}`);
  }
  
  return parts.length > 0 ? parts.join(' | ') : null;
};

export const CostWorkflowTimeline: React.FC<CostWorkflowTimelineProps> = ({
  items,
  compact = false,
  title = 'Workflow Timeline',
}) => {
  if (items.length === 0) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>{title}</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="py-8 text-center text-[var(--muted)]">
            <p>No workflow history yet.</p>
          </div>
        </CardContent>
      </Card>
    );
  }

  // Sort items by timestamp (newest first, then reverse for display oldest to newest)
  const sortedItems = [...items].sort((a, b) => {
    const dateA = new Date(a.timestamp).getTime();
    const dateB = new Date(b.timestamp).getTime();
    return dateB - dateA; // Newest first
  }).reverse(); // Reverse to show oldest to newest (chronological)

  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
      </CardHeader>
      <CardContent>
        <div className={`space-y-${compact ? '2' : '4'}`}>
          {sortedItems.map((item, index) => {
            const isLast = index === sortedItems.length - 1;
            const tone = getActionTone(item.action);
            const actionLabel = getActionLabel(item.action, item.action_label);
            const metadataSummary = getMetadataSummary(item.metadata);
            
            return (
              <div key={item.id} className="relative flex gap-4">
                {/* Timeline line */}
                {!isLast && (
                  <div className="absolute left-[11px] top-8 bottom-0 w-0.5 bg-[var(--border)]" />
                )}
                
                {/* Timeline dot */}
                <div className="relative z-10 flex-shrink-0">
                  <div
                    className={`w-6 h-6 rounded-full border-2 flex items-center justify-center ${
                      tone === 'success'
                        ? 'bg-[var(--color-semantic-success-50)] border-[var(--color-semantic-success-600)]'
                        : tone === 'danger'
                        ? 'bg-[var(--color-semantic-danger-50)] border-[var(--color-semantic-danger-600)]'
                        : tone === 'warning'
                        ? 'bg-[var(--color-semantic-warning-50)] border-[var(--color-semantic-warning-600)]'
                        : 'bg-[var(--muted-surface)] border-[var(--muted)]'
                    }`}
                  >
                    <div
                      className={`w-2 h-2 rounded-full ${
                        tone === 'success'
                          ? 'bg-[var(--color-semantic-success-600)]'
                          : tone === 'danger'
                          ? 'bg-[var(--color-semantic-danger-600)]'
                          : tone === 'warning'
                          ? 'bg-[var(--color-semantic-warning-600)]'
                          : 'bg-[var(--muted)]'
                      }`}
                    />
                  </div>
                </div>
                
                {/* Content */}
                <div className="flex-1 pb-4">
                  <div className="flex items-start justify-between gap-4">
                    <div className="flex-1">
                      <p className="font-medium text-[var(--text)]">{actionLabel}</p>
                      {item.userName && (
                        <p className="text-sm text-[var(--muted)] mt-1">
                          by {item.userName}
                          {item.userEmail && ` (${item.userEmail})`}
                        </p>
                      )}
                      {item.description && (
                        <p className="text-sm text-[var(--muted)] mt-1">{item.description}</p>
                      )}
                      {metadataSummary && (
                        <p className="text-xs text-[var(--muted)] mt-1 font-mono bg-[var(--muted-surface)] px-2 py-1 rounded inline-block">
                          {metadataSummary}
                        </p>
                      )}
                    </div>
                    <div className="text-xs text-[var(--muted)] whitespace-nowrap">
                      {formatTimestamp(item.timestamp)}
                    </div>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </CardContent>
    </Card>
  );
};
