<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\ContractExpense;
use App\Models\Contract;
use App\Models\ContractBudgetLine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ContractExpense>
 */
class ContractExpenseFactory extends Factory
{
    protected $model = ContractExpense::class;

    /**
     * Define the model's default state.
     * 
     * Round 44: Contract Expenses (Actual Costs) - Backend Only
     * 
     * The factory ensures tenant_id always matches contract's tenant_id.
     * 
     * RECOMMENDED USAGE in tests:
     * - ContractExpense::factory()->for($contract, 'contract')->create()
     *   This ensures tenant_id is automatically synced from the contract.
     * 
     * - ContractExpense::factory()->for($contract)->state(['tenant_id' => $contract->tenant_id])->create()
     *   Explicitly set both contract_id and tenant_id for clarity.
     * 
     * The configure() hook will auto-sync tenant_id from contract if not explicitly set,
     * but explicit patterns are preferred for test clarity.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->optional()->randomFloat(2, 1, 1000);
        $unitCost = $this->faker->optional()->randomFloat(2, 10, 10000);
        $amount = null;
        
        // Calculate amount if both quantity and unit_cost are set
        if ($quantity !== null && $unitCost !== null) {
            $amount = $quantity * $unitCost;
        } else {
            // Otherwise use a random amount
            $amount = $this->faker->randomFloat(2, 1000, 100000);
        }
        
        return [
            // Note: tenant_id and contract_id are created independently in default state.
            // The configure() hook ensures tenant_id matches contract's tenant_id.
            // For tests, prefer: ->for($contract, 'contract') to ensure consistency.
            'tenant_id' => Tenant::factory(),
            'contract_id' => Contract::factory(),
            'budget_line_id' => null,
            'code' => $this->faker->optional()->regexify('[A-Z0-9]{6}'),
            'name' => $this->faker->sentence(3),
            'category' => $this->faker->optional()->randomElement(['labor', 'material', 'service', 'other']),
            'vendor_name' => $this->faker->optional()->company(),
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'amount' => $amount,
            'currency' => $this->faker->randomElement(['VND', 'USD', 'EUR', 'GBP']),
            'incurred_at' => $this->faker->optional()->date(),
            'status' => $this->faker->randomElement(['planned', 'recorded', 'approved', 'paid', 'cancelled']),
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
     * Round 44: Contract Expenses (Actual Costs) - Backend Only
     * 
     * Behavior:
     * - Default: If tenant_id is not explicitly set (null or factory instance), sync from contract
     * - Explicit: If test explicitly sets tenant_id in state, respect it and don't override
     */
    public function configure(): static
    {
        return $this->afterMaking(function (ContractExpense $expense) {
            // Only auto-sync tenant_id if it wasn't explicitly set
            // Check if tenant_id is null or is a factory instance (not explicitly set)
            $tenantIdIsExplicit = $expense->tenant_id !== null 
                && !($expense->tenant_id instanceof \Illuminate\Database\Eloquent\Factories\Factory);
            
            // If tenant_id was explicitly set, don't override
            if ($tenantIdIsExplicit) {
                return;
            }
            
            // Otherwise, sync from contract if available
            if ($expense->contract_id) {
                $contract = $expense->contract ?? Contract::find($expense->contract_id);
                if ($contract && $contract->tenant_id) {
                    $expense->tenant_id = $contract->tenant_id;
                }
            }
        });
    }
}

