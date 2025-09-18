<?php

namespace Database\Factories;

use App\Models\ZenaNotification;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ZenaNotification>
 */
class ZenaNotificationFactory extends Factory
{
    protected $model = ZenaNotification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'type' => $this->faker->randomElement(['task_assigned', 'task_completed', 'project_updated', 'rfi_submitted', 'change_request_approved']),
            'title' => $this->faker->sentence(3),
            'message' => $this->faker->paragraph(),
            'data' => [
                'project_id' => $this->faker->uuid(),
                'task_id' => $this->faker->uuid(),
                'action' => $this->faker->randomElement(['created', 'updated', 'deleted', 'assigned'])
            ],
            'is_read' => $this->faker->boolean(30), // 30% chance of being read
        ];
    }

    /**
     * Indicate that the notification is unread.
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    /**
     * Indicate that the notification is read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => true,
            'read_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Indicate that the notification is high priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
            'type' => $this->faker->randomElement(['task_overdue', 'project_delay', 'urgent_rfi']),
        ]);
    }

    /**
     * Indicate that the notification is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $this->faker->dateTimeBetween('-1 month', '-1 day'),
        ]);
    }

    /**
     * Create a task assignment notification.
     */
    public function taskAssigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'task_assigned',
            'title' => 'New Task Assignment',
            'message' => 'You have been assigned a new task',
            'priority' => 'medium',
        ]);
    }

    /**
     * Create a project update notification.
     */
    public function projectUpdated(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'project_updated',
            'title' => 'Project Updated',
            'message' => 'A project you are involved in has been updated',
            'priority' => 'low',
        ]);
    }
}