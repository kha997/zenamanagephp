<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\DashboardAlert;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DashboardAlert>
 */
class DashboardAlertFactory extends Factory
{
    protected $model = DashboardAlert::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'project_id' => null,
            'message' => $this->faker->sentence(),
            'type' => $this->faker->randomElement(['task_overdue', 'budget_warning', 'quality_failure', 'schedule_slip']),
            'severity' => $this->faker->randomElement(DashboardAlert::VALID_SEVERITIES),
            'is_read' => false,
            'triggered_at' => now(),
            'context' => null,
        ];
    }
}
