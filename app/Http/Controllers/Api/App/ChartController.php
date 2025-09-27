<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ChartController extends Controller
{
    /**
     * Get chart data
     */
    public function getChartData(Request $request, string $chartId): JsonResponse
    {
        try {
            $chartType = $request->input('type', 'bar');
            $filters = $request->input('filters', []);
            $options = $request->input('options', []);

            $chartData = $this->generateChartData($chartId, $chartType, $filters, $options);
            $stats = $this->calculateChartStats($chartData);

            return response()->json([
                'success' => true,
                'data' => [
                    'chartData' => $chartData,
                    'stats' => $stats
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Chart data generation failed', [
                'chart_id' => $chartId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to generate chart data'
                ]
            ], 500);
        }
    }

    /**
     * Generate chart data based on chart ID and type
     */
    protected function generateChartData(string $chartId, string $chartType, array $filters, array $options): array
    {
        switch ($chartId) {
            case 'project-status':
                return $this->getProjectStatusData($chartType, $filters);
            case 'task-trends':
                return $this->getTaskTrendsData($chartType, $filters);
            case 'team-performance':
                return $this->getTeamPerformanceData($chartType, $filters);
            case 'budget-utilization':
                return $this->getBudgetUtilizationData($chartType, $filters);
            case 'timeline':
                return $this->getTimelineData($chartType, $filters);
            case 'productivity':
                return $this->getProductivityData($chartType, $filters);
            default:
                return $this->getDefaultChartData($chartType);
        }
    }

    /**
     * Get project status chart data
     */
    protected function getProjectStatusData(string $chartType, array $filters): array
    {
        $data = [
            'labels' => ['Active', 'Completed', 'On Hold', 'Planning', 'Cancelled'],
            'datasets' => [
                [
                    'label' => 'Projects',
                    'data' => [12, 8, 3, 5, 2],
                    'backgroundColor' => [
                        '#3B82F6', // Active - Blue
                        '#10B981', // Completed - Green
                        '#F59E0B', // On Hold - Yellow
                        '#6B7280', // Planning - Gray
                        '#EF4444'  // Cancelled - Red
                    ],
                    'borderColor' => '#ffffff',
                    'borderWidth' => 2
                ]
            ]
        ];

        return $this->formatChartData($data, $chartType);
    }

    /**
     * Get task trends chart data
     */
    protected function getTaskTrendsData(string $chartType, array $filters): array
    {
        $period = $filters['period'] ?? '30d';
        $labels = $this->getPeriodLabels($period);

        $data = [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Completed Tasks',
                    'data' => [15, 22, 18, 25, 30, 28, 35, 32, 40, 38, 45, 42],
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'tension' => 0.4,
                    'fill' => true
                ],
                [
                    'label' => 'Created Tasks',
                    'data' => [12, 18, 15, 20, 25, 22, 28, 30, 35, 32, 38, 40],
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.4,
                    'fill' => true
                ]
            ]
        ];

        return $this->formatChartData($data, $chartType);
    }

    /**
     * Get team performance chart data
     */
    protected function getTeamPerformanceData(string $chartType, array $filters): array
    {
        if ($chartType === 'radar') {
            return [
                'labels' => ['Productivity', 'Quality', 'Collaboration', 'Innovation', 'Delivery', 'Communication'],
                'datasets' => [
                    [
                        'label' => 'Team Average',
                        'data' => [85, 92, 78, 88, 90, 82],
                        'borderColor' => '#3B82F6',
                        'backgroundColor' => 'rgba(59, 130, 246, 0.2)',
                        'pointBackgroundColor' => '#3B82F6',
                        'pointBorderColor' => '#ffffff',
                        'pointBorderWidth' => 2
                    ]
                ]
            ];
        }

        $data = [
            'labels' => ['John Doe', 'Jane Smith', 'Mike Johnson', 'Sarah Wilson', 'David Brown'],
            'datasets' => [
                [
                    'label' => 'Performance Score',
                    'data' => [92, 88, 95, 85, 90],
                    'backgroundColor' => [
                        '#10B981',
                        '#3B82F6',
                        '#F59E0B',
                        '#EF4444',
                        '#8B5CF6'
                    ],
                    'borderColor' => '#ffffff',
                    'borderWidth' => 2
                ]
            ]
        ];

        return $this->formatChartData($data, $chartType);
    }

    /**
     * Get budget utilization chart data
     */
    protected function getBudgetUtilizationData(string $chartType, array $filters): array
    {
        $data = [
            'labels' => ['Q1', 'Q2', 'Q3', 'Q4'],
            'datasets' => [
                [
                    'label' => 'Budget Allocated',
                    'data' => [100000, 120000, 110000, 130000],
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor' => '#3B82F6',
                    'borderWidth' => 2
                ],
                [
                    'label' => 'Budget Spent',
                    'data' => [95000, 115000, 108000, 125000],
                    'backgroundColor' => 'rgba(16, 185, 129, 0.5)',
                    'borderColor' => '#10B981',
                    'borderWidth' => 2
                ]
            ]
        ];

        return $this->formatChartData($data, $chartType);
    }

    /**
     * Get timeline chart data
     */
    protected function getTimelineData(string $chartType, array $filters): array
    {
        $data = [
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            'datasets' => [
                [
                    'label' => 'Project Milestones',
                    'data' => [2, 4, 3, 6, 5, 8],
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.4,
                    'pointRadius' => 6,
                    'pointHoverRadius' => 8
                ]
            ]
        ];

        return $this->formatChartData($data, $chartType);
    }

    /**
     * Get productivity chart data
     */
    protected function getProductivityData(string $chartType, array $filters): array
    {
        $data = [
            'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'datasets' => [
                [
                    'label' => 'Tasks Completed',
                    'data' => [8, 12, 10, 15, 18, 5, 3],
                    'backgroundColor' => 'rgba(16, 185, 129, 0.5)',
                    'borderColor' => '#10B981',
                    'borderWidth' => 2
                ]
            ]
        ];

        return $this->formatChartData($data, $chartType);
    }

    /**
     * Get default chart data
     */
    protected function getDefaultChartData(string $chartType): array
    {
        return [
            'labels' => ['Sample 1', 'Sample 2', 'Sample 3', 'Sample 4'],
            'datasets' => [
                [
                    'label' => 'Sample Data',
                    'data' => [10, 20, 15, 25],
                    'backgroundColor' => '#3B82F6',
                    'borderColor' => '#ffffff',
                    'borderWidth' => 2
                ]
            ]
        ];
    }

    /**
     * Format chart data for specific chart type
     */
    protected function formatChartData(array $data, string $chartType): array
    {
        // Add chart type specific formatting
        switch ($chartType) {
            case 'line':
                // Ensure line charts have proper line styling
                foreach ($data['datasets'] as &$dataset) {
                    if (!isset($dataset['tension'])) {
                        $dataset['tension'] = 0.4;
                    }
                    if (!isset($dataset['fill'])) {
                        $dataset['fill'] = false;
                    }
                }
                break;
            case 'bar':
                // Ensure bar charts have proper bar styling
                foreach ($data['datasets'] as &$dataset) {
                    if (!isset($dataset['borderRadius'])) {
                        $dataset['borderRadius'] = 4;
                    }
                }
                break;
        }

        return $data;
    }

    /**
     * Get period labels based on filter
     */
    protected function getPeriodLabels(string $period): array
    {
        switch ($period) {
            case '7d':
                return ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            case '30d':
                return ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
            case '90d':
                return ['Month 1', 'Month 2', 'Month 3'];
            case '1y':
                return ['Q1', 'Q2', 'Q3', 'Q4'];
            default:
                return ['Period 1', 'Period 2', 'Period 3', 'Period 4'];
        }
    }

    /**
     * Calculate chart statistics
     */
    protected function calculateChartStats(array $chartData): array
    {
        $stats = [];

        if (!empty($chartData['datasets'])) {
            $firstDataset = $chartData['datasets'][0];
            if (!empty($firstDataset['data'])) {
                $data = $firstDataset['data'];
                $stats = [
                    [
                        'label' => 'Total',
                        'value' => array_sum($data)
                    ],
                    [
                        'label' => 'Average',
                        'value' => round(array_sum($data) / count($data), 2)
                    ],
                    [
                        'label' => 'Max',
                        'value' => max($data)
                    ],
                    [
                        'label' => 'Min',
                        'value' => min($data)
                    ]
                ];
            }
        }

        return $stats;
    }
}
