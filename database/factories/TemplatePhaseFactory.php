<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\TemplatePhase;
use App\Models\TemplateSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<App\Models\TemplatePhase>
 */
class TemplatePhaseFactory extends Factory
{
    protected $model = TemplatePhase::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'set_id' => TemplateSet::factory(),
            'code' => 'PHASE-' . strtoupper($this->faker->unique()->regexify('[A-Z0-9]{4}')),
            'name' => $this->faker->words(2, true) . ' Phase',
            'order_index' => $this->faker->numberBetween(0, 10),
            'metadata' => null,
        ];
    }
}

