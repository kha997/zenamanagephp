<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Task;
use App\Models\Project;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Schema;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->sentence(4);

        $data = [
            'id' => $this->faker->unique()->regexify('[0-9A-Za-z]{26}'),
            'tenant_id' => null,
            'project_id' => Project::factory(),
            'parent_id' => null,
            'name' => $title,
            'title' => $title,
            'description' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement(['pending', 'in_progress', 'completed', 'cancelled', 'on_hold']),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'urgent']),
            'is_hidden' => false,
            'estimated_hours' => $this->faker->randomFloat(2, 1, 40),
            'actual_hours' => $this->faker->randomFloat(2, 0, 60),
            'estimated_cost' => 0,
            'actual_cost' => 0,
            'progress_percent' => $this->faker->numberBetween(0, 100),
            'visibility' => $this->faker->randomElement(['team', 'private', 'public']),
            'client_approved' => false,
            'assignee_id' => User::factory(),
            'created_by' => User::factory(),
            'start_date' => $this->faker->dateTimeBetween('-1 month', '+1 month'),
            'end_date' => $this->faker->dateTimeBetween('+1 month', '+6 months'),
            'tags' => $this->faker->words(2),
            'watchers' => [],
            'dependency_ids' => [],
            'order' => $this->faker->numberBetween(1, 50),
        ];

        return $this->filterTaskAttributes($data);
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Task $task) {
            $this->alignTenantWithProject($task);
        })->afterCreating(function (Task $task) {
            $this->alignTenantWithProject($task);
        });
    }

    private function filterTaskAttributes(array $attributes): array
    {
        if (! Schema::hasTable('tasks')) {
            return $attributes;
        }

        $columns = Schema::getColumnListing('tasks');

        return array_intersect_key($attributes, array_flip($columns));
    }

    private function alignTenantWithProject(Task $task): void
    {
        if (!empty($task->tenant_id)) {
            return;
        }

        $tenantId = null;

        if (!empty($task->project_id)) {
            $project = Project::withoutGlobalScopes()->find($task->project_id);

            if ($project) {
                $tenantId = $project->tenant_id;
            }
        }

        if (empty($tenantId)) {
            $tenantId = Tenant::factory()->create()->id;
        }

        $task->tenant_id = $tenantId;

        if ($task->exists) {
            $task->saveQuietly();
        }
    }

    /**
     * Indicate that the task is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => $this->filterTaskAttributes([
            'status' => 'pending',
            'progress_percent' => 0,
        ]));
    }

    /**
     * Indicate that the task is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => $this->filterTaskAttributes([
            'status' => 'in_progress',
            'progress_percent' => $this->faker->numberBetween(10, 90),
        ]));
    }

    /**
     * Indicate that the task is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => $this->filterTaskAttributes([
            'status' => 'completed',
            'progress_percent' => 100,
            'last_activity_at' => now(),
        ]));
    }

    /**
     * Indicate that the task is overdue.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => $this->filterTaskAttributes([
            'status' => 'in_progress',
            'end_date' => $this->faker->dateTimeBetween('-1 month', '-1 day'),
        ]));
    }

    /**
     * Indicate that the task has high priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => $this->filterTaskAttributes([
            'priority' => 'high',
        ]));
    }

    /**
     * Indicate that the task has low priority.
     */
    public function lowPriority(): static
    {
        return $this->state(fn (array $attributes) => $this->filterTaskAttributes([
            'priority' => 'low',
        ]));
    }

    /**
     * Indicate that the task is a subtask.
     */
    public function subtask(): static
    {
        return $this->state(fn (array $attributes) => $this->filterTaskAttributes([
            'parent_id' => Task::factory(),
        ]));
    }

    /**
     * Indicate that the task is hidden.
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => $this->filterTaskAttributes([
            'is_hidden' => true,
        ]));
    }

    /**
     * Indicate that the task is client approved.
     */
    public function clientApproved(): static
    {
        return $this->state(fn (array $attributes) => $this->filterTaskAttributes([
            'client_approved' => true,
        ]));
    }
}
