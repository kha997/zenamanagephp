<?php declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;

/**
 * Factory cho Team model
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Team>
 */
class TeamFactory extends Factory
{
    /**
     * Model được tạo bởi factory này
     *
     * @var string
     */
    protected $model = Team::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => $this->faker->unique()->regexify('[0-9A-Za-z]{26}'),
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->company() . ' Team',
            'description' => $this->faker->paragraph(2),
            'team_lead_id' => null, // Will be set separately if needed
            'department' => $this->faker->randomElement(['Engineering', 'Design', 'Marketing', 'Sales', 'Support', 'Operations']),
            'is_active' => $this->faker->boolean(90), // 90% chance of being active
            'settings' => json_encode([
                'notifications' => $this->faker->boolean(),
                'auto_assign_tasks' => $this->faker->boolean(),
                'require_approval' => $this->faker->boolean(),
            ]),
            'created_by' => null, // Will be set separately if needed
            'updated_by' => null,
        ];
    }

    /**
     * Indicate that the team is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the team is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the team has a team lead.
     */
    public function withTeamLead(): static
    {
        return $this->state(fn (array $attributes) => [
            'team_lead_id' => User::factory(),
        ]);
    }

    /**
     * Indicate that the team belongs to a specific tenant.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * Indicate that the team was created by a specific user.
     */
    public function createdBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);
    }

    /**
     * Indicate that the team is for a specific department.
     */
    public function department(string $department): static
    {
        return $this->state(fn (array $attributes) => [
            'department' => $department,
        ]);
    }

    /**
     * Indicate that the team has specific settings.
     */
    public function withSettings(array $settings): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => json_encode($settings),
        ]);
    }
}
