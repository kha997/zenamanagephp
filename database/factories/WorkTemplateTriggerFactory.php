<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkTemplateTask;
use App\Models\WorkTemplateTrigger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkTemplateTrigger>
 */
class WorkTemplateTriggerFactory extends Factory
{
    protected $model = WorkTemplateTrigger::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'work_template_task_id' => WorkTemplateTask::factory(),
            'trigger_key' => 'trigger-' . $this->faker->unique()->numerify('###'),
            'event' => 'task.completed',
            'action' => 'notify_role',
            'trigger_order' => 1,
            'is_active' => true,
            'conditions_json' => null,
            'payload_json' => ['role_code' => 'project_manager'],
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
