<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class AnalysisService
{
    /**
     * Get analysis data for the current context
     */
    public function getAnalysis(string $context, array $filters = []): array
    {
        $tenantId = Auth::user()->tenant_id ?? 'default';
        $cacheKey = "analysis_{$context}_{$tenantId}_" . md5(serialize($filters));
        
        return Cache::remember($cacheKey, 60, function () use ($context, $filters, $tenantId) {
            return $this->generateAnalysis($context, $filters, $tenantId);
        });
    }
    
    /**
     * Generate analysis data
     */
    private function generateAnalysis(string $context, array $filters, string $tenantId): array
    {
        $isAdmin = Auth::user()->hasRole('super_admin');
        
        switch ($context) {
            case 'projects':
                return $this->getProjectAnalysis($filters, $tenantId);
            case 'tasks':
                return $this->getTaskAnalysis($filters, $tenantId);
            case 'documents':
                return $this->getDocumentAnalysis($filters, $tenantId);
            case 'users':
                if ($isAdmin) {
                    return $this->getUserAnalysis($filters);
                }
                break;
            case 'tenants':
                if ($isAdmin) {
                    return $this->getTenantAnalysis($filters);
                }
                break;
            case 'overview':
                return $this->getOverviewAnalysis($filters, $tenantId, $isAdmin);
        }
        
        return [];
    }
    
    /**
     * Get project analysis
     */
    private function getProjectAnalysis(array $filters, string $tenantId): array
    {
        $projects = $this->getMockProjects($tenantId);
        
        return [
            'charts' => [
                [
                    'id' => 'project_status',
                    'title' => 'Project Status Distribution',
                    'type' => 'doughnut',
                    'data' => [
                        'labels' => ['Active', 'Planning', 'On Hold', 'Completed', 'Cancelled'],
                        'datasets' => [
                            [
                                'data' => [8, 3, 2, 12, 1],
                                'backgroundColor' => ['#10B981', '#F59E0B', '#EF4444', '#3B82F6', '#6B7280']
                            ]
                        ]
                    ]
                ],
                [
                    'id' => 'project_progress',
                    'title' => 'Project Progress Over Time',
                    'type' => 'line',
                    'data' => [
                        'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        'datasets' => [
                            [
                                'label' => 'Average Progress',
                                'data' => [20, 35, 45, 60, 75, 85],
                                'borderColor' => '#3B82F6',
                                'backgroundColor' => 'rgba(59, 130, 246, 0.1)'
                            ]
                        ]
                    ]
                ],
                [
                    'id' => 'project_health',
                    'title' => 'Project Health Status',
                    'type' => 'bar',
                    'data' => [
                        'labels' => ['Good', 'At Risk', 'Critical'],
                        'datasets' => [
                            [
                                'label' => 'Projects',
                                'data' => [15, 8, 2],
                                'backgroundColor' => ['#10B981', '#F59E0B', '#EF4444']
                            ]
                        ]
                    ]
                ]
            ],
            'metrics' => [
                [
                    'title' => 'Total Projects',
                    'value' => '26',
                    'change' => '+3',
                    'changeType' => 'positive',
                    'description' => 'vs last month'
                ],
                [
                    'title' => 'Active Projects',
                    'value' => '8',
                    'change' => '+1',
                    'changeType' => 'positive',
                    'description' => 'vs last month'
                ],
                [
                    'title' => 'Average Progress',
                    'value' => '68%',
                    'change' => '+5%',
                    'changeType' => 'positive',
                    'description' => 'vs last month'
                ],
                [
                    'title' => 'At-Risk Projects',
                    'value' => '8',
                    'change' => '-2',
                    'changeType' => 'positive',
                    'description' => 'vs last month'
                ]
            ],
            'insights' => [
                'Project completion rate has improved by 15% this month',
                '3 projects are approaching their deadlines',
                'Budget utilization is at 78% across all projects',
                'Team productivity has increased by 12%'
            ]
        ];
    }
    
    /**
     * Get task analysis
     */
    private function getTaskAnalysis(array $filters, string $tenantId): array
    {
        return [
            'charts' => [
                [
                    'id' => 'task_status',
                    'title' => 'Task Status Distribution',
                    'type' => 'pie',
                    'data' => [
                        'labels' => ['Pending', 'In Progress', 'Completed', 'Cancelled'],
                        'datasets' => [
                            [
                                'data' => [25, 40, 30, 5],
                                'backgroundColor' => ['#F59E0B', '#3B82F6', '#10B981', '#6B7280']
                            ]
                        ]
                    ]
                ],
                [
                    'id' => 'task_priority',
                    'title' => 'Tasks by Priority',
                    'type' => 'bar',
                    'data' => [
                        'labels' => ['Low', 'Medium', 'High', 'Urgent'],
                        'datasets' => [
                            [
                                'label' => 'Tasks',
                                'data' => [15, 35, 25, 10],
                                'backgroundColor' => ['#6B7280', '#F59E0B', '#EF4444', '#DC2626']
                            ]
                        ]
                    ]
                ],
                [
                    'id' => 'task_completion_trend',
                    'title' => 'Task Completion Trend',
                    'type' => 'line',
                    'data' => [
                        'labels' => ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                        'datasets' => [
                            [
                                'label' => 'Completed Tasks',
                                'data' => [12, 18, 22, 28],
                                'borderColor' => '#10B981',
                                'backgroundColor' => 'rgba(16, 185, 129, 0.1)'
                            ]
                        ]
                    ]
                ]
            ],
            'metrics' => [
                [
                    'title' => 'Total Tasks',
                    'value' => '100',
                    'change' => '+8',
                    'changeType' => 'positive',
                    'description' => 'vs last week'
                ],
                [
                    'title' => 'Completed Tasks',
                    'value' => '30',
                    'change' => '+5',
                    'changeType' => 'positive',
                    'description' => 'vs last week'
                ],
                [
                    'title' => 'Overdue Tasks',
                    'value' => '5',
                    'change' => '-2',
                    'changeType' => 'positive',
                    'description' => 'vs last week'
                ],
                [
                    'title' => 'Average Completion Time',
                    'value' => '3.2 days',
                    'change' => '-0.5 days',
                    'changeType' => 'positive',
                    'description' => 'vs last week'
                ]
            ],
            'insights' => [
                'Task completion rate is 30% this week',
                '5 tasks are overdue and need attention',
                'High priority tasks are being completed faster',
                'Team is on track to meet weekly goals'
            ]
        ];
    }
    
    /**
     * Get document analysis
     */
    private function getDocumentAnalysis(array $filters, string $tenantId): array
    {
        return [
            'charts' => [
                [
                    'id' => 'document_types',
                    'title' => 'Document Types',
                    'type' => 'doughnut',
                    'data' => [
                        'labels' => ['PDF', 'Word', 'Excel', 'PowerPoint', 'Images', 'Other'],
                        'datasets' => [
                            [
                                'data' => [35, 25, 15, 10, 10, 5],
                                'backgroundColor' => ['#EF4444', '#3B82F6', '#10B981', '#F59E0B', '#8B5CF6', '#6B7280']
                            ]
                        ]
                    ]
                ],
                [
                    'id' => 'document_status',
                    'title' => 'Document Status',
                    'type' => 'bar',
                    'data' => [
                        'labels' => ['Draft', 'Pending Approval', 'Approved', 'Archived'],
                        'datasets' => [
                            [
                                'label' => 'Documents',
                                'data' => [20, 15, 50, 10],
                                'backgroundColor' => ['#F59E0B', '#3B82F6', '#10B981', '#6B7280']
                            ]
                        ]
                    ]
                ],
                [
                    'id' => 'document_uploads',
                    'title' => 'Document Uploads Over Time',
                    'type' => 'line',
                    'data' => [
                        'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        'datasets' => [
                            [
                                'label' => 'Uploads',
                                'data' => [15, 22, 18, 25, 30, 28],
                                'borderColor' => '#3B82F6',
                                'backgroundColor' => 'rgba(59, 130, 246, 0.1)'
                            ]
                        ]
                    ]
                ]
            ],
            'metrics' => [
                [
                    'title' => 'Total Documents',
                    'value' => '95',
                    'change' => '+12',
                    'changeType' => 'positive',
                    'description' => 'vs last month'
                ],
                [
                    'title' => 'Pending Approval',
                    'value' => '15',
                    'change' => '+3',
                    'changeType' => 'negative',
                    'description' => 'vs last month'
                ],
                [
                    'title' => 'Storage Used',
                    'value' => '2.3 GB',
                    'change' => '+0.5 GB',
                    'changeType' => 'neutral',
                    'description' => 'vs last month'
                ],
                [
                    'title' => 'Average File Size',
                    'value' => '24 MB',
                    'change' => '-2 MB',
                    'changeType' => 'positive',
                    'description' => 'vs last month'
                ]
            ],
            'insights' => [
                'Document uploads have increased by 40% this month',
                '15 documents are pending approval',
                'PDF documents make up 35% of all files',
                'Storage usage is within acceptable limits'
            ]
        ];
    }
    
    /**
     * Get user analysis (admin only)
     */
    private function getUserAnalysis(array $filters): array
    {
        return [
            'charts' => [
                [
                    'id' => 'user_roles',
                    'title' => 'User Roles Distribution',
                    'type' => 'pie',
                    'data' => [
                        'labels' => ['Developers', 'Project Managers', 'Designers', 'Clients', 'Admins'],
                        'datasets' => [
                            [
                                'data' => [45, 20, 15, 15, 5],
                                'backgroundColor' => ['#3B82F6', '#10B981', '#F59E0B', '#8B5CF6', '#EF4444']
                            ]
                        ]
                    ]
                ],
                [
                    'id' => 'user_activity',
                    'title' => 'User Activity Over Time',
                    'type' => 'line',
                    'data' => [
                        'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        'datasets' => [
                            [
                                'label' => 'Active Users',
                                'data' => [120, 135, 142, 158, 165, 172],
                                'borderColor' => '#10B981',
                                'backgroundColor' => 'rgba(16, 185, 129, 0.1)'
                            ]
                        ]
                    ]
                ]
            ],
            'metrics' => [
                [
                    'title' => 'Total Users',
                    'value' => '1,247',
                    'change' => '+23',
                    'changeType' => 'positive',
                    'description' => 'vs last month'
                ],
                [
                    'title' => 'Active Users',
                    'value' => '172',
                    'change' => '+8',
                    'changeType' => 'positive',
                    'description' => 'vs last month'
                ],
                [
                    'title' => 'New Registrations',
                    'value' => '23',
                    'change' => '+5',
                    'changeType' => 'positive',
                    'description' => 'vs last month'
                ],
                [
                    'title' => 'User Satisfaction',
                    'value' => '4.8/5',
                    'change' => '+0.2',
                    'changeType' => 'positive',
                    'description' => 'vs last month'
                ]
            ],
            'insights' => [
                'User growth rate is 12% month-over-month',
                'Developer role is the most common (45%)',
                'User satisfaction score is excellent',
                'Active user rate is 13.8%'
            ]
        ];
    }
    
    /**
     * Get tenant analysis (admin only)
     */
    private function getTenantAnalysis(array $filters): array
    {
        return [
            'charts' => [
                [
                    'id' => 'tenant_plans',
                    'title' => 'Tenant Plans Distribution',
                    'type' => 'doughnut',
                    'data' => [
                        'labels' => ['Free', 'Professional', 'Enterprise', 'Custom'],
                        'datasets' => [
                            [
                                'data' => [15, 25, 8, 2],
                                'backgroundColor' => ['#6B7280', '#3B82F6', '#10B981', '#F59E0B']
                            ]
                        ]
                    ]
                ],
                [
                    'id' => 'tenant_growth',
                    'title' => 'Tenant Growth Over Time',
                    'type' => 'line',
                    'data' => [
                        'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        'datasets' => [
                            [
                                'label' => 'New Tenants',
                                'data' => [3, 5, 4, 7, 6, 8],
                                'borderColor' => '#3B82F6',
                                'backgroundColor' => 'rgba(59, 130, 246, 0.1)'
                            ]
                        ]
                    ]
                ]
            ],
            'metrics' => [
                [
                    'title' => 'Total Tenants',
                    'value' => '50',
                    'change' => '+8',
                    'changeType' => 'positive',
                    'description' => 'vs last month'
                ],
                [
                    'title' => 'Active Tenants',
                    'value' => '45',
                    'change' => '+3',
                    'changeType' => 'positive',
                    'description' => 'vs last month'
                ],
                [
                    'title' => 'Enterprise Tenants',
                    'value' => '8',
                    'change' => '+2',
                    'changeType' => 'positive',
                    'description' => 'vs last month'
                ],
                [
                    'title' => 'Average Revenue',
                    'value' => '$2,450',
                    'change' => '+$150',
                    'changeType' => 'positive',
                    'description' => 'vs last month'
                ]
            ],
            'insights' => [
                'Tenant growth rate is 16% month-over-month',
                'Enterprise plan adoption is increasing',
                'Average revenue per tenant is growing',
                'Tenant retention rate is 90%'
            ]
        ];
    }
    
    /**
     * Get overview analysis
     */
    private function getOverviewAnalysis(array $filters, string $tenantId, bool $isAdmin): array
    {
        $baseAnalysis = [
            'charts' => [
                [
                    'id' => 'overview_trends',
                    'title' => 'Key Metrics Trends',
                    'type' => 'line',
                    'data' => [
                        'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        'datasets' => [
                            [
                                'label' => 'Projects',
                                'data' => [15, 18, 22, 25, 28, 26],
                                'borderColor' => '#3B82F6',
                                'backgroundColor' => 'rgba(59, 130, 246, 0.1)'
                            ],
                            [
                                'label' => 'Tasks',
                                'data' => [80, 95, 110, 125, 140, 100],
                                'borderColor' => '#10B981',
                                'backgroundColor' => 'rgba(16, 185, 129, 0.1)'
                            ],
                            [
                                'label' => 'Documents',
                                'data' => [60, 70, 80, 85, 90, 95],
                                'borderColor' => '#F59E0B',
                                'backgroundColor' => 'rgba(245, 158, 11, 0.1)'
                            ]
                        ]
                    ]
                ]
            ],
            'metrics' => [
                [
                    'title' => 'Total Projects',
                    'value' => '26',
                    'change' => '+3',
                    'changeType' => 'positive',
                    'description' => 'vs last month'
                ],
                [
                    'title' => 'Active Tasks',
                    'value' => '100',
                    'change' => '+8',
                    'changeType' => 'positive',
                    'description' => 'vs last week'
                ],
                [
                    'title' => 'Documents',
                    'value' => '95',
                    'change' => '+12',
                    'changeType' => 'positive',
                    'description' => 'vs last month'
                ],
                [
                    'title' => 'Team Members',
                    'value' => '8',
                    'change' => '+1',
                    'changeType' => 'positive',
                    'description' => 'vs last month'
                ]
            ],
            'insights' => [
                'Overall productivity has increased by 15%',
                'Project completion rate is improving',
                'Team collaboration is at an all-time high',
                'Document management is efficient'
            ]
        ];
        
        if ($isAdmin) {
            $baseAnalysis['metrics'][] = [
                'title' => 'Total Users',
                'value' => '1,247',
                'change' => '+23',
                'changeType' => 'positive',
                'description' => 'vs last month'
            ];
            
            $baseAnalysis['metrics'][] = [
                'title' => 'Active Tenants',
                'value' => '45',
                'change' => '+3',
                'changeType' => 'positive',
                'description' => 'vs last month'
            ];
        }
        
        return $baseAnalysis;
    }
    
    // Mock data methods
    
    private function getMockProjects(string $tenantId): array
    {
        return [
            ['id' => 1, 'name' => 'Website Redesign', 'status' => 'active', 'progress' => 75],
            ['id' => 2, 'name' => 'Mobile App', 'status' => 'planning', 'progress' => 25],
            ['id' => 3, 'name' => 'Database Migration', 'status' => 'completed', 'progress' => 100]
        ];
    }
}
