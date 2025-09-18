<?php declare(strict_types=1);

namespace Database\Factories;

use Src\CoreProject\Models\Project;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory cho Project model
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;
    
    /**
     * Define the model's default state
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-1 month', 'now');
        $endDate = $this->faker->dateTimeBetween($startDate, '+6 months');
        
        return [
            'tenant_id' => Tenant::factory(),
            'code' => 'PRJ-' . $this->faker->unique()->numberBetween(1000, 9999),
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $this->faker->randomElement(['planning', 'active', 'on_hold', 'completed']),
            'progress' => $this->faker->numberBetween(0, 100),
            'budget_total' => $this->faker->randomFloat(2, 10000, 1000000),
        ];
    }
}