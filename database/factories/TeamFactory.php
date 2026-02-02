<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for App\Models\Team
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $lead = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $creator = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $updater = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);

        return [
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenant->id,
            'name' => $this->faker->company(),
            'description' => $this->faker->sentence(),
            'team_lead_id' => $lead->id,
            'department' => $this->faker->word(),
            'is_active' => true,
            'status' => Team::STATUS_ACTIVE,
            'settings' => json_encode([
                'notifications' => true,
                'auto_assign' => false,
            ]),
            'created_by' => $creator->id,
            'updated_by' => $updater->id,
        ];
    }
}
