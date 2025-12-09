<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $modules = ['tasks', 'documents', 'cost', 'rbac', 'system'];
        $types = [
            'task.assigned',
            'task.completed',
            'document.uploaded',
            'document.approved',
            'co.needs_approval',
            'co.approved',
            'system.alert',
            'rbac.permission_changed',
        ];
        $entityTypes = ['task', 'document', 'change_order', 'project', 'user'];

        return [
            'id' => \Illuminate\Support\Str::ulid(),
            'user_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'module' => $this->faker->randomElement($modules),
            'type' => $this->faker->randomElement($types),
            'title' => $this->faker->sentence(4),
            'message' => $this->faker->paragraph(2),
            'entity_type' => $this->faker->optional(0.7)->randomElement($entityTypes),
            'entity_id' => $this->faker->optional(0.7)->passthrough(\Illuminate\Support\Str::ulid()),
            'is_read' => $this->faker->boolean(30), // 30% chance of being read
            'metadata' => [
                'source' => $this->faker->randomElement(['system', 'user', 'api']),
                'tags' => $this->faker->words(3),
            ],
        ];
    }

    /**
     * Indicate that the notification is unread.
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => false,
        ]);
    }

    /**
     * Indicate that the notification is read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => true,
        ]);
    }

    /**
     * Indicate that the notification is for tasks module.
     */
    public function tasks(): static
    {
        return $this->state(fn (array $attributes) => [
            'module' => 'tasks',
            'type' => $this->faker->randomElement(['task.assigned', 'task.completed', 'task.updated']),
        ]);
    }

    /**
     * Indicate that the notification is for documents module.
     */
    public function documents(): static
    {
        return $this->state(fn (array $attributes) => [
            'module' => 'documents',
            'type' => $this->faker->randomElement(['document.uploaded', 'document.approved', 'document.rejected']),
        ]);
    }

    /**
     * Indicate that the notification is for cost module.
     */
    public function cost(): static
    {
        return $this->state(fn (array $attributes) => [
            'module' => 'cost',
            'type' => $this->faker->randomElement(['co.needs_approval', 'co.approved', 'co.rejected']),
        ]);
    }

    /**
     * Indicate that the notification is for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);
    }
}