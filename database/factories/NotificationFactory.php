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
        $priorities = ['critical', 'normal', 'low'];
        $channels = ['inapp', 'email', 'webhook'];
        $types = ['task_assigned', 'project_update', 'deadline_reminder', 'system_alert', 'comment_added'];

        return [
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'user_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'type' => $this->faker->randomElement($types),
            'priority' => $this->faker->randomElement($priorities),
            'title' => $this->faker->sentence(4),
            'body' => $this->faker->paragraph(2),
            'link_url' => $this->faker->optional(0.7)->url(),
            'channel' => $this->faker->randomElement($channels),
            'read_at' => $this->faker->optional(0.3)->dateTimeBetween('-1 week', 'now'),
            'data' => [
                'action' => $this->faker->randomElement(['view', 'edit', 'approve', 'reject']),
                'entity_type' => $this->faker->randomElement(['task', 'project', 'document', 'rfi']),
                'entity_id' => $this->faker->uuid(),
            ],
            'metadata' => [
                'source' => $this->faker->randomElement(['system', 'user', 'api']),
                'tags' => $this->faker->words(3),
                'priority_score' => $this->faker->numberBetween(1, 10),
            ],
            'event_key' => $this->faker->optional(0.8)->slug(3),
            'project_id' => $this->faker->optional(0.6)->randomElement(Project::pluck('id')->toArray()),
        ];
    }

    /**
     * Indicate that the notification is critical.
     */
    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'critical',
            'channel' => 'inapp',
        ]);
    }

    /**
     * Indicate that the notification is unread.
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => null,
        ]);
    }

    /**
     * Indicate that the notification is read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Indicate that the notification is for email channel.
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => 'email',
        ]);
    }

    /**
     * Indicate that the notification is for webhook channel.
     */
    public function webhook(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => 'webhook',
        ]);
    }

    /**
     * Indicate that the notification is for in-app channel.
     */
    public function inapp(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => 'inapp',
        ]);
    }

    /**
     * Indicate that the notification is for a specific project.
     */
    public function forProject(Project $project): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $project->id,
            'tenant_id' => $project->tenant_id,
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
