<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Rfi;
use App\Models\Project;
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
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'tenant_id' => Tenant::factory(),
            'project_id' => Project::factory(),
            'title' => $this->faker->sentence(6),
            'subject' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'question' => $this->faker->paragraph(),
            'rfi_number' => 'RFI-' . strtoupper($this->faker->unique()->regexify('[A-Z0-9]{6}')),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high']),
            'status' => $this->faker->randomElement(['open', 'answered', 'closed']),
            'asked_by' => User::factory(),
            'created_by' => User::factory(),
            'assigned_to' => User::factory(),
            'due_date' => $this->faker->optional(0.7)->dateTimeBetween('now', '+1 month'),
            'location' => $this->faker->word(),
            'drawing_reference' => $this->faker->word(),
            'attachments' => [],
        ];
    }
}
