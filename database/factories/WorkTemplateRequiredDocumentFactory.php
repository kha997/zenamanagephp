<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkTemplateRequiredDocument;
use App\Models\WorkTemplateTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkTemplateRequiredDocument>
 */
class WorkTemplateRequiredDocumentFactory extends Factory
{
    protected $model = WorkTemplateRequiredDocument::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'work_template_task_id' => WorkTemplateTask::factory(),
            'work_template_checklist_item_id' => null,
            'doc_key' => 'doc-' . $this->faker->unique()->numerify('###'),
            'document_type' => 'drawing',
            'name' => $this->faker->words(2, true),
            'description' => null,
            'doc_order' => 1,
            'is_required' => true,
            'rules_json' => null,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
