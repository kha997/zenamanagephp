<?php declare(strict_types=1);

namespace Database\Factories\Src\CoreProject\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\CoreProject\Models\Project;
use App\Models\Tenant;

/**
 * Factory cho Project model
 * 
 * Tạo test data cho projects với các trạng thái và thông tin khác nhau
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * Define the model's default state
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-6 months', 'now');
        $endDate = $this->faker->dateTimeBetween($startDate, '+1 year');
        
        return [
            'tenant_id' => Tenant::factory(),
            'code' => 'PRJ-' . $this->faker->unique()->numberBetween(1000, 9999),
            'name' => $this->faker->catchPhrase . ' Project',
            'description' => $this->faker->paragraph(3),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'status' => $this->faker->randomElement(['planning', 'active', 'on_hold', 'completed', 'cancelled']),
            'progress' => $this->faker->numberBetween(0, 100),
            'budget_total' => $this->faker->randomFloat(2, 0, 100000),
            'created_at' => now(),
            'updated_at' => now()
        ];
    }

    /**
     * Active project state
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'progress' => $this->faker->numberBetween(10, 80)
        ]);
    }

    /**
     * Completed project state
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'progress' => 100,
            'end_date' => $this->faker->dateTimeBetween('-3 months', 'now')->format('Y-m-d')
        ]);
    }

    /**
     * Planning phase project state
     */
    public function planning(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'planning',
            'progress' => 0,
            'start_date' => $this->faker->dateTimeBetween('now', '+1 month')->format('Y-m-d')
        ]);
    }

    /**
     * Large budget project state
     */
    public function largeBudget(): static
    {
        return $this->state(fn (array $attributes) => [
            'budget_total' => $this->faker->randomFloat(2, 50000, 500000)
        ]);
    }
}