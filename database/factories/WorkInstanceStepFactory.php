<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkInstance;
use App\Models\WorkInstanceStep;
use App\Models\WorkTemplateStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkInstanceStep>
 */
class WorkInstanceStepFactory extends Factory
{
    protected $model = WorkInstanceStep::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'work_instance_id' => WorkInstance::factory(),
            'work_template_step_id' => WorkTemplateStep::factory(),
            'step_key' => 'step-' . $this->faker->unique()->numerify('###'),
            'name' => $this->faker->words(2, true),
            'type' => 'task',
            'step_order' => 1,
            'depends_on' => [],
            'assignee_rule_json' => null,
            'sla_hours' => null,
            'snapshot_fields_json' => null,
            'status' => 'pending',
            'assignee_id' => null,
            'deadline_at' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}
