import React, { useRef, useEffect } from 'react'
import { Card, CardHeader, CardTitle, CardContent } from '../ui/Card'
import { Skeleton } from '../ui/Skeleton'
import { BarChart3 } from 'lucide-react'
import type { ChartConfiguration, ChartType } from 'chart.js'

interface DashboardChartProps {
  type: 'project-progress' | 'task-completion'
  title: string
  data?: any
  loading?: boolean
  error?: Error | null
  dataTestId?: string
}

export const DashboardChart: React.FC<DashboardChartProps> = ({
  type,
  title,
  data,
  loading = false,
  error = null,
  dataTestId = `chart-${type}`
}) => {
  const canvasRef = useRef<HTMLCanvasElement>(null)
  const chartRef = useRef<any>(null) // Chart.js instance

  useEffect(() => {
    if (!canvasRef.current || !data || loading || error) return

    // Clean up previous chart
    if (chartRef.current) {
      chartRef.current.destroy()
      chartRef.current = null
    }

    // Lazy load Chart.js
    import('chart.js').then((ChartJS) => {
      const ctx = canvasRef.current
      if (!ctx) return

      const chartType: ChartType = type === 'project-progress' ? 'doughnut' : 'line'
      
      const config: ChartConfiguration = {
        type: chartType,
        data: data.datasets ? data : {
          labels: data.labels || [],
          datasets: [{
            data: data.data || [],
            ...data
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: type === 'project-progress' ? 'bottom' : 'top',
              labels: {
                padding: 15,
                usePointStyle: true
              }
            },
            ...(type === 'task-completion' && {
              tooltip: {
                mode: 'index',
                intersect: false
              }
            })
          },
          ...(type === 'project-progress' && {
            cutout: '70%'
          }),
          ...(type === 'task-completion' && {
            scales: {
              y: {
                beginAtZero: true,
                ticks: {
                  stepSize: 1
                }
              }
            }
          })
        }
      }

      chartRef.current = new ChartJS.Chart(ctx, config)
    })

    return () => {
      if (chartRef.current) {
        chartRef.current.destroy()
        chartRef.current = null
      }
    }
  }, [data, type, loading, error])

  return (
    <Card data-testid={dataTestId}>
      <CardHeader>
        <div className="flex items-center gap-2">
          <BarChart3 className="h-5 w-5 text-primary-600" />
          <CardTitle>{title}</CardTitle>
        </div>
      </CardHeader>
      <CardContent>
        {loading && (
          <div className="h-64 flex items-center justify-center">
            <Skeleton variant="rectangular" width="100%" height={256} />
          </div>
        )}

        {error && (
          <div className="h-64 flex items-center justify-center">
            <div className="text-center">
              <p className="text-sm text-muted-foreground mb-2">
                Failed to load chart data
              </p>
              <p className="text-xs text-muted-foreground">
                {error.message}
              </p>
            </div>
          </div>
        )}

        {!loading && !error && data && (
          <div className="h-64">
            <canvas ref={canvasRef} />
          </div>
        )}

        {!loading && !error && !data && (
          <div className="h-64 flex items-center justify-center">
            <div className="text-center">
              <p className="text-sm text-muted-foreground">No data available</p>
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  )
}

