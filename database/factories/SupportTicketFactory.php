<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $creator = User::factory()->state(['tenant_id' => $tenant->id])->create();
        $assignee = User::factory()->state(['tenant_id' => $tenant->id])->create();

        return [
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenant->id,
            'user_id' => $creator->id,
            'assigned_to' => $assignee->id,
            'ticket_number' => 'TCK-' . Str::upper(Str::random(8)),
            'subject' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'category' => $this->faker->randomElement(['technical', 'billing', 'feature_request', 'bug_report', 'general']),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'urgent']),
            'status' => $this->faker->randomElement(['open', 'in_progress', 'pending_customer', 'resolved', 'closed']),
        ];
    }
}
