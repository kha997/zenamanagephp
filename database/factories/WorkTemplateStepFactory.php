<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\WorkTemplateStep;
use App\Models\WorkTemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkTemplateStep>
 */
class WorkTemplateStepFactory extends Factory
{
    protected $model = WorkTemplateStep::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'work_template_version_id' => WorkTemplateVersion::factory(),
            'step_key' => 'step-' . $this->faker->unique()->numerify('###'),
            'name' => $this->faker->words(2, true),
            'type' => 'task',
            'step_order' => 1,
            'depends_on' => [],
            'assignee_rule_json' => null,
            'sla_hours' => null,
            'config_json' => null,
        ];
    }
}
