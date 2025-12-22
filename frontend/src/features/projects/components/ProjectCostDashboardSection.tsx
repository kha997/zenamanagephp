import React, { useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { useProjectCostDashboard, useProjectCostHealth, useProjectCostFlowStatus } from '../hooks';
import { CostHealthBadge } from './CostHealthBadge';
import { ProjectCostFlowStatusBadge } from './ProjectCostFlowStatusBadge';
import { ProjectCostAlertsBanner } from './ProjectCostAlertsBanner';
import { MoneyCell } from '../../reports/components/MoneyCell';
import { zeroFillTimeSeries, formatMonthLabel, type TimeSeriesPoint } from '../utils/timeSeriesHelpers';
import { usePermissions } from '../../../hooks/usePermissions';

interface ProjectCostDashboardSectionProps {
  projectId: string | number;
}

/**
 * Project Cost Dashboard Section
 * 
 * Round 224: Project Cost Dashboard Frontend
 * 
 * Displays:
 * - Summary cards (Budget vs Contract, Flow of money)
 * - Variance & Forecast block
 * - Contracts & CO breakdown
 * - Time-series charts for certificates and payments
 */
export const ProjectCostDashboardSection: React.FC<ProjectCostDashboardSectionProps> = ({
  projectId,
}) => {
  const navigate = useNavigate();
  const { canViewCost } = usePermissions();
  const { data, isLoading, error, refetch } = useProjectCostDashboard(projectId);
  const { data: costHealthData } = useProjectCostHealth(projectId);
  const { data: costFlowStatusData } = useProjectCostFlowStatus(projectId);

  const dashboard = data?.data;

  // Permission check - Round 229
  if (!canViewCost(Number(projectId))) {
    return (
      <Card>
        <CardContent className="py-8">
          <div className="text-center text-[var(--muted)]">
            <p>You do not have permission to view cost data for this project.</p>
          </div>
        </CardContent>
      </Card>
    );
  }

  // Navigation handlers for drilldown
  const handleNavigateToContracts = () => {
    navigate(`/app/projects/${projectId}/contracts`);
  };

  // Zero-fill time-series data for charts
  const certificatesTimeSeries = useMemo(() => {
    if (!dashboard?.time_series?.certificates_per_month) return [];
    return zeroFillTimeSeries(
      dashboard.time_series.certificates_per_month,
      'amount_payable_approved'
    );
  }, [dashboard]);

  const paymentsTimeSeries = useMemo(() => {
    if (!dashboard?.time_series?.payments_per_month) return [];
    return zeroFillTimeSeries(
      dashboard.time_series.payments_per_month,
      'amount_paid'
    );
  }, [dashboard]);

  // Loading state
  if (isLoading) {
    return (
      <div className="space-y-6">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {[1, 2, 3, 4, 5, 6].map((i) => (
            <Card key={i}>
              <CardContent className="py-6">
                <div className="animate-pulse">
                  <div className="h-4 bg-[var(--muted-surface)] rounded w-1/2 mb-2"></div>
                  <div className="h-8 bg-[var(--muted-surface)] rounded w-3/4"></div>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
    );
  }

  // Error state
  if (error) {
    return (
      <Card>
        <CardContent className="py-8">
          <div className="text-center">
            <p className="text-[var(--muted)] mb-4">
              Error loading cost dashboard: {(error as Error).message}
            </p>
            <Button variant="primary" onClick={() => refetch()}>
              Retry
            </Button>
          </div>
        </CardContent>
      </Card>
    );
  }

  // No data state
  if (!dashboard) {
    return (
      <Card>
        <CardContent className="py-8">
          <div className="text-center text-[var(--muted)]">
            <p>No cost data available for this project.</p>
          </div>
        </CardContent>
      </Card>
    );
  }

  const currency = dashboard.currency || 'VND';

  return (
    <div className="space-y-6" data-testid="project-cost-dashboard">
      {/* Cost Health & Flow Status Badges */}
      <div className="flex items-center gap-4 flex-wrap">
        {costHealthData?.data && (
          <div className="flex items-center gap-2">
            <span className="text-sm font-medium text-[var(--text)]">Cost Health:</span>
            <CostHealthBadge
              status={costHealthData.data.cost_health_status}
              showTooltip={true}
            />
          </div>
        )}
        {costFlowStatusData?.data && (
          <div className="flex items-center gap-2">
            <span className="text-sm font-medium text-[var(--text)]">Cost Flow:</span>
            <ProjectCostFlowStatusBadge
              status={costFlowStatusData.data.status}
              metrics={costFlowStatusData.data.metrics}
              showTooltip={true}
            />
          </div>
        )}
      </div>

      {/* Cost Alerts Banner */}
      <ProjectCostAlertsBanner projectId={projectId} />
      
      {/* Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {/* Budget vs Contract */}
        <Card>
          <CardHeader>
            <CardTitle className="text-sm font-medium text-[var(--muted)]">
              Budget Total
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-semibold text-[var(--text)]">
              <MoneyCell value={dashboard.summary.budget_total} currency={currency} />
            </div>
          </CardContent>
        </Card>

        <Card 
          className="cursor-pointer hover:bg-[var(--muted-surface)] transition-colors"
          onClick={handleNavigateToContracts}
        >
          <CardHeader>
            <CardTitle className="text-sm font-medium text-[var(--muted)]">
              Contract Base Total
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-semibold text-[var(--text)]">
              <MoneyCell value={dashboard.summary.contract_base_total} currency={currency} />
            </div>
          </CardContent>
        </Card>

        <Card 
          className="cursor-pointer hover:bg-[var(--muted-surface)] transition-colors"
          onClick={handleNavigateToContracts}
        >
          <CardHeader>
            <CardTitle className="text-sm font-medium text-[var(--muted)]">
              Contract Current Total
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-semibold text-[var(--text)]">
              <MoneyCell value={dashboard.summary.contract_current_total} currency={currency} />
            </div>
          </CardContent>
        </Card>

        {/* Flow of money */}
        <Card>
          <CardHeader>
            <CardTitle className="text-sm font-medium text-[var(--muted)]">
              Total Certified Amount
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-semibold text-[var(--text)]">
              <MoneyCell value={dashboard.summary.total_certified_amount} currency={currency} />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-sm font-medium text-[var(--muted)]">
              Total Paid Amount
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-semibold text-[var(--text)]">
              <MoneyCell value={dashboard.summary.total_paid_amount} currency={currency} />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-sm font-medium text-[var(--muted)]">
              Outstanding Amount
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-semibold text-[var(--text)]">
              <MoneyCell value={dashboard.summary.outstanding_amount} currency={currency} />
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Variance & Forecast Block */}
      <Card>
        <CardHeader>
          <CardTitle>Variance & Forecast</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
              <label className="text-sm font-medium text-[var(--muted)] mb-1 block">
                Forecast Final Cost
              </label>
              <div className="text-lg font-semibold text-[var(--text)]">
                <MoneyCell value={dashboard.variance.forecast_final_cost} currency={currency} />
              </div>
              <p className="text-xs text-[var(--muted)] mt-1">
                Current Contract + Pending COs
              </p>
            </div>

            <div>
              <label className="text-sm font-medium text-[var(--muted)] mb-1 block">
                Variance vs Budget
              </label>
              <div className={`text-lg font-semibold ${
                dashboard.variance.variance_vs_budget <= 0
                  ? 'text-[var(--color-semantic-success-600)]'
                  : 'text-[var(--color-semantic-danger-600)]'
              }`}>
                <MoneyCell
                  value={dashboard.variance.variance_vs_budget}
                  currency={currency}
                  showPlusWhenPositive
                  tone={dashboard.variance.variance_vs_budget > 0 ? 'danger' : 'normal'}
                />
              </div>
            </div>

            <div>
              <label className="text-sm font-medium text-[var(--muted)] mb-1 block">
                Variance vs Current Contract
              </label>
              <div className={`text-lg font-semibold ${
                dashboard.variance.variance_vs_contract_current <= 0
                  ? 'text-[var(--color-semantic-success-600)]'
                  : 'text-[var(--color-semantic-warning-600)]'
              }`}>
                <MoneyCell
                  value={dashboard.variance.variance_vs_contract_current}
                  currency={currency}
                  showPlusWhenPositive
                />
              </div>
            </div>

            <div 
              className="cursor-pointer hover:bg-[var(--muted-surface)] p-2 rounded transition-colors"
              onClick={handleNavigateToContracts}
            >
              <label className="text-sm font-medium text-[var(--muted)] mb-1 block">
                Pending Change Orders
              </label>
              <div className="text-lg font-semibold text-[var(--text)]">
                <MoneyCell value={dashboard.variance.pending_change_orders_total} currency={currency} />
              </div>
            </div>

            {dashboard.variance.rejected_change_orders_total !== undefined && (
              <div>
                <label className="text-sm font-medium text-[var(--muted)] mb-1 block">
                  Rejected Change Orders
                </label>
                <div className="text-lg font-semibold text-[var(--muted)]">
                  <MoneyCell value={dashboard.variance.rejected_change_orders_total} currency={currency} />
                </div>
              </div>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Contracts & CO Breakdown */}
      <Card>
        <CardHeader>
          <CardTitle>Contracts & Change Orders Breakdown</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div 
              className="cursor-pointer hover:bg-[var(--muted-surface)] p-2 rounded transition-colors"
              onClick={handleNavigateToContracts}
            >
              <label className="text-sm font-medium text-[var(--muted)] mb-1 block">
                Contract Base Total
              </label>
              <div className="text-lg font-semibold text-[var(--text)]">
                <MoneyCell value={dashboard.contracts.contract_base_total} currency={currency} />
              </div>
            </div>

            <div 
              className="cursor-pointer hover:bg-[var(--muted-surface)] p-2 rounded transition-colors"
              onClick={handleNavigateToContracts}
            >
              <label className="text-sm font-medium text-[var(--muted)] mb-1 block">
                Approved COs
              </label>
              <div className="text-lg font-semibold text-[var(--color-semantic-success-600)]">
                <MoneyCell value={dashboard.contracts.change_orders_approved_total} currency={currency} />
              </div>
            </div>

            <div 
              className="cursor-pointer hover:bg-[var(--muted-surface)] p-2 rounded transition-colors"
              onClick={handleNavigateToContracts}
            >
              <label className="text-sm font-medium text-[var(--muted)] mb-1 block">
                Pending COs
              </label>
              <div className="text-lg font-semibold text-[var(--color-semantic-warning-600)]">
                <MoneyCell value={dashboard.contracts.change_orders_pending_total} currency={currency} />
              </div>
            </div>

            <div 
              className="cursor-pointer hover:bg-[var(--muted-surface)] p-2 rounded transition-colors"
              onClick={handleNavigateToContracts}
            >
              <label className="text-sm font-medium text-[var(--muted)] mb-1 block">
                Rejected COs
              </label>
              <div className="text-lg font-semibold text-[var(--muted)]">
                <MoneyCell value={dashboard.contracts.change_orders_rejected_total} currency={currency} />
              </div>
            </div>

            <div 
              className="cursor-pointer hover:bg-[var(--muted-surface)] p-2 rounded transition-colors"
              onClick={handleNavigateToContracts}
            >
              <label className="text-sm font-medium text-[var(--muted)] mb-1 block">
                Current Contract Total
              </label>
              <div className="text-lg font-semibold text-[var(--text)]">
                <MoneyCell value={dashboard.contracts.contract_current_total} currency={currency} />
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Time-series Charts */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Certificates per month */}
        <Card 
          className="cursor-pointer hover:bg-[var(--muted-surface)] transition-colors"
          onClick={handleNavigateToContracts}
        >
          <CardHeader>
            <CardTitle>Certificates per Month (Last 12 Months)</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="h-64">
              <SimpleBarChart
                data={certificatesTimeSeries}
                labelField="amount"
                xAxisLabel={(point) => formatMonthLabel(point.year, point.month)}
                currency={currency}
              />
            </div>
          </CardContent>
        </Card>

        {/* Payments per month */}
        <Card 
          className="cursor-pointer hover:bg-[var(--muted-surface)] transition-colors"
          onClick={handleNavigateToContracts}
        >
          <CardHeader>
            <CardTitle>Payments per Month (Last 12 Months)</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="h-64">
              <SimpleBarChart
                data={paymentsTimeSeries}
                labelField="amount"
                xAxisLabel={(point) => formatMonthLabel(point.year, point.month)}
                currency={currency}
              />
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
};

/**
 * Simple Bar Chart Component
 * 
 * A minimal SVG-based bar chart for displaying time-series data.
 * Uses simple HTML/SVG instead of heavy charting libraries.
 */
interface SimpleBarChartProps {
  data: TimeSeriesPoint[];
  labelField: keyof TimeSeriesPoint;
  xAxisLabel: (point: TimeSeriesPoint) => string;
  currency: string;
}

const SimpleBarChart: React.FC<SimpleBarChartProps> = ({
  data,
  labelField,
  xAxisLabel,
  currency,
}) => {
  if (!data || data.length === 0) {
    return (
      <div className="flex items-center justify-center h-full text-[var(--muted)]">
        No data available
      </div>
    );
  }

  const maxValue = Math.max(...data.map((d) => d[labelField] as number), 1);
  const chartHeight = 200;
  const chartWidth = 100;
  const barWidth = Math.max(2, (chartWidth / data.length) - 2);

  return (
    <div className="w-full h-full overflow-x-auto">
      <svg
        viewBox={`0 0 ${chartWidth} ${chartHeight + 30}`}
        className="w-full h-full min-w-full"
        preserveAspectRatio="xMidYMid meet"
      >
        {/* Bars */}
        {data.map((point, index) => {
          const value = point[labelField] as number;
          const barHeight = maxValue > 0 ? (value / maxValue) * chartHeight : 0;
          const x = (index * chartWidth) / data.length;
          const y = chartHeight - barHeight;

          return (
            <g key={`${point.year}-${point.month}`}>
              <rect
                x={x}
                y={y}
                width={barWidth}
                height={barHeight}
                fill="var(--accent)"
                opacity={0.7}
              />
              {/* Value label on top of bar */}
              {value > 0 && barHeight > 15 && (
                <text
                  x={x + barWidth / 2}
                  y={y - 2}
                  fontSize="8"
                  fill="var(--text)"
                  textAnchor="middle"
                  className="font-medium"
                >
                  {new Intl.NumberFormat('en-US', {
                    style: 'currency',
                    currency,
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0,
                    notation: 'compact',
                  }).format(value)}
                </text>
              )}
            </g>
          );
        })}

        {/* X-axis labels */}
        {data.map((point, index) => {
          const x = (index * chartWidth) / data.length + barWidth / 2;
          const label = xAxisLabel(point);
          // Show every 2nd or 3rd label to avoid crowding
          const showLabel = index % Math.ceil(data.length / 6) === 0 || index === data.length - 1;

          if (!showLabel) return null;

          return (
            <text
              key={`label-${point.year}-${point.month}`}
              x={x}
              y={chartHeight + 20}
              fontSize="8"
              fill="var(--muted)"
              textAnchor="middle"
            >
              {label.length > 8 ? label.substring(0, 8) : label}
            </text>
          );
        })}
      </svg>
    </div>
  );
};
