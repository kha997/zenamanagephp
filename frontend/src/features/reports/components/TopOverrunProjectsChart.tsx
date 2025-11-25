import React, { useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer } from 'recharts';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { formatCurrency, formatAmountShort, buildTopOverrunItems } from '../utils/chartUtils';

export type TopOverrunProjectsChartProps = {
  items: Array<{
    project_id: number | string;
    project_code: string;
    project_name: string | null;
    overrun_amount_total: number | null;
    currency?: string | null;
  }>;
  maxItems?: number; // default 5
};

/**
 * TopOverrunProjectsChart - Bar chart showing top projects by overrun amount
 * 
 * Round 57: Project Cost Portfolio Chart
 * Round 58: Added drill-down navigation on bar click
 * 
 * Features:
 * - Filters items with overrun_amount_total > 0
 * - Sorts by overrun_amount_total descending
 * - Shows top N items (default 5)
 * - Returns null if no items after filtering
 * - Click bar to navigate to project detail page
 */
export const TopOverrunProjectsChart: React.FC<TopOverrunProjectsChartProps> = ({
  items,
  maxItems = 5,
}) => {
  const navigate = useNavigate();

  const chartData = useMemo(() => {
    // Use buildTopOverrunItems utility
    const topItems = buildTopOverrunItems(items, maxItems);

    if (topItems.length === 0) {
      return null;
    }

    // Get currency from first item (or default to USD)
    const currency = topItems[0]?.currency || 'USD';

    // Transform to chart data format
    return {
      currency,
      data: topItems.map((item) => ({
        project_id: item.project_id,
        project_code: item.project_code,
        project_name: item.project_name,
        overrun_amount_total: item.overrun_amount_total ?? 0,
        // Label for X axis: prefer project_code, fallback to shortened project_name
        label: item.project_code || (item.project_name ? item.project_name.substring(0, 20) : `Project #${item.project_id}`),
      })),
    };
  }, [items, maxItems]);

  // Don't render if no data
  if (!chartData || chartData.data.length === 0) {
    return null;
  }

  // Handle bar click - navigate to project detail
  const handleBarClick = (data: any) => {
    const projectId = data?.payload?.project_id;
    if (projectId) {
      navigate(`/app/projects/${projectId}`);
    }
  };

  const CustomTooltip = ({ active, payload }: any) => {
    if (active && payload && payload.length) {
      const data = payload[0].payload;
      return (
        <div className="rounded-lg border border-[var(--color-border-subtle)] bg-[var(--color-surface-card)] p-3 shadow-lg">
          <p className="font-semibold text-[var(--color-text-primary)]">
            {data.project_code}
          </p>
          {data.project_name && (
            <p className="text-sm text-[var(--color-text-muted)]">
              {data.project_name}
            </p>
          )}
          <p className="mt-1 text-sm font-medium text-[var(--color-semantic-danger-600)]">
            Overrun: {formatCurrency(data.overrun_amount_total, chartData.currency)}
          </p>
        </div>
      );
    }
    return null;
  };

  return (
    <Card data-testid="top-overrun-projects-chart">
      <CardHeader>
        <CardTitle>Top dự án vượt chi phí</CardTitle>
        <p className="text-xs text-[var(--color-text-muted)] mt-1">
          Theo Overrun Total (giá trị dương)
        </p>
        <p className="mt-1 text-xs text-[var(--color-text-muted)]">
          Gợi ý: click vào từng cột để mở chi tiết dự án.
        </p>
      </CardHeader>
      <CardContent>
        <ResponsiveContainer width="100%" height={300}>
          <BarChart
            data={chartData.data}
            margin={{ top: 5, right: 30, left: 20, bottom: 5 }}
          >
            <XAxis
              dataKey="label"
              angle={-45}
              textAnchor="end"
              height={80}
              tick={{ fontSize: 12, fill: 'var(--color-text-muted)' }}
            />
            <YAxis
              tick={{ fontSize: 12, fill: 'var(--color-text-muted)' }}
              tickFormatter={formatAmountShort}
            />
            <Tooltip content={<CustomTooltip />} />
            <Bar
              dataKey="overrun_amount_total"
              fill="#EF4444" // --color-semantic-danger-500 from design tokens
              radius={[4, 4, 0, 0]}
              onClick={handleBarClick}
              style={{ cursor: 'pointer' }}
            />
          </BarChart>
        </ResponsiveContainer>
      </CardContent>
    </Card>
  );
};

