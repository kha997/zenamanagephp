<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Rfi;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rfi>
 */
class RfiFactory extends Factory
{
    protected $model = Rfi::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'tenant_id' => Tenant::factory(),
            'project_id' => Project::factory(),
            'title' => $this->faker->sentence(6),
            'subject' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'question' => $this->faker->paragraph(),
            'rfi_number' => 'RFI-' . strtoupper(Str::random(8)),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'urgent']),
            'location' => $this->faker->city(),
            'drawing_reference' => $this->faker->word(),
            'asked_by' => User::factory(),
            'created_by' => User::factory(),
            'assigned_to' => null,
            'due_date' => $this->faker->date(),
            'status' => 'open',
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
