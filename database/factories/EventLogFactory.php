<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\EventLog;
use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventLog>
 */
class EventLogFactory extends Factory
{
    protected $model = EventLog::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'event_name' => 'Tests\\Events\\SampleEvent',
            'event_type' => 'Tests\\Events\\SampleEvent',
            'event_class' => 'Tests\\Events\\SampleEvent',
            'project_id' => Project::factory(),
            'tenant_id' => Tenant::factory(),
            'actor_id' => null,
            'entity_id' => null,
            'payload' => [
                'message' => $this->faker->sentence()
            ],
            'event_data' => [
                'details' => $this->faker->sentence()
            ],
            'changed_fields' => ['field' => $this->faker->word()],
            'source_module' => 'Testing',
            'severity' => 'info',
            'event_timestamp' => now(),
        ];
    }
}
