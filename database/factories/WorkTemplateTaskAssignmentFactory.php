<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkTemplateTask;
use App\Models\WorkTemplateTaskAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkTemplateTaskAssignment>
 */
class WorkTemplateTaskAssignmentFactory extends Factory
{
    protected $model = WorkTemplateTaskAssignment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'work_template_task_id' => WorkTemplateTask::factory(),
            'assignment_key' => 'assign-' . $this->faker->unique()->numerify('###'),
            'assignment_type' => 'assignee',
            'role_code' => 'project_manager',
            'approval_order' => null,
            'is_required' => true,
            'conditions_json' => null,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
