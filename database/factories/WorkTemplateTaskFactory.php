<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkTemplatePhase;
use App\Models\WorkTemplateTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkTemplateTask>
 */
class WorkTemplateTaskFactory extends Factory
{
    protected $model = WorkTemplateTask::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'work_template_phase_id' => WorkTemplatePhase::factory(),
            'task_key' => 'task-' . $this->faker->unique()->numerify('###'),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'task_type' => 'standard',
            'task_order' => 1,
            'default_duration_days' => null,
            'is_required' => true,
            'config_json' => null,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
