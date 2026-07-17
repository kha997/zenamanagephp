<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkTemplatePhase;
use App\Models\WorkTemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkTemplatePhase>
 */
class WorkTemplatePhaseFactory extends Factory
{
    protected $model = WorkTemplatePhase::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'work_template_version_id' => WorkTemplateVersion::factory(),
            'phase_key' => 'phase-' . $this->faker->unique()->numerify('###'),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'phase_order' => 1,
            'default_offset_days' => null,
            'config_json' => null,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
