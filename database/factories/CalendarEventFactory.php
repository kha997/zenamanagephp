<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\CalendarEvent;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CalendarEvent>
 */
class CalendarEventFactory extends Factory
{
    protected $model = CalendarEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = $this->faker->dateTimeBetween('now', '+30 days');
        $endTime = clone $startTime;
        $endTime->modify('+' . $this->faker->numberBetween(1, 4) . ' hours');

        return [
            'id' => Str::ulid(),
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'project_id' => null,
            'task_id' => null,
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'location' => $this->faker->optional()->address(),
            'attendees' => $this->faker->optional()->randomElements(
                User::factory()->count(3)->make()->pluck('id')->toArray(),
                $this->faker->numberBetween(1, 3)
            ),
            'status' => $this->faker->randomElement(['confirmed', 'tentative', 'cancelled']),
            'all_day' => $this->faker->boolean(20), // 20% chance of all-day events
            'recurrence' => $this->faker->optional()->randomElement([
                ['type' => 'daily', 'interval' => 1],
                ['type' => 'weekly', 'interval' => 1, 'days' => ['monday', 'wednesday', 'friday']],
                ['type' => 'monthly', 'interval' => 1]
            ]),
            'metadata' => $this->faker->optional()->randomElements([
                'meeting_type' => $this->faker->randomElement(['standup', 'review', 'planning', 'retrospective']),
                'priority' => $this->faker->randomElement(['low', 'medium', 'high']),
                'category' => $this->faker->randomElement(['work', 'personal', 'team', 'client'])
            ]),
            'is_synced' => $this->faker->boolean(30), // 30% chance of synced events
            'last_synced_at' => $this->faker->optional()->dateTimeBetween('-1 week', 'now'),
        ];
    }

    /**
     * Indicate that the event is a meeting.
     */
    public function meeting(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => $this->faker->randomElement([
                'Team Standup',
                'Project Review',
                'Client Meeting',
                'Sprint Planning',
                'Code Review',
                'Design Review'
            ]),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'meeting_type' => $this->faker->randomElement(['standup', 'review', 'planning']),
                'category' => 'work'
            ])
        ]);
    }

    /**
     * Indicate that the event is a deadline.
     */
    public function deadline(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => $this->faker->randomElement([
                'Project Deadline',
                'Task Due Date',
                'Milestone Completion',
                'Client Delivery',
                'Code Freeze'
            ]),
            'all_day' => true,
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'category' => 'deadline',
                'priority' => $this->faker->randomElement(['high', 'medium'])
            ])
        ]);
    }

    /**
     * Indicate that the event is associated with a project.
     */
    public function forProject(Project $project): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $project->id,
            'tenant_id' => $project->tenant_id,
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'project_name' => $project->name,
                'project_code' => $project->code
            ])
        ]);
    }

    /**
     * Indicate that the event is associated with a task.
     */
    public function forTask(Task $task): static
    {
        return $this->state(fn (array $attributes) => [
            'task_id' => $task->id,
            'project_id' => $task->project_id,
            'tenant_id' => $task->tenant_id,
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'task_name' => $task->name,
                'task_status' => $task->status
            ])
        ]);
    }

    /**
     * Indicate that the event is all day.
     */
    public function allDay(): static
    {
        return $this->state(fn (array $attributes) => [
            'all_day' => true,
            'start_time' => $this->faker->dateTimeBetween('now', '+30 days')->setTime(0, 0, 0),
            'end_time' => $this->faker->dateTimeBetween('now', '+30 days')->setTime(23, 59, 59),
        ]);
    }

    /**
     * Indicate that the event is recurring.
     */
    public function recurring(): static
    {
        return $this->state(fn (array $attributes) => [
            'recurrence' => $this->faker->randomElement([
                ['type' => 'daily', 'interval' => 1, 'end_date' => $this->faker->dateTimeBetween('+1 month', '+3 months')->format('Y-m-d')],
                ['type' => 'weekly', 'interval' => 1, 'days' => ['monday', 'wednesday', 'friday'], 'end_date' => $this->faker->dateTimeBetween('+1 month', '+3 months')->format('Y-m-d')],
                ['type' => 'monthly', 'interval' => 1, 'end_date' => $this->faker->dateTimeBetween('+3 months', '+6 months')->format('Y-m-d')]
            ])
        ]);
    }

    /**
     * Indicate that the event is synced with external calendar.
     */
    public function synced(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_synced' => true,
            'external_event_id' => $this->faker->uuid(),
            'last_synced_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }
}
