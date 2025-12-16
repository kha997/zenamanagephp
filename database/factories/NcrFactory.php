<?php

namespace Database\Factories;

use App\Models\Ncr;
use App\Models\Project;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ncr>
 */
class NcrFactory extends Factory
{
    protected $model = Ncr::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'tenant_id' => Tenant::factory(),
            'inspection_id' => null,
            'ncr_number' => 'NCR-' . strtoupper($this->faker->unique()->bothify('####??')),
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement(['open', 'under_review', 'in_progress', 'resolved', 'closed']),
            'severity' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'created_by' => User::factory(),
            'assigned_to' => null,
            'root_cause' => null,
            'corrective_action' => null,
            'preventive_action' => null,
            'resolution' => null,
            'resolved_at' => null,
            'closed_at' => null,
            'attachments' => null,
        ];
    }
}

