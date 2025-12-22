<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProjectHealthSnapshot>
 */
class ProjectHealthSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => \App\Models\Tenant::factory(),
            'project_id' => \App\Models\Project::factory(),
            'snapshot_date' => now()->toDateString(),
            'schedule_status' => 'on_track',
            'cost_status' => 'on_budget',
            'overall_status' => 'good',
            'tasks_completion_rate' => 0.75,
            'blocked_tasks_ratio' => 0.1,
            'overdue_tasks' => 0,
        ];
    }
}
