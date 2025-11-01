import React, { useEffect, useRef, useCallback } from 'react';

export interface ChartData {
  id: string;
  type: 'doughnut' | 'line' | 'bar';
  title: string;
  data: {
    labels: string[];
    datasets: Array<{
      label: string;
      data: number[];
      backgroundColor?: string | string[];
      borderColor?: string;
      borderWidth?: number;
      fill?: boolean;
    }>;
  };
  options?: any;
}

interface ChartWidgetProps {
  data: ChartData[];
  loading?: boolean;
  error?: boolean;
}

export const ChartWidget: React.FC<ChartWidgetProps> = ({ data, loading = false, error = false }) => {
  const chartRefs = useRef<{ [key: string]: HTMLCanvasElement | null }>({});

  useEffect(() => {
    // Load Chart.js dynamically
    const loadChartJS = async () => {
      if (typeof window !== 'undefined' && !window.Chart) {
        const Chart = await import('chart.js/auto');
        window.Chart = Chart.default;
      }
    };

    loadChartJS().then(() => {
      if (window.Chart && !loading && !error) {
        renderCharts();
      }
    });
  }, [data, loading, error, renderCharts]);

  const renderCharts = useCallback(() => {
    data.forEach((chartData) => {
      const canvas = chartRefs.current[chartData.id];
      if (!canvas) return;

      // Destroy existing chart if it exists
      const existingChart = Chart.getChart(canvas);
      if (existingChart) {
        existingChart.destroy();
      }

      // Create new chart
      new Chart(canvas, {
        type: chartData.type,
        data: chartData.data,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                padding: 20,
                usePointStyle: true
              }
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  const label = context.label || '';
                  const value = context.parsed;
                  
                  if (chartData.type === 'doughnut') {
                    const total = context.dataset.data.reduce((a: number, b: number) => a + b, 0);
                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                    return `${label}: ${value} (${percentage}%)`;
                  }
                  
                  return `${label}: ${value}`;
                }
              }
            }
          },
          ...chartData.options
        }
      });
    });
  }, [data]);

  if (loading) {
    return (
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {Array.from({ length: 2 }).map((_, index) => (
          <div key={index} className="bg-white shadow-sm rounded-lg border border-gray-200">
            <div className="px-6 py-4 border-b border-gray-200">
              <div className="h-6 bg-gray-200 rounded animate-pulse"></div>
            </div>
            <div className="p-6">
              <div className="h-64 bg-gray-200 rounded animate-pulse"></div>
            </div>
          </div>
        ))}
      </div>
    );
  }

  if (error) {
    return (
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div className="col-span-full bg-red-50 border border-red-200 rounded-lg p-6">
          <div className="flex items-center">
            <div className="flex-shrink-0">
              <i className="fas fa-exclamation-triangle text-red-400 text-xl"></i>
            </div>
            <div className="ml-3">
              <h3 className="text-sm font-medium text-red-800">Failed to load charts</h3>
              <p className="text-sm text-red-700 mt-1">Please try refreshing the page.</p>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
      {data.map((chartData) => (
        <div key={chartData.id} className="bg-white shadow-sm rounded-lg border border-gray-200">
          <div className="px-6 py-4 border-b border-gray-200">
            <h2 className="text-lg font-semibold text-gray-900">{chartData.title}</h2>
          </div>
          <div className="p-6">
            <div className="h-64">
              <canvas
                ref={(el) => {
                  chartRefs.current[chartData.id] = el;
                }}
                className="w-full h-full"
              ></canvas>
            </div>
          </div>
        </div>
      ))}
    </div>
  );
};

export default ChartWidget;
