<?php

namespace Database\Factories;

use App\Models\OpportunityAppointment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\OpportunityAppointment> */
class OpportunityAppointmentFactory extends Factory
{
    protected $model = OpportunityAppointment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'opportunity_id' => null, // must be set manually — no OpportunityFactory
            'type' => $this->faker->randomElement(OpportunityAppointment::VALID_TYPES),
            'scheduled_at' => now()->addDay(),
            'location' => $this->faker->optional()->address(),
            'assigned_to' => null,
            'status' => OpportunityAppointment::STATUS_SCHEDULED,
            'outcome_notes' => null,
            'created_by' => User::factory(),
        ];
    }
}
