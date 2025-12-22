<?php

namespace Database\Factories;

use App\Models\Rfi;
use App\Models\Project;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rfi>
 */
class RfiFactory extends Factory
{
    protected $model = Rfi::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = ['open', 'answered', 'closed'];
        $priorities = ['low', 'medium', 'high', 'urgent'];
        
        return [
            'tenant_id' => Tenant::factory(),
            'project_id' => Project::factory(),
            'title' => $this->faker->sentence(4),
            'subject' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(3),
            'question' => $this->faker->paragraph(2),
            'rfi_number' => 'RFI-' . $this->faker->unique()->numberBetween(1000, 9999),
            'priority' => $this->faker->randomElement($priorities),
            'location' => $this->faker->address,
            'drawing_reference' => 'DRW-' . $this->faker->numberBetween(100, 999),
            'asked_by' => User::factory(),
            'created_by' => User::factory(),
            'assigned_to' => User::factory(),
            'due_date' => $this->faker->dateTimeBetween('now', '+30 days'),
            'status' => $this->faker->randomElement($statuses),
            'answer' => $this->faker->optional(0.7)->paragraph(2),
            'response' => $this->faker->optional(0.5)->paragraph(2),
            'answered_by' => $this->faker->optional(0.6)->passthrough(User::factory()),
            'responded_by' => $this->faker->optional(0.4)->passthrough(User::factory()),
            'answered_at' => $this->faker->optional(0.6)->dateTimeBetween('-10 days', 'now'),
            'responded_at' => $this->faker->optional(0.4)->dateTimeBetween('-5 days', 'now'),
            'assigned_at' => $this->faker->optional(0.8)->dateTimeBetween('-15 days', 'now'),
            'assignment_notes' => $this->faker->optional(0.3)->paragraph(1),
            'escalated_to' => $this->faker->optional(0.2)->passthrough(User::factory()),
            'escalation_reason' => $this->faker->optional(0.2)->sentence(2),
            'escalated_by' => $this->faker->optional(0.2)->passthrough(User::factory()),
            'escalated_at' => $this->faker->optional(0.2)->dateTimeBetween('-7 days', 'now'),
            'closed_by' => $this->faker->optional(0.3)->passthrough(User::factory()),
            'closed_at' => $this->faker->optional(0.3)->dateTimeBetween('-3 days', 'now'),
            'attachments' => $this->faker->optional(0.4)->randomElements([
                'attachment1.pdf',
                'drawing1.dwg',
                'photo1.jpg',
                'specification1.docx'
            ], $this->faker->numberBetween(0, 3)),
        ];
    }

    /**
     * Indicate that the RFI is open.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'open',
            'answered_at' => null,
            'responded_at' => null,
            'closed_at' => null,
        ]);
    }

    /**
     * Indicate that the RFI is answered.
     */
    public function answered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'answered',
            'answer' => $this->faker->paragraph(2),
            'answered_by' => User::factory(),
            'answered_at' => $this->faker->dateTimeBetween('-5 days', 'now'),
        ]);
    }

    /**
     * Indicate that the RFI is closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
            'answer' => $this->faker->paragraph(2),
            'response' => $this->faker->paragraph(1),
            'answered_by' => User::factory(),
            'responded_by' => User::factory(),
            'closed_by' => User::factory(),
            'answered_at' => $this->faker->dateTimeBetween('-10 days', '-5 days'),
            'responded_at' => $this->faker->dateTimeBetween('-5 days', '-2 days'),
            'closed_at' => $this->faker->dateTimeBetween('-2 days', 'now'),
        ]);
    }

    /**
     * Indicate that the RFI is overdue.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $this->faker->randomElement(['open']),
            'due_date' => $this->faker->dateTimeBetween('-10 days', '-1 day'),
            'answered_at' => null,
            'responded_at' => null,
            'closed_at' => null,
        ]);
    }

    /**
     * Indicate that the RFI is high priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
            'due_date' => $this->faker->dateTimeBetween('now', '+3 days'),
        ]);
    }

    /**
     * Indicate that the RFI is urgent.
     */
    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'urgent',
            'due_date' => $this->faker->dateTimeBetween('now', '+1 day'),
            'status' => 'open',
        ]);
    }
}