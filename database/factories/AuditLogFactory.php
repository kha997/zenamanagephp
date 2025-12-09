<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * AuditLogFactory
 * 
 * Round 235: Audit Log Framework
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'action' => $this->faker->randomElement([
                'role.created',
                'role.updated',
                'role.deleted',
                'user.roles_updated',
                'co.created',
                'co.updated',
                'co.submitted',
                'co.approved',
                'co.rejected',
                'certificate.created',
                'certificate.submitted',
                'certificate.approved',
                'payment.marked_paid',
                'document.version_created',
                'document.version_restored',
                'task.status_changed',
                'task.due_date_changed',
            ]),
            'entity_type' => $this->faker->randomElement(['Role', 'User', 'ChangeOrder', 'ContractPaymentCertificate', 'ContractActualPayment', 'Document', 'Task']),
            'entity_id' => $this->faker->uuid(),
            'project_id' => null,
            'payload_before' => null,
            'payload_after' => ['status' => 'active'],
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
        ];
    }
}
