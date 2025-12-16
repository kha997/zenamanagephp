import React from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { useReportsKpis } from '../../reports/hooks';

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
 * Format number
 */
const formatNumber = (value: number | null | undefined): string => {
  if (value === null || value === undefined) return '0';
  return value.toLocaleString('vi-VN');
};

/**
 * KPI Item Component
 */
interface KpiItemProps {
  label: string;
  value: number | string;
  variant?: 'default' | 'danger' | 'success' | 'warning';
}

const KpiItem: React.FC<KpiItemProps> = ({ label, value, variant = 'default' }) => {
  const variantClasses = {
    default: 'text-[var(--color-text-primary)]',
    danger: 'text-[var(--color-semantic-danger-600)]',
    success: 'text-[var(--color-semantic-success-600)]',
    warning: 'text-[var(--color-semantic-warning-600)]',
  };

  return (
    <div className="flex flex-col gap-1">
      <span className="text-sm font-medium text-[var(--color-text-muted)]">{label}</span>
      <span className={`text-xl font-bold ${variantClasses[variant]}`}>{value}</span>
    </div>
  );
};

/**
 * KPI Money Component
 */
interface KpiMoneyProps {
  label: string;
  amount: number | null | undefined;
  currency?: string;
  variant?: 'default' | 'danger' | 'success' | 'warning';
}

const KpiMoney: React.FC<KpiMoneyProps> = ({ label, amount, currency = 'VND', variant = 'default' }) => {
  return <KpiItem label={label} value={formatCurrency(amount, currency)} variant={variant} />;
};

/**
 * KPI Overdue Component
 */
interface KpiOverdueProps {
  count: number | null | undefined;
  amount: number | null | undefined;
  currency?: string;
}

const KpiOverdue: React.FC<KpiOverdueProps> = ({ count, amount, currency = 'VND' }) => {
  const hasOverdue = (count ?? 0) > 0;
  return (
    <div className="flex flex-col gap-1">
      <span className="text-sm font-medium text-[var(--color-text-muted)]">Quá hạn</span>
      <div className="flex flex-col gap-1">
        <span className={`text-xl font-bold ${hasOverdue ? 'text-[var(--color-semantic-danger-600)]' : 'text-[var(--color-text-primary)]'}`}>
          {formatNumber(count)} đợt
        </span>
        {hasOverdue && (
          <span className="text-sm text-[var(--color-semantic-danger-600)]">
            {formatCurrency(amount, currency)}
          </span>
        )}
      </div>
    </div>
  );
};

/**
 * ContractsKpisWidget - Widget displaying Contracts & Payments KPIs
 * 
 * Round 42: Dashboard Contracts KPIs Widget
 * 
 * Features:
 * - Displays contract counts (total, active, completed, cancelled)
 * - Displays financial metrics (total value, scheduled, paid, remaining)
 * - Displays overdue payments (count and amount)
 * - Uses /api/v1/app/reports/kpis endpoint
 * - Tenant-aware caching via useReportsKpis hook
 * - RBAC: Requires tenant.view_reports permission (checked at Dashboard level)
 */
export const ContractsKpisWidget: React.FC = () => {
  const { data, isLoading, error, refetch } = useReportsKpis();

  if (isLoading) {
    return (
      <Card data-testid="contracts-kpis-widget">
        <CardHeader>
          <CardTitle>Hợp đồng & thanh toán</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            {[1, 2, 3].map((i) => (
              <div key={i} className="animate-pulse">
                <div className="h-4 bg-[var(--color-surface-subtle)] rounded w-3/4 mb-2"></div>
                <div className="h-6 bg-[var(--color-surface-subtle)] rounded w-1/2"></div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
    );
  }

  if (error || !data) {
    return (
      <Card data-testid="contracts-kpis-widget">
        <CardHeader>
          <CardTitle>Hợp đồng & thanh toán</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="flex flex-col gap-3">
            <p className="text-sm text-[var(--color-semantic-danger-600)] mb-2">
              Không tải được KPIs hợp đồng
            </p>
            <Button size="sm" variant="secondary" onClick={() => refetch()}>
              Thử lại
            </Button>
          </div>
        </CardContent>
      </Card>
    );
  }

  const contracts = data?.contracts;
  if (!contracts) {
    return (
      <Card data-testid="contracts-kpis-widget">
        <CardHeader>
          <CardTitle>Hợp đồng & thanh toán</CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-[var(--color-text-muted)]">Không có dữ liệu hợp đồng</p>
        </CardContent>
      </Card>
    );
  }

  const {
    total_count,
    active_count,
    completed_count,
    cancelled_count,
    total_value,
    payments,
    budget,
    actual,
  } = contracts;

  return (
    <Card data-testid="contracts-kpis-widget">
      <CardHeader>
        <CardTitle>Hợp đồng & thanh toán</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="space-y-4">
          {/* Row 1: Contract counts */}
          <div className="grid gap-3 md:grid-cols-3">
            <KpiItem label="Tổng số HĐ" value={formatNumber(total_count)} />
            <KpiItem label="Đang thực hiện" value={formatNumber(active_count)} variant="success" />
            <KpiItem label="Hoàn thành" value={formatNumber(completed_count)} variant="success" />
          </div>

          {/* Row 2: Financial metrics */}
          <div className="grid gap-3 md:grid-cols-3">
            <KpiMoney label="Tổng giá trị HĐ" amount={total_value} />
            <KpiMoney label="Đã phân bổ (scheduled)" amount={payments?.scheduled_total} />
            <KpiMoney label="Đã thanh toán" amount={payments?.paid_total} variant="success" />
          </div>

          {/* Row 3: Remaining & Overdue */}
          <div className="grid gap-3 md:grid-cols-3">
            <KpiMoney label="Còn chưa phân bổ" amount={payments?.remaining_to_schedule} />
            <KpiMoney label="Còn phải thanh toán" amount={payments?.remaining_to_pay} variant="warning" />
            <KpiOverdue
              count={payments?.overdue_count}
              amount={payments?.overdue_total}
            />
          </div>

          {/* Row 4: Budget KPIs (if available) */}
          {budget && (
            <div className="grid gap-3 md:grid-cols-3">
              <KpiMoney label="Tổng Budget (planned)" amount={budget.budget_total} />
              <KpiItem label="Số dòng budget" value={formatNumber(budget.active_line_count)} />
              <KpiItem 
                label="HĐ over budget" 
                value={formatNumber(budget.over_budget_contracts_count)}
                variant={budget.over_budget_contracts_count > 0 ? 'warning' : 'default'}
              />
            </div>
          )}

          {/* Row 5: Actual KPIs (if available) */}
          {actual && (
            <div className="grid gap-3 md:grid-cols-3">
              <KpiMoney label="Tổng Actual (chi phí)" amount={actual.actual_total} />
              <KpiItem 
                label="HĐ vượt chi phí" 
                value={formatNumber(actual.overrun_contracts_count)}
                variant={actual.overrun_contracts_count > 0 ? 'warning' : 'default'}
              />
              {actual.contract_vs_actual_diff_total !== undefined && (
                <KpiMoney 
                  label="Tổng (HĐ – Actual)" 
                  amount={actual.contract_vs_actual_diff_total}
                  variant={actual.contract_vs_actual_diff_total < 0 ? 'warning' : 'default'}
                />
              )}
            </div>
          )}
        </div>
      </CardContent>
    </Card>
  );
};

