<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\ContractPayment;
use App\Models\Contract;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ContractPayment>
 */
class ContractPaymentFactory extends Factory
{
    protected $model = ContractPayment::class;

    /**
     * Define the model's default state.
     * 
     * Round 37: Payment Hardening - Ensure tenant_id matches contract's tenant_id
     * 
     * The factory ensures tenant_id always matches contract's tenant_id.
     * Use ->for($contract, 'contract') to create payment for a specific contract.
     * The tenant_id will be automatically synced from the contract.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'contract_id' => Contract::factory(),
            'code' => $this->faker->optional()->regexify('[A-Z0-9]{6}'),
            'name' => $this->faker->sentence(3),
            'type' => $this->faker->optional()->randomElement(['deposit', 'milestone', 'progress', 'retention', 'final']),
            'due_date' => $this->faker->dateTimeBetween('now', '+1 year'),
            'amount' => $this->faker->randomFloat(2, 1000, 100000),
            'currency' => $this->faker->randomElement(['USD', 'VND', 'EUR', 'GBP']),
            'status' => $this->faker->randomElement(['planned', 'due', 'paid', 'overdue', 'cancelled']),
            'paid_at' => $this->faker->optional()->dateTimeBetween('-1 year', 'now'),
            'notes' => $this->faker->optional()->paragraph(),
            'sort_order' => $this->faker->numberBetween(0, 100),
            'created_by_id' => function (array $attributes) {
                $tenantId = $attributes['tenant_id'] ?? null;
                
                // If tenant_id is a factory, resolve it
                if ($tenantId instanceof \Illuminate\Database\Eloquent\Factories\Factory) {
                    $tenantId = null; // Will be resolved from contract
                }
                
                // If contract_id is set, get tenant_id from contract
                if (!$tenantId && isset($attributes['contract_id'])) {
                    $contract = is_string($attributes['contract_id']) 
                        ? Contract::find($attributes['contract_id'])
                        : $attributes['contract_id'];
                    if ($contract) {
                        $tenantId = $contract->tenant_id ?? $contract->id ?? null;
                    }
                }
                
                if (!$tenantId) {
                    // Fallback: create a tenant
                    $tenantId = Tenant::factory()->create()->id;
                }
                
                return User::factory()->create(['tenant_id' => $tenantId])->id;
            },
            'updated_by_id' => function (array $attributes) {
                return $attributes['created_by_id'];
            },
        ];
    }
    
    /**
     * Configure the factory to ensure tenant_id matches contract's tenant_id
     * 
     * Round 37: Payment Hardening - Auto-sync tenant_id from contract
     * Round 38: Respect explicit tenant_id in state
     * 
     * Behavior:
     * - Default: If tenant_id is not explicitly set (null or factory instance), sync from contract
     * - Explicit: If test explicitly sets tenant_id in state, respect it and don't override
     */
    public function configure(): static
    {
        return $this->afterMaking(function (ContractPayment $payment) {
            // Only auto-sync tenant_id if it wasn't explicitly set
            // Check if tenant_id is null or is a factory instance (not explicitly set)
            $tenantIdIsExplicit = $payment->tenant_id !== null 
                && !($payment->tenant_id instanceof \Illuminate\Database\Eloquent\Factories\Factory);
            
            // If tenant_id was explicitly set, don't override
            if ($tenantIdIsExplicit) {
                return;
            }
            
            // Otherwise, sync from contract if available
            if ($payment->contract_id) {
                $contract = $payment->contract ?? Contract::find($payment->contract_id);
                if ($contract && $contract->tenant_id) {
                    $payment->tenant_id = $contract->tenant_id;
                }
            }
        });
    }
}
