<?php declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DashboardWidget;
use App\Models\DashboardMetric;
use Illuminate\Database\Seeder;

/**
 * Dashboard Seeder
 * 
 * Tạo dữ liệu mẫu cho Dashboard System
 */
class DashboardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createDashboardWidgets();
        $this->createDashboardMetrics();
    }

    /**
     * Tạo dashboard widgets
     */
    private function createDashboardWidgets(): void
    {
        $widgets = [
            // System Admin Widgets
            [
                'name' => 'System Overview',
                'type' => 'card',
                'category' => 'overview',
                'config' => [
                    'display' => [
                        'title' => 'System Overview',
                        'icon' => 'server',
                        'color' => 'blue'
                    ]
                ],
                'data_source' => [
                    'type' => 'static',
                    'data' => [
                        'total_users' => 1247,
                        'active_projects' => 23,
                        'system_load' => 78
                    ]
                ],
                'permissions' => [
                    'roles' => ['system_admin']
                ],
                'description' => 'Tổng quan hệ thống cho System Admin'
            ],
            [
                'name' => 'User Management',
                'type' => 'table',
                'category' => 'overview',
                'config' => [
                    'display' => [
                        'title' => 'User Management',
                        'columns' => ['name', 'email', 'role', 'last_login']
                    ]
                ],
                'data_source' => [
                    'type' => 'query',
                    'query' => 'SELECT u.name, u.email, r.name as role, u.last_login_at as last_login FROM users u LEFT JOIN system_user_roles sur ON u.id = sur.user_id LEFT JOIN roles r ON sur.role_id = r.id WHERE u.tenant_id = {tenant_id} LIMIT 10'
                ],
                'permissions' => [
                    'roles' => ['system_admin']
                ],
                'description' => 'Quản lý người dùng'
            ],

            // Project Manager Widgets
            [
                'name' => 'Project Overview',
                'type' => 'card',
                'category' => 'overview',
                'config' => [
                    'display' => [
                        'title' => 'Project Overview',
                        'icon' => 'folder',
                        'color' => 'green'
                    ]
                ],
                'data_source' => [
                    'type' => 'static',
                    'data' => [
                        'total_tasks' => 156,
                        'completed' => 89,
                        'budget_used' => 67,
                        'timeline' => 'on_track'
                    ]
                ],
                'permissions' => [
                    'roles' => ['project_manager']
                ],
                'description' => 'Tổng quan dự án cho Project Manager'
            ],
            [
                'name' => 'Task Management',
                'type' => 'table',
                'category' => 'progress',
                'config' => [
                    'display' => [
                        'title' => 'Task Management',
                        'columns' => ['name', 'status', 'assignee', 'due_date']
                    ]
                ],
                'data_source' => [
                    'type' => 'query',
                    'query' => 'SELECT t.name, t.status, u.name as assignee, t.due_date FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.project_id IN (SELECT project_id FROM project_user_roles WHERE user_id = {user_id}) LIMIT 10'
                ],
                'permissions' => [
                    'roles' => ['project_manager']
                ],
                'description' => 'Quản lý tasks'
            ],
            [
                'name' => 'Budget Tracking',
                'type' => 'chart',
                'category' => 'budget',
                'config' => [
                    'display' => [
                        'title' => 'Budget vs Actual',
                        'chart_type' => 'line',
                        'x_axis' => 'month',
                        'y_axis' => 'amount'
                    ]
                ],
                'data_source' => [
                    'type' => 'static',
                    'data' => [
                        'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        'datasets' => [
                            [
                                'label' => 'Budget',
                                'data' => [100000, 200000, 300000, 400000, 500000, 600000]
                            ],
                            [
                                'label' => 'Actual',
                                'data' => [95000, 210000, 280000, 420000, 480000, 580000]
                            ]
                        ]
                    ]
                ],
                'permissions' => [
                    'roles' => ['project_manager']
                ],
                'description' => 'Theo dõi ngân sách'
            ],

            // Design Lead Widgets
            [
                'name' => 'Design Overview',
                'type' => 'card',
                'category' => 'overview',
                'config' => [
                    'display' => [
                        'title' => 'Design Overview',
                        'icon' => 'design',
                        'color' => 'purple'
                    ]
                ],
                'data_source' => [
                    'type' => 'static',
                    'data' => [
                        'drawings' => 45,
                        'rfis' => 12,
                        'submittals' => 8,
                        'reviews' => 3
                    ]
                ],
                'permissions' => [
                    'roles' => ['design_lead']
                ],
                'description' => 'Tổng quan thiết kế'
            ],
            [
                'name' => 'RFI Management',
                'type' => 'table',
                'category' => 'progress',
                'config' => [
                    'display' => [
                        'title' => 'RFI Management',
                        'columns' => ['title', 'status', 'assignee', 'due_date']
                    ]
                ],
                'data_source' => [
                    'type' => 'static',
                    'data' => [
                        ['title' => 'Foundation Design Query', 'status' => 'pending', 'assignee' => 'Design Lead', 'due_date' => '2024-01-15'],
                        ['title' => 'MEP Coordination', 'status' => 'in_progress', 'assignee' => 'MEP Engineer', 'due_date' => '2024-01-20']
                    ]
                ],
                'permissions' => [
                    'roles' => ['design_lead']
                ],
                'description' => 'Quản lý RFI'
            ],

            // Site Engineer Widgets
            [
                'name' => 'Site Overview',
                'type' => 'card',
                'category' => 'overview',
                'config' => [
                    'display' => [
                        'title' => 'Site Overview',
                        'icon' => 'construction',
                        'color' => 'orange'
                    ]
                ],
                'data_source' => [
                    'type' => 'static',
                    'data' => [
                        'daily_reports' => 15,
                        'photos_uploaded' => 23,
                        'inspections' => 8,
                        'weather' => 'sunny'
                    ]
                ],
                'permissions' => [
                    'roles' => ['site_engineer']
                ],
                'description' => 'Tổng quan hiện trường'
            ],
            [
                'name' => 'Daily Progress',
                'type' => 'chart',
                'category' => 'progress',
                'config' => [
                    'display' => [
                        'title' => 'Daily Progress',
                        'chart_type' => 'bar',
                        'x_axis' => 'date',
                        'y_axis' => 'percentage'
                    ]
                ],
                'data_source' => [
                    'type' => 'static',
                    'data' => [
                        'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                        'datasets' => [
                            [
                                'label' => 'Progress %',
                                'data' => [15, 25, 35, 45, 55, 65, 75]
                            ]
                        ]
                    ]
                ],
                'permissions' => [
                    'roles' => ['site_engineer']
                ],
                'description' => 'Tiến độ hàng ngày'
            ],

            // QC Inspector Widgets
            [
                'name' => 'Quality Overview',
                'type' => 'card',
                'category' => 'quality',
                'config' => [
                    'display' => [
                        'title' => 'Quality Overview',
                        'icon' => 'quality',
                        'color' => 'red'
                    ]
                ],
                'data_source' => [
                    'type' => 'static',
                    'data' => [
                        'inspections' => 24,
                        'ncrs' => 3,
                        'observations' => 7,
                        'quality_score' => 92
                    ]
                ],
                'permissions' => [
                    'roles' => ['qc_inspector']
                ],
                'description' => 'Tổng quan chất lượng'
            ],
            [
                'name' => 'Quality Trend',
                'type' => 'chart',
                'category' => 'quality',
                'config' => [
                    'display' => [
                        'title' => 'Quality Trend',
                        'chart_type' => 'line',
                        'x_axis' => 'date',
                        'y_axis' => 'score'
                    ]
                ],
                'data_source' => [
                    'type' => 'static',
                    'data' => [
                        'labels' => ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                        'datasets' => [
                            [
                                'label' => 'Quality Score',
                                'data' => [85, 88, 92, 95]
                            ]
                        ]
                    ]
                ],
                'permissions' => [
                    'roles' => ['qc_inspector']
                ],
                'description' => 'Xu hướng chất lượng'
            ],

            // Client Rep Widgets
            [
                'name' => 'Client Overview',
                'type' => 'card',
                'category' => 'overview',
                'config' => [
                    'display' => [
                        'title' => 'Project Status',
                        'icon' => 'client',
                        'color' => 'blue'
                    ]
                ],
                'data_source' => [
                    'type' => 'static',
                    'data' => [
                        'pending_crs' => 5,
                        'approvals_required' => 3,
                        'budget_status' => 'on_track',
                        'timeline_status' => 'on_track'
                    ]
                ],
                'permissions' => [
                    'roles' => ['client_rep']
                ],
                'description' => 'Tổng quan cho Client Rep'
            ],

            // Subcontractor Lead Widgets
            [
                'name' => 'Work Overview',
                'type' => 'card',
                'category' => 'overview',
                'config' => [
                    'display' => [
                        'title' => 'Work Overview',
                        'icon' => 'work',
                        'color' => 'green'
                    ]
                ],
                'data_source' => [
                    'type' => 'static',
                    'data' => [
                        'tasks_assigned' => 12,
                        'materials_submitted' => 8,
                        'progress_updates' => 85,
                        'quality_score' => 92
                    ]
                ],
                'permissions' => [
                    'roles' => ['subcontractor_lead']
                ],
                'description' => 'Tổng quan công việc cho Subcontractor'
            ]
        ];

        foreach ($widgets as $widgetData) {
            DashboardWidget::firstOrCreate(
                ['name' => $widgetData['name']],
                $widgetData
            );
        }
    }

    /**
     * Tạo dashboard metrics
     */
    private function createDashboardMetrics(): void
    {
        $metrics = [
            [
                'metric_code' => 'project.progress',
                'category' => 'project',
                'name' => 'Project Progress',
                'unit' => '%',
                'calculation_config' => [
                    'type' => 'simple',
                    'formula' => '(completed_tasks / total_tasks) * 100'
                ],
                'display_config' => [
                    'format' => 'percentage',
                    'color' => 'green',
                    'thresholds' => [
                        'warning' => 70,
                        'critical' => 50
                    ]
                ],
                'description' => 'Tỷ lệ hoàn thành dự án'
            ],
            [
                'metric_code' => 'budget.utilization',
                'category' => 'budget',
                'name' => 'Budget Utilization',
                'unit' => '%',
                'calculation_config' => [
                    'type' => 'simple',
                    'formula' => '(actual_cost / budget) * 100'
                ],
                'display_config' => [
                    'format' => 'percentage',
                    'color' => 'blue',
                    'thresholds' => [
                        'warning' => 80,
                        'critical' => 95
                    ]
                ],
                'description' => 'Tỷ lệ sử dụng ngân sách'
            ],
            [
                'metric_code' => 'quality.score',
                'category' => 'quality',
                'name' => 'Quality Score',
                'unit' => 'points',
                'calculation_config' => [
                    'type' => 'aggregate',
                    'formula' => 'average(inspection_scores)'
                ],
                'display_config' => [
                    'format' => 'number',
                    'color' => 'purple',
                    'thresholds' => [
                        'warning' => 80,
                        'critical' => 70
                    ]
                ],
                'description' => 'Điểm chất lượng tổng thể'
            ],
            [
                'metric_code' => 'safety.incident_rate',
                'category' => 'safety',
                'name' => 'Safety Incident Rate',
                'unit' => '%',
                'calculation_config' => [
                    'type' => 'simple',
                    'formula' => '(incidents / total_work_hours) * 100'
                ],
                'display_config' => [
                    'format' => 'percentage',
                    'color' => 'red',
                    'thresholds' => [
                        'warning' => 2,
                        'critical' => 5
                    ]
                ],
                'description' => 'Tỷ lệ tai nạn lao động'
            ],
            [
                'metric_code' => 'schedule.performance',
                'category' => 'schedule',
                'name' => 'Schedule Performance Index',
                'unit' => 'ratio',
                'calculation_config' => [
                    'type' => 'formula',
                    'formula' => 'earned_value / planned_value'
                ],
                'display_config' => [
                    'format' => 'decimal',
                    'color' => 'orange',
                    'thresholds' => [
                        'warning' => 0.9,
                        'critical' => 0.8
                    ]
                ],
                'description' => 'Chỉ số hiệu suất tiến độ'
            ],
            [
                'metric_code' => 'resource.utilization',
                'category' => 'resource',
                'name' => 'Resource Utilization',
                'unit' => '%',
                'calculation_config' => [
                    'type' => 'simple',
                    'formula' => '(actual_hours / planned_hours) * 100'
                ],
                'display_config' => [
                    'format' => 'percentage',
                    'color' => 'teal',
                    'thresholds' => [
                        'warning' => 90,
                        'critical' => 110
                    ]
                ],
                'description' => 'Tỷ lệ sử dụng tài nguyên'
            ]
        ];

        foreach ($metrics as $metricData) {
            DashboardMetric::firstOrCreate(
                ['metric_code' => $metricData['metric_code']],
                $metricData
            );
        }
    }
}
