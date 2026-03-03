<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\WorkTemplateField;
use App\Models\WorkTemplateStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkTemplateField>
 */
class WorkTemplateFieldFactory extends Factory
{
    protected $model = WorkTemplateField::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'work_template_step_id' => WorkTemplateStep::factory(),
            'field_key' => 'field-' . $this->faker->unique()->numerify('###'),
            'label' => $this->faker->words(2, true),
            'type' => 'string',
            'is_required' => false,
            'default_value' => null,
            'validation_json' => null,
            'enum_options_json' => null,
            'visibility_rule_json' => null,
        ];
    }
}
