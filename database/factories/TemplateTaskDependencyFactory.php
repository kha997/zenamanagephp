<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\TemplateTaskDependency;
use App\Models\TemplateSet;
use App\Models\TemplateTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<App\Models\TemplateTaskDependency>
 */
class TemplateTaskDependencyFactory extends Factory
{
    protected $model = TemplateTaskDependency::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'set_id' => TemplateSet::factory(),
            'task_id' => TemplateTask::factory(),
            'depends_on_task_id' => TemplateTask::factory(),
        ];
    }
}

