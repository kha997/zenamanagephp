<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkTemplateChecklistItem;
use App\Models\WorkTemplateTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkTemplateChecklistItem>
 */
class WorkTemplateChecklistItemFactory extends Factory
{
    protected $model = WorkTemplateChecklistItem::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'work_template_task_id' => WorkTemplateTask::factory(),
            'checklist_key' => 'check-' . $this->faker->unique()->numerify('###'),
            'label' => $this->faker->sentence(3),
            'help_text' => null,
            'item_order' => 1,
            'is_required' => true,
            'validation_json' => null,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
