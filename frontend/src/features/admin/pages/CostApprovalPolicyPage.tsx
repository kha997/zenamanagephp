import React, { useState, useEffect } from 'react';
import toast from 'react-hot-toast';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../shared/ui/button';
import { Input } from '../../../shared/ui/input';
import { Label } from '../../../shared/ui/label';
import {
  useCostApprovalPolicy,
  useUpdateCostApprovalPolicy,
} from '../hooks';
import { LoadingSpinner } from '../../../components/shared/LoadingSpinner';

/**
 * CostApprovalPolicyPage - Cost approval policy management page
 * Round 239: Cost Approval Policies (Phase 1 - Thresholds & Blocking)
 */
export const CostApprovalPolicyPage: React.FC = () => {
  const { data: policy, isLoading, error } = useCostApprovalPolicy();
  const updatePolicyMutation = useUpdateCostApprovalPolicy();

  const [formData, setFormData] = useState({
    co_dual_threshold_amount: '',
    certificate_dual_threshold_amount: '',
    payment_dual_threshold_amount: '',
    over_budget_threshold_percent: '',
  });

  // Initialize form data when policy loads
  useEffect(() => {
    if (policy) {
      setFormData({
        co_dual_threshold_amount: policy.co_dual_threshold_amount?.toString() || '',
        certificate_dual_threshold_amount: policy.certificate_dual_threshold_amount?.toString() || '',
        payment_dual_threshold_amount: policy.payment_dual_threshold_amount?.toString() || '',
        over_budget_threshold_percent: policy.over_budget_threshold_percent?.toString() || '',
      });
    }
  }, [policy]);

  const handleInputChange = (field: string, value: string) => {
    setFormData((prev) => ({
      ...prev,
      [field]: value,
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    try {
      const payload: any = {};
      
      // Only include fields that have values
      if (formData.co_dual_threshold_amount) {
        payload.co_dual_threshold_amount = parseFloat(formData.co_dual_threshold_amount);
      } else {
        payload.co_dual_threshold_amount = null;
      }
      
      if (formData.certificate_dual_threshold_amount) {
        payload.certificate_dual_threshold_amount = parseFloat(formData.certificate_dual_threshold_amount);
      } else {
        payload.certificate_dual_threshold_amount = null;
      }
      
      if (formData.payment_dual_threshold_amount) {
        payload.payment_dual_threshold_amount = parseFloat(formData.payment_dual_threshold_amount);
      } else {
        payload.payment_dual_threshold_amount = null;
      }
      
      if (formData.over_budget_threshold_percent) {
        payload.over_budget_threshold_percent = parseFloat(formData.over_budget_threshold_percent);
      } else {
        payload.over_budget_threshold_percent = null;
      }

      await updatePolicyMutation.mutateAsync(payload);
      toast.success('Cost approval policy updated successfully');
    } catch (error: any) {
      toast.error(error?.message || 'Failed to update cost approval policy');
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <LoadingSpinner />
      </div>
    );
  }

  if (error) {
    return (
      <div className="p-6">
        <Card>
          <CardContent className="p-6">
            <p className="text-red-600">Error loading cost approval policy: {(error as Error).message}</p>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">Cost Approval Policies</h1>
        <p className="text-sm text-[var(--color-text-secondary)] mt-1">
          Configure approval thresholds for cost objects (Change Orders, Certificates, Payments)
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Policy Configuration</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-6">
            <div className="space-y-4">
              <div>
                <Label htmlFor="co_dual_threshold_amount">
                  Change Order Threshold (requires high-level approval)
                </Label>
                <Input
                  id="co_dual_threshold_amount"
                  type="number"
                  step="0.01"
                  min="0"
                  value={formData.co_dual_threshold_amount}
                  onChange={(e) => handleInputChange('co_dual_threshold_amount', e.target.value)}
                  placeholder="e.g. 100000000"
                />
                <p className="text-xs text-[var(--color-text-secondary)] mt-1">
                  Amount from which Change Order approvals require high-privilege approver. Leave empty to disable.
                </p>
              </div>

              <div>
                <Label htmlFor="certificate_dual_threshold_amount">
                  Certificate Threshold (requires high-level approval)
                </Label>
                <Input
                  id="certificate_dual_threshold_amount"
                  type="number"
                  step="0.01"
                  min="0"
                  value={formData.certificate_dual_threshold_amount}
                  onChange={(e) => handleInputChange('certificate_dual_threshold_amount', e.target.value)}
                  placeholder="e.g. 80000000"
                />
                <p className="text-xs text-[var(--color-text-secondary)] mt-1">
                  Amount from which Payment Certificate approvals require high-privilege approver. Leave empty to disable.
                </p>
              </div>

              <div>
                <Label htmlFor="payment_dual_threshold_amount">
                  Payment Threshold (requires high-level approval)
                </Label>
                <Input
                  id="payment_dual_threshold_amount"
                  type="number"
                  step="0.01"
                  min="0"
                  value={formData.payment_dual_threshold_amount}
                  onChange={(e) => handleInputChange('payment_dual_threshold_amount', e.target.value)}
                  placeholder="e.g. 50000000"
                />
                <p className="text-xs text-[var(--color-text-secondary)] mt-1">
                  Amount from which Payment approvals require high-privilege approver. Leave empty to disable.
                </p>
              </div>

              <div>
                <Label htmlFor="over_budget_threshold_percent">
                  Over-Budget Threshold (%)
                </Label>
                <Input
                  id="over_budget_threshold_percent"
                  type="number"
                  step="0.01"
                  min="0"
                  max="1000"
                  value={formData.over_budget_threshold_percent}
                  onChange={(e) => handleInputChange('over_budget_threshold_percent', e.target.value)}
                  placeholder="e.g. 10.00"
                />
                <p className="text-xs text-[var(--color-text-secondary)] mt-1">
                  Percentage over budget that triggers stricter policy (e.g., 10.00 = 10%). Leave empty to disable.
                </p>
              </div>
            </div>

            <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
              <p className="text-sm text-blue-800 dark:text-blue-200">
                <strong>Note:</strong> If a field is empty/null, the system behaves as before (no threshold restrictions).
                Users with <code className="bg-blue-100 dark:bg-blue-800 px-1 rounded">projects.cost.approve_unlimited</code> permission can bypass all thresholds.
              </p>
            </div>

            <div className="flex justify-end">
              <Button
                type="submit"
                disabled={updatePolicyMutation.isPending}
              >
                {updatePolicyMutation.isPending ? 'Saving...' : 'Save Policy'}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
};
