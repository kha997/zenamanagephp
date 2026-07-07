<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\EventRecord;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventRecord>
 */
class EventRecordFactory extends Factory
{
    protected $model = EventRecord::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'project_id' => null,
            'aggregate_type' => $this->faker->randomElement(['task', 'project', 'rfi', 'submittal']),
            'aggregate_id' => (string) \Illuminate\Support\Str::ulid(),
            'event_key' => 'zena.' . $this->faker->slug(3),
            'actor_user_id' => User::factory(),
            'payload' => ['event' => 'test'],
            'occurred_at' => now(),
        ];
    }
}
