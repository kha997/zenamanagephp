<?php declare(strict_types=1);

namespace Database\Factories\Src\Compensation\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Compensation\Models\Contract;
use Src\CoreProject\Models\Project;

/**
 * Factory cho Contract model
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Src\Compensation\Models\Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-1 year', 'now');
        $endDate = $this->faker->dateTimeBetween($startDate, '+2 years');
        
        return [
            'project_id' => Project::factory(),
            'contract_number' => 'CT-' . $this->faker->unique()->numberBetween(10000, 99999),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(3),
            'total_value' => $this->faker->randomFloat(2, 50000, 2000000),
            'version' => $this->faker->numberBetween(1, 3),
            'status' => $this->faker->randomElement(Contract::VALID_STATUSES),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'signed_date' => $this->faker->optional(0.8)->dateTimeBetween($startDate, 'now'),
            'terms' => [
                'payment_schedule' => 'Monthly',
                'warranty_period' => '12 months',
                'penalty_rate' => '0.1% per day',
            ],
            'client_name' => $this->faker->company(),
            'notes' => $this->faker->optional(0.6)->paragraph(),
        ];
    }

    /**
     * State: Active contract
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Contract::STATUS_ACTIVE,
            'signed_date' => $this->faker->dateTimeBetween('-6 months', 'now'),
        ]);
    }

    /**
     * State: Draft contract
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Contract::STATUS_DRAFT,
            'signed_date' => null,
        ]);
    }
}