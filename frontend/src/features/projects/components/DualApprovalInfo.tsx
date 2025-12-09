import React from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';

/**
 * DualApprovalInfo Component
 * 
 * Round 242: FE/UX for Cost Dual Approval
 * 
 * Displays detailed information about first and second approvals.
 */
export interface DualApprovalInfoProps {
  requiresDualApproval?: boolean | null;
  firstApprovedBy?: string | null;
  firstApprovedAt?: string | null;
  secondApprovedBy?: string | null;
  secondApprovedAt?: string | null;
  firstApproverName?: string | null;
  secondApproverName?: string | null;
}

export const DualApprovalInfo: React.FC<DualApprovalInfoProps> = ({
  requiresDualApproval,
  firstApprovedBy,
  firstApprovedAt,
  secondApprovedBy,
  secondApprovedAt,
  firstApproverName,
  secondApproverName,
}) => {
  // Only show if dual approval is required or if there are any approvals
  if (!requiresDualApproval && !firstApprovedBy && !secondApprovedBy) {
    return null;
  }

  const formatDate = (dateString: string | null | undefined) => {
    if (!dateString) return null;
    try {
      const date = new Date(dateString);
      return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    } catch {
      return dateString;
    }
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>Approvals</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="space-y-4">
          {/* First Approval */}
          <div>
            <label className="text-sm font-medium text-[var(--muted)] mb-1 block">
              First Approval
            </label>
            {firstApprovedBy ? (
              <div className="text-[var(--text)]">
                <p className="font-medium">
                  {firstApproverName || `User ID: ${firstApprovedBy}`}
                </p>
                {firstApprovedAt && (
                  <p className="text-sm text-[var(--muted)] mt-1">
                    {formatDate(firstApprovedAt)}
                  </p>
                )}
              </div>
            ) : (
              <p className="text-[var(--muted)]">Not yet approved</p>
            )}
          </div>

          {/* Second Approval */}
          {requiresDualApproval && (
            <div>
              <label className="text-sm font-medium text-[var(--muted)] mb-1 block">
                Second Approval
              </label>
              {secondApprovedBy ? (
                <div className="text-[var(--text)]">
                  <p className="font-medium">
                    {secondApproverName || `User ID: ${secondApprovedBy}`}
                  </p>
                  {secondApprovedAt && (
                    <p className="text-sm text-[var(--muted)] mt-1">
                      {formatDate(secondApprovedAt)}
                    </p>
                  )}
                </div>
              ) : (
                <p className="text-[var(--muted)]">
                  {firstApprovedBy ? 'Waiting for second approver' : 'Not yet approved'}
                </p>
              )}
            </div>
          )}

          {/* Policy Hint */}
          {requiresDualApproval && (
            <div className="mt-4 pt-4 border-t border-[var(--border)]">
              <p className="text-sm text-[var(--muted)] italic">
                This transaction exceeds the cost approval policy threshold and requires two approvers.
              </p>
            </div>
          )}
        </div>
      </CardContent>
    </Card>
  );
};
