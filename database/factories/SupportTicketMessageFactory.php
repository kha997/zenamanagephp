<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\SupportTicketMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SupportTicketMessageFactory extends Factory
{
    protected $model = SupportTicketMessage::class;

    public function definition(): array
    {
        $ticket = SupportTicket::factory()->create();
        $user = User::factory()->state(['tenant_id' => $ticket->tenant_id])->create();

        return [
            'id' => (string) Str::ulid(),
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $this->faker->paragraph(),
            'is_internal' => $this->faker->boolean(20),
        ];
    }
}
