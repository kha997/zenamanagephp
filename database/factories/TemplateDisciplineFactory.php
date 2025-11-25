<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\TemplateDiscipline;
use App\Models\TemplateSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<App\Models\TemplateDiscipline>
 */
class TemplateDisciplineFactory extends Factory
{
    protected $model = TemplateDiscipline::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'set_id' => TemplateSet::factory(),
            'code' => 'DISC-' . strtoupper($this->faker->unique()->regexify('[A-Z0-9]{4}')),
            'name' => $this->faker->words(2, true) . ' Discipline',
            'color_hex' => $this->faker->hexColor(),
            'order_index' => $this->faker->numberBetween(0, 10),
            'metadata' => null,
        ];
    }
}

