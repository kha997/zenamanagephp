import React, { useRef, useEffect } from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '../../shared/ui/card';
import { Skeleton } from '../../shared/ui/skeleton';
import { useI18n } from '../../app/i18n-context';
import { useDashboardChart } from '../../entities/dashboard/hooks';
import type { ApiResponse } from '../../entities/dashboard/types';

export interface ProjectProgressChartProps {
  /** Optional chart data (if not provided, will fetch from API) */
  data?: ApiResponse<any> | null;
  /** Loading state (if not provided, will use hook loading state) */
  loading?: boolean;
  /** Error state (if not provided, will use hook error state) */
  error?: Error | null;
  /** Chart period */
  period?: string;
  /** Optional className */
  className?: string;
}

/**
 * ProjectProgressChart - Dashboard component for displaying project progress chart
 * 
 * Displays project status distribution as a doughnut chart:
 * - Planning projects
 * - Active projects
 * - Completed projects
 * - On Hold projects
 * - Cancelled projects
 * 
 * Features:
 * - Loading skeletons
 * - Error states
 * - Empty states
 * - Accessibility support
 * - Lazy loaded Chart.js
 */
export const ProjectProgressChart: React.FC<ProjectProgressChartProps> = ({
  data: externalData,
  loading: externalLoading,
  error: externalError,
  period,
  className,
}) => {
  const { t } = useI18n();
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const chartRef = useRef<any>(null); // Chart.js instance

  // Fetch chart data if not provided externally
  const {
    data: chartData,
    isLoading: hookLoading,
    error: hookError,
  } = useDashboardChart('project-progress', period);

  const data = externalData || chartData;
  const loading = externalLoading !== undefined ? externalLoading : hookLoading;
  const error = externalError || hookError;

  useEffect(() => {
    if (!canvasRef.current || !data?.data || loading || error) {
      return;
    }

    // Clean up previous chart
    if (chartRef.current) {
      chartRef.current.destroy();
      chartRef.current = null;
    }

    // Use double requestAnimationFrame to ensure all other components are laid out first
    // This prevents forced reflow by deferring chart initialization until after layout
    const rafId = requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        // Lazy load Chart.js with auto registration of all controllers
        import('chart.js/auto').then((ChartJS) => {
          const ctx = canvasRef.current;
          if (!ctx) return;

          const chartData = data.data;
          
          // Transform API data to Chart.js format if needed
          const chartConfig = chartData.datasets
            ? chartData
            : {
                labels: chartData.labels || ['Planning', 'Active', 'Completed', 'On Hold', 'Cancelled'],
                datasets: [
                  {
                    label: 'Projects',
                    data: chartData.data || [],
                    backgroundColor: [
                      'var(--color-semantic-primary-500)',
                      'var(--color-semantic-success-500)',
                      'var(--color-semantic-info-500)',
                      'var(--color-semantic-warning-500)',
                      'var(--color-semantic-danger-500)',
                    ],
                    borderWidth: 0,
                  },
                ],
              };

          const config: any = {
            type: 'doughnut' as const,
            data: chartConfig,
            options: {
              responsive: true,
              maintainAspectRatio: false,
              animation: {
                duration: 0, // Disable animation to avoid reflow during initialization
              },
              plugins: {
                legend: {
                  position: 'bottom' as const,
                  labels: {
                    padding: 15,
                    usePointStyle: true,
                    color: 'var(--color-text-primary)',
                    boxWidth: 12,
                    font: {
                      size: 12,
                    },
                  },
                },
                tooltip: {
                  enabled: true,
                  callbacks: {
                    label: (context: any) => {
                      const label = context.label || '';
                      const value = context.parsed || 0;
                      const total = context.dataset.data.reduce((a: number, b: number) => a + b, 0);
                      const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0';
                      return `${label}: ${value} (${percentage}%)`;
                    },
                  },
                },
              },
              cutout: '70%',
              layout: {
                padding: {
                  top: 0,
                  bottom: 0,
                  left: 0,
                  right: 0,
                },
              },
            },
          };

          chartRef.current = new ChartJS.Chart(ctx, config);
        }).catch((err) => {
          console.error('Failed to load Chart.js:', err);
        });
      });
    });

    let idleCallbackId: number | undefined;

    return () => {
      cancelAnimationFrame(rafId);
      if (idleCallbackId !== undefined && 'cancelIdleCallback' in window) {
        (window as any).cancelIdleCallback(idleCallbackId);
      }
      if (chartRef.current) {
        chartRef.current.destroy();
        chartRef.current = null;
      }
    };
  }, [data, loading, error]);

  return (
    <Card
      role="region"
      aria-label="Project progress chart"
      className={className}
    >
      <CardHeader>
        <CardTitle>
          {t('dashboard.projectProgress', { defaultValue: 'Project Progress' })}
        </CardTitle>
        <p className="text-sm text-[var(--color-text-muted)] mt-1">
          {t('dashboard.projectProgressDescription', { defaultValue: 'Distribution of projects by status' })}
        </p>
      </CardHeader>

      <CardContent>
        {loading && (
          <div className="h-64 flex items-center justify-center" role="status" aria-live="polite">
            <Skeleton className="h-full w-full" />
          </div>
        )}

        {error && (
          <div className="h-64 flex items-center justify-center" role="alert">
            <div className="text-center">
              <p className="text-sm text-[var(--color-text-muted)] mb-2">
                {t('dashboard.chartError', { defaultValue: 'Failed to load chart data' })}
              </p>
              <p className="text-xs text-[var(--color-text-muted)]">
                {error.message || t('common.errorOccurred', { defaultValue: 'An error occurred' })}
              </p>
            </div>
          </div>
        )}

        {!loading && !error && data?.data && (
          <div className="h-64" aria-hidden="true">
            <canvas ref={canvasRef} />
          </div>
        )}

        {!loading && !error && (!data?.data || (data.data.datasets?.[0]?.data?.every((v: number) => v === 0))) && (
          <div className="h-64 flex items-center justify-center" role="status">
            <div className="text-center">
              <div className="text-4xl mb-4 opacity-50" aria-hidden="true">📊</div>
              <p className="text-sm text-[var(--color-text-muted)]">
                {t('dashboard.noChartData', { defaultValue: 'No data available' })}
              </p>
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  );
};

export default ProjectProgressChart;

