<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Ncr;
use App\Models\Project;
use App\Models\QcInspection;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ncr>
 */
class NcrFactory extends Factory
{
    protected $model = Ncr::class;

    public function definition(): array
    {
        return [
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'project_id' => Project::factory(),
            'tenant_id' => Tenant::factory(),
            'inspection_id' => QcInspection::factory(),
            'ncr_number' => 'NCR-' . strtoupper($this->faker->unique()->regexify('[A-Z0-9]{8}')),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement(['open', 'under_review', 'in_progress', 'resolved', 'closed']),
            'severity' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'created_by' => User::factory(),
            'assigned_to' => User::factory(),
            'root_cause' => $this->faker->optional(0.7)->paragraph(),
            'corrective_action' => $this->faker->optional(0.7)->paragraph(),
            'preventive_action' => $this->faker->optional(0.7)->paragraph(),
            'resolution' => $this->faker->optional(0.5)->paragraph(),
            'attachments' => [],
        ];
    }
}
