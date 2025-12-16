import React from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { useContractCostOverruns } from '../hooks';

/**
 * Format currency value
 */
const formatCurrency = (amount: number | null | undefined, currency: string = 'VND'): string => {
  if (amount === null || amount === undefined) return '0';
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: currency,
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount);
};

/**
 * ContractCostOverrunsWidget - Widget displaying contracts that are over budget or over actual cost
 * 
 * Round 47: Cost Overruns Dashboard
 * 
 * Features:
 * - Displays contracts over budget (budget_total > contract_value)
 * - Displays contracts overrun (actual_total > contract_value)
 * - Clickable contract items navigate to contract detail
 * - Uses /api/v1/app/reports/contracts/cost-overruns endpoint
 * - RBAC: Requires tenant.view_reports permission (checked at Dashboard level)
 */
export const ContractCostOverrunsWidget: React.FC = () => {
  const navigate = useNavigate();
  const { data, isLoading, error, refetch } = useContractCostOverruns({ limit: 10 });

  if (isLoading) {
    return (
      <Card data-testid="contract-cost-overruns-widget">
        <CardHeader>
          <CardTitle>Hợp đồng vượt Budget / Actual</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <div className="animate-pulse">
              <div className="h-4 bg-[var(--color-surface-subtle)] rounded w-3/4 mb-2"></div>
              <div className="h-6 bg-[var(--color-surface-subtle)] rounded w-1/2"></div>
            </div>
            <div className="animate-pulse">
              <div className="h-4 bg-[var(--color-surface-subtle)] rounded w-3/4 mb-2"></div>
              <div className="h-6 bg-[var(--color-surface-subtle)] rounded w-1/2"></div>
            </div>
          </div>
        </CardContent>
      </Card>
    );
  }

  if (error || !data) {
    return (
      <Card data-testid="contract-cost-overruns-widget">
        <CardHeader>
          <CardTitle>Hợp đồng vượt Budget / Actual</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="flex flex-col gap-3">
            <p className="text-sm text-[var(--color-semantic-danger-600)] mb-2">
              Không tải được danh sách hợp đồng vượt budget/actual
            </p>
            <Button size="sm" variant="secondary" onClick={() => refetch()}>
              Thử lại
            </Button>
          </div>
        </CardContent>
      </Card>
    );
  }

  const { overBudgetContracts, overrunContracts } = data;

  const hasOverBudget = overBudgetContracts && overBudgetContracts.length > 0;
  const hasOverrun = overrunContracts && overrunContracts.length > 0;

  if (!hasOverBudget && !hasOverrun) {
    return (
      <Card data-testid="contract-cost-overruns-widget">
        <CardHeader>
          <CardTitle>Hợp đồng vượt Budget / Actual</CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-[var(--color-text-muted)]">
            Không có hợp đồng nào vượt budget hoặc vượt actual cost
          </p>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card data-testid="contract-cost-overruns-widget">
      <CardHeader>
        <div className="flex items-center justify-between">
          <CardTitle>Hợp đồng vượt Budget / Actual</CardTitle>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => navigate('/app/reports/cost-overruns')}
            className="text-xs"
          >
            Xem tất cả
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        <div className="space-y-6">
          {/* Over Budget Contracts */}
          {hasOverBudget && (
            <div>
              <h3 className="text-sm font-semibold text-[var(--color-text-primary)] mb-3">
                HĐ vượt Budget
              </h3>
              <div className="space-y-2">
                {overBudgetContracts.map((contract) => (
                  <button
                    key={contract.id}
                    onClick={() => navigate(`/app/contracts/${contract.id}`)}
                    className="w-full text-left p-3 rounded-lg border border-[var(--color-border-subtle)] hover:bg-[var(--color-surface-subtle)] transition-colors"
                  >
                    <div className="flex items-start justify-between gap-2">
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 mb-1">
                          <span className="text-sm font-medium text-[var(--color-text-primary)] truncate">
                            {contract.code}
                          </span>
                          <span className="text-xs text-[var(--color-text-muted)]">
                            {contract.status}
                          </span>
                        </div>
                        <p className="text-sm text-[var(--color-text-muted)] truncate mb-1">
                          {contract.name}
                        </p>
                        {contract.client_name && (
                          <p className="text-xs text-[var(--color-text-muted)] truncate">
                            Khách hàng: {contract.client_name}
                          </p>
                        )}
                      </div>
                      <div className="text-right flex-shrink-0">
                        <div className="text-sm font-semibold text-[var(--color-semantic-warning-600)]">
                          +{formatCurrency(contract.budget_vs_contract_diff, contract.currency)}
                        </div>
                        <div className="text-xs text-[var(--color-text-muted)]">
                          Budget: {formatCurrency(contract.budget_total, contract.currency)}
                        </div>
                        <div className="text-xs text-[var(--color-text-muted)]">
                          HĐ: {formatCurrency(contract.contract_value, contract.currency)}
                        </div>
                      </div>
                    </div>
                  </button>
                ))}
              </div>
            </div>
          )}

          {/* Overrun Contracts */}
          {hasOverrun && (
            <div>
              <h3 className="text-sm font-semibold text-[var(--color-text-primary)] mb-3">
                HĐ vượt Actual
              </h3>
              <div className="space-y-2">
                {overrunContracts.map((contract) => (
                  <button
                    key={contract.id}
                    onClick={() => navigate(`/app/contracts/${contract.id}`)}
                    className="w-full text-left p-3 rounded-lg border border-[var(--color-border-subtle)] hover:bg-[var(--color-surface-subtle)] transition-colors"
                  >
                    <div className="flex items-start justify-between gap-2">
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 mb-1">
                          <span className="text-sm font-medium text-[var(--color-text-primary)] truncate">
                            {contract.code}
                          </span>
                          <span className="text-xs text-[var(--color-text-muted)]">
                            {contract.status}
                          </span>
                        </div>
                        <p className="text-sm text-[var(--color-text-muted)] truncate mb-1">
                          {contract.name}
                        </p>
                        {contract.client_name && (
                          <p className="text-xs text-[var(--color-text-muted)] truncate">
                            Khách hàng: {contract.client_name}
                          </p>
                        )}
                      </div>
                      <div className="text-right flex-shrink-0">
                        <div className="text-sm font-semibold text-[var(--color-semantic-danger-600)]">
                          {formatCurrency(-contract.contract_vs_actual_diff, contract.currency)}
                        </div>
                        <div className="text-xs text-[var(--color-text-muted)]">
                          Actual: {formatCurrency(contract.actual_total, contract.currency)}
                        </div>
                        <div className="text-xs text-[var(--color-text-muted)]">
                          HĐ: {formatCurrency(contract.contract_value, contract.currency)}
                        </div>
                      </div>
                    </div>
                  </button>
                ))}
              </div>
            </div>
          )}
        </div>
      </CardContent>
    </Card>
  );
};

