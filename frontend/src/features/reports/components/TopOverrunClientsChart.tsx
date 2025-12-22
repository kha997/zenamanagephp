import React, { useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer } from 'recharts';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { formatCurrency, formatAmountShort, buildTopOverrunItems } from '../utils/chartUtils';

export type TopOverrunClientsChartProps = {
  items: Array<{
    client_id: number | string;
    client_name: string | null;
    overrun_amount_total: number | null;
    currency?: string | null;
  }>;
  maxItems?: number; // default 5
};

/**
 * TopOverrunClientsChart - Bar chart showing top clients by overrun amount
 * 
 * Round 57: Client Cost Portfolio Chart
 * Round 58: Added drill-down navigation on bar click
 * 
 * Features:
 * - Filters items with overrun_amount_total > 0
 * - Sorts by overrun_amount_total descending
 * - Shows top N items (default 5)
 * - Returns null if no items after filtering
 * - Click bar to navigate to projects portfolio filtered by client
 */
export const TopOverrunClientsChart: React.FC<TopOverrunClientsChartProps> = ({
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
        client_id: item.client_id,
        client_name: item.client_name,
        overrun_amount_total: item.overrun_amount_total ?? 0,
        // Label for X axis: prefer client_name, fallback to Client #ID
        label: item.client_name || `Client #${item.client_id}`,
      })),
    };
  }, [items, maxItems]);

  // Don't render if no data
  if (!chartData || chartData.data.length === 0) {
    return null;
  }

  // Handle bar click - navigate to projects portfolio filtered by client
  const handleBarClick = (data: any) => {
    const clientId = data?.payload?.client_id;
    if (clientId) {
      navigate(`/app/reports/projects-portfolio?client_id=${clientId}`);
    }
  };

  const CustomTooltip = ({ active, payload }: any) => {
    if (active && payload && payload.length) {
      const data = payload[0].payload;
      return (
        <div className="rounded-lg border border-[var(--color-border-subtle)] bg-[var(--color-surface-card)] p-3 shadow-lg">
          <p className="font-semibold text-[var(--color-text-primary)]">
            {data.client_name || `Client #${data.client_id}`}
          </p>
          <p className="mt-1 text-sm font-medium text-[var(--color-semantic-danger-600)]">
            Overrun: {formatCurrency(data.overrun_amount_total, chartData.currency)}
          </p>
        </div>
      );
    }
    return null;
  };

  return (
    <Card data-testid="top-overrun-clients-chart">
      <CardHeader>
        <CardTitle>Top khách hàng vượt chi phí</CardTitle>
        <p className="text-xs text-[var(--color-text-muted)] mt-1">
          Theo Overrun Total (giá trị dương)
        </p>
        <p className="mt-1 text-xs text-[var(--color-text-muted)]">
          Gợi ý: click vào từng cột để xem danh sách dự án của khách hàng.
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

