<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DashboardWidget;

class DashboardWidgetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $widgets = [
            [
                'id' => '01k7ht0000000000000000001',
                'name' => 'Project Overview',
                'type' => 'metric',
                'category' => 'kpi',
                'description' => 'Shows total number of projects',
                'config' => [
                    'data_source' => 'projects',
                    'calculation' => 'count',
                    'format' => 'number'
                ],
                'permissions' => ['view_projects'],
                'is_active' => true,
            ],
            [
                'id' => '01k7ht0000000000000000002',
                'name' => 'Task Progress Chart',
                'type' => 'chart',
                'category' => 'analytics',
                'description' => 'Shows task completion progress over time',
                'config' => [
                    'chart_type' => 'line',
                    'data_source' => 'tasks',
                    'time_range' => '30_days'
                ],
                'permissions' => ['view_tasks'],
                'is_active' => true,
            ],
            [
                'id' => '01k7ht0000000000000000003',
                'name' => 'Recent Activities',
                'type' => 'table',
                'category' => 'reports',
                'description' => 'Shows recent project activities',
                'config' => [
                    'data_source' => 'activities',
                    'limit' => 10,
                    'columns' => ['activity', 'user', 'timestamp']
                ],
                'permissions' => ['view_activities'],
                'is_active' => true,
            ],
            [
                'id' => '01k7ht0000000000000000004',
                'name' => 'Budget Summary',
                'type' => 'metric',
                'category' => 'financial',
                'description' => 'Shows total budget and spending',
                'config' => [
                    'data_source' => 'budgets',
                    'calculation' => 'sum',
                    'format' => 'currency'
                ],
                'permissions' => ['view_budgets'],
                'is_active' => true,
            ],
            [
                'id' => '01k7ht0000000000000000005',
                'name' => 'Team Performance',
                'type' => 'chart',
                'category' => 'analytics',
                'description' => 'Shows team performance metrics',
                'config' => [
                    'chart_type' => 'bar',
                    'data_source' => 'team_performance',
                    'group_by' => 'team'
                ],
                'permissions' => ['view_team_performance'],
                'is_active' => true,
            ]
        ];

        foreach ($widgets as $widget) {
            DashboardWidget::updateOrCreate(
                ['id' => $widget['id']],
                $widget
            );
        }
    }
}