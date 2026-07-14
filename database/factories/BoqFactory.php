<?php

namespace Database\Factories;

use App\Models\Boq;
use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Boq> */
class BoqFactory extends Factory
{
    protected $model = Boq::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'project_id' => Project::factory(),
            'contract_id' => null,
            'code' => 'BOQ-' . strtoupper(uniqid()),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
        ];
    }
}
