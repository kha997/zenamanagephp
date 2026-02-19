<?php

namespace Database\Factories;

use App\Models\Submittal;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Submittal>
 */
class SubmittalFactory extends Factory
{
    protected $model = Submittal::class;

    public function definition(): array
    {
        $statuses = [
            'draft',
            'submitted',
            'pending_review',
            'approved',
            'rejected',
            'revised',
        ];
        $types = [
            'construction_material',
            'design_document',
            'equipment_setting',
            'request_for_information',
        ];

        return [
            'id' => (string) Str::ulid(),
            'tenant_id' => Tenant::factory(),
            'project_id' => Project::factory(),
            'submittal_number' => 'SUB-' . $this->faker->numerify('####'),
            'package_no' => $this->faker->bothify('PKG-??'),
            'title' => $this->faker->sentence(6),
            'description' => $this->faker->paragraph(2),
            'submittal_type' => $this->faker->randomElement($types),
            'specification_section' => $this->faker->bothify('Sec-###'),
            'status' => $this->faker->randomElement($statuses),
            'due_date' => $this->faker->dateTimeBetween('now', '+30 days'),
            'contractor' => $this->faker->company(),
            'manufacturer' => $this->faker->company(),
            'file_url' => $this->faker->optional()->url(),
            'submitted_by' => User::factory(),
            'created_by' => User::factory(),
            'submitted_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'reviewed_by' => User::factory(),
            'reviewed_at' => $this->faker->optional()->dateTimeBetween('-1 week', 'now'),
            'review_comments' => $this->faker->optional()->paragraph(),
            'review_notes' => $this->faker->optional()->sentence(),
            'approved_by' => User::factory(),
            'approved_at' => $this->faker->optional()->dateTimeBetween('-1 week', 'now'),
            'approval_comments' => $this->faker->optional()->sentence(),
            'rejected_by' => User::factory(),
            'rejected_at' => $this->faker->optional()->dateTimeBetween('-1 week', 'now'),
            'rejection_reason' => $this->faker->optional()->sentence(),
            'rejection_comments' => $this->faker->optional()->sentence(),
            'attachments' => [],
        ];
    }
}
