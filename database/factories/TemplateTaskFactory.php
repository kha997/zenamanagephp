<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\TemplateTask;
use App\Models\TemplateSet;
use App\Models\TemplatePhase;
use App\Models\TemplateDiscipline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<App\Models\TemplateTask>
 */
class TemplateTaskFactory extends Factory
{
    protected $model = TemplateTask::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'set_id' => TemplateSet::factory(),
            'phase_id' => TemplatePhase::factory(),
            'discipline_id' => TemplateDiscipline::factory(),
            'code' => 'TASK-' . strtoupper($this->faker->unique()->regexify('[A-Z0-9]{6}')),
            'name' => $this->faker->words(3, true) . ' Task',
            'description' => $this->faker->paragraph(),
            'est_duration_days' => $this->faker->numberBetween(1, 30),
            'role_key' => $this->faker->randomElement(['lead_architect', 'project_manager', 'engineer', null]),
            'deliverable_type' => $this->faker->randomElement(['layout_dwg', 'specification', 'report', null]),
            'order_index' => $this->faker->numberBetween(0, 10),
            'is_optional' => false,
            'metadata' => null,
        ];
    }
}

