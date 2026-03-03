<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\WorkInstanceFieldValue;
use App\Models\WorkInstanceStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkInstanceFieldValue>
 */
class WorkInstanceFieldValueFactory extends Factory
{
    protected $model = WorkInstanceFieldValue::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'work_instance_step_id' => WorkInstanceStep::factory(),
            'field_key' => 'field-' . $this->faker->unique()->numerify('###'),
            'value_string' => null,
            'value_number' => null,
            'value_date' => null,
            'value_datetime' => null,
            'value_json' => null,
        ];
    }
}
