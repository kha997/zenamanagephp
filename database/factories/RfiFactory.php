<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\Rfi;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rfi>
 */
class RfiFactory extends Factory
{
    protected $model = Rfi::class;

    public function definition(): array
    {
        return [
            'id' => $this->faker->unique()->regexify('[0-9A-Za-z]{26}'),
            'tenant_id' => Tenant::factory(),
            'project_id' => Project::factory(),
            'title' => $this->faker->sentence(4),
            'subject' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'question' => $this->faker->sentence(),
            'rfi_number' => 'RFI-' . strtoupper($this->faker->unique()->regexify('[A-Z0-9]{6}')),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'urgent']),
            'location' => $this->faker->optional()->city(),
            'drawing_reference' => $this->faker->optional()->bothify('DR-###'),
            'asked_by' => User::factory(),
            'created_by' => User::factory(),
            'assigned_to' => null,
            'due_date' => $this->faker->optional()->dateTimeBetween('+1 day', '+1 month'),
            'status' => $this->faker->randomElement(['open', 'answered', 'closed']),
            'answer' => null,
            'response' => null,
            'answered_by' => null,
            'responded_by' => null,
            'answered_at' => null,
            'responded_at' => null,
            'assigned_at' => null,
            'assignment_notes' => null,
            'escalated_to' => null,
            'escalation_reason' => null,
            'escalated_by' => null,
            'escalated_at' => null,
            'closed_by' => null,
            'closed_at' => null,
            'attachments' => [],
        ];
    }
}
