<?php declare(strict_types=1);

namespace Database\Factories\Src\Notification\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Notification\Models\NotificationRule;
use Src\Notification\Models\Notification;
use Src\CoreProject\Models\Project;
use Illuminate\Support\Str;
use App\Models\User;

/**
 * Factory cho NotificationRule model
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Src\Notification\Models\NotificationRule>
 */
class NotificationRuleFactory extends Factory
{
    protected $model = NotificationRule::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'user_id' => User::factory(),
            'tenant_id' => function (array $attributes) {
                $user = User::find($attributes['user_id']);
                return $user ? $user->tenant_id : null;
            },
            'project_id' => $this->faker->optional(0.6)->randomElement([null, Project::factory()]),
            'event_key' => $this->faker->randomElement(NotificationRule::VALID_EVENT_KEYS),
            'min_priority' => $this->faker->randomElement(Notification::VALID_PRIORITIES),
            'channels' => $this->faker->randomElements(
                Notification::VALID_CHANNELS,
                $this->faker->numberBetween(1, 3)
            ),
            'is_enabled' => $this->faker->boolean(80), // 80% enabled
        ];
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

    /**
     * State: Global rule (no project)
     */
    public function global(): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => null,
        ]);
    }

    /**
     * State: Project-specific rule
     */
    public function forProject(string $projectId): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $projectId,
        ]);
    }

    /**
     * State: Enabled rule
     */
    public function enabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => true,
        ]);
    }

    /**
     * State: Critical priority only
     */
    public function criticalOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'min_priority' => Notification::PRIORITY_CRITICAL,
        ]);
    }
}
