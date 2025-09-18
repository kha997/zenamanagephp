<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\TaskDependency;

class TaskDependencyFactory extends Factory
{
    protected $model = TaskDependency::class;

    public function definition(): array
    {
        return [
            'task_id' => \Src\CoreProject\Models\Task::factory(),
            'dependency_id' => \Src\CoreProject\Models\Task::factory(),
            'tenant_id' => \App\Models\Tenant::factory(),
        ];
    }
}
