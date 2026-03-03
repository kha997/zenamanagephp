<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskDependencyFactory extends Factory
{
    protected $model = TaskDependency::class;

    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'dependency_id' => Task::factory(),
            'tenant_id' => Tenant::factory(),
        ];
    }
}
