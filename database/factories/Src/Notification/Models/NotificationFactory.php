<?php declare(strict_types=1);

namespace Database\Factories\Src\Notification\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Notification\Models\Notification;
use App\Models\User;

/**
 * Factory cho Notification model
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Src\Notification\Models\Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        $types = ['task_assigned', 'project_update', 'deadline_reminder', 'system_alert', 'comment_added'];

        return [
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement($types),
            'priority' => $this->faker->randomElement(Notification::VALID_PRIORITIES),
            'title' => $this->faker->sentence(4),
            'body' => $this->faker->paragraph(2),
            'link_url' => $this->faker->optional(0.6)->url(),
            'channel' => $this->faker->randomElement(Notification::VALID_CHANNELS),
            'read_at' => $this->faker->optional(0.4)->dateTimeBetween('-1 week', 'now'),
        ];
    }

    /**
     * State: Critical notification
     */
    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => Notification::PRIORITY_CRITICAL,
        ]);
    }

    /**
     * State: Unread notification
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => null,
        ]);
    }

    /**
     * State: Email notification
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => Notification::CHANNEL_EMAIL,
        ]);
    }

    /**
     * State: For specific user
     */
    public function forUser(string $userId): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $userId,
        ]);
    }
}
