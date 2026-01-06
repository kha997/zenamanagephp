<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->company() . ' Team',
            'description' => $this->faker->sentence(),
            'team_lead_id' => User::factory(),
            'department' => $this->faker->randomElement(['Engineering', 'Operations', 'Quality']),
            'is_active' => true,
            'settings' => [
                'notifications' => $this->faker->boolean(),
            ],
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
