<?php declare(strict_types=1);

namespace Database\Factories\Src\RBAC\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\RBAC\Models\UserRoleProject;
use Src\RBAC\Models\Role;
use Src\CoreProject\Models\Project;
use App\Models\User;

/**
 * Factory cho UserRoleProject model
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Src\RBAC\Models\UserRoleProject>
 */
class UserRoleProjectFactory extends Factory
{
    protected $model = UserRoleProject::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'role_id' => Role::factory()->project(),
            'assigned_by' => User::factory(),
            'assigned_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'expires_at' => $this->faker->optional(0.5)->dateTimeBetween('now', '+1 year'),
            'is_active' => $this->faker->boolean(90),
        ];
    }

    /**
     * State: Project manager role
     */
    public function projectManager(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::factory()->project()->state(['name' => 'Project Manager']),
            'is_active' => true,
        ]);
    }

    /**
     * State: Team member role
     */
    public function teamMember(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::factory()->project()->state(['name' => 'Team Member']),
        ]);
    }
}