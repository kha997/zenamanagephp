<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\ContractBudgetLine;
use App\Models\Contract;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ContractBudgetLine>
 */
class ContractBudgetLineFactory extends Factory
{
    protected $model = ContractBudgetLine::class;

    /**
     * Define the model's default state.
     * 
     * Round 43: Cost Control / Budget vs Actual (Backend-only Foundation)
     * 
     * The factory ensures tenant_id always matches contract's tenant_id.
     * 
     * RECOMMENDED USAGE in tests:
     * - ContractBudgetLine::factory()->for($contract, 'contract')->create()
     *   This ensures tenant_id is automatically synced from the contract.
     * 
     * - ContractBudgetLine::factory()->for($contract)->state(['tenant_id' => $contract->tenant_id])->create()
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
        $unitPrice = $this->faker->optional()->randomFloat(2, 10, 10000);
        $totalAmount = null;
        
        // Calculate total_amount if both quantity and unit_price are set
        if ($quantity !== null && $unitPrice !== null) {
            $totalAmount = $quantity * $unitPrice;
        } else {
            // Otherwise use a random total_amount
            $totalAmount = $this->faker->randomFloat(2, 1000, 100000);
        }
        
        return [
            // Note: tenant_id and contract_id are created independently in default state.
            // The configure() hook ensures tenant_id matches contract's tenant_id.
            // For tests, prefer: ->for($contract, 'contract') to ensure consistency.
            'tenant_id' => Tenant::factory(),
            'contract_id' => Contract::factory(),
            'code' => $this->faker->optional()->regexify('[A-Z0-9]{6}'),
            'name' => $this->faker->sentence(3),
            'category' => $this->faker->optional()->randomElement(['vật tư', 'nhân công', 'thầu phụ', 'khác']),
            'cost_type' => $this->faker->optional()->randomElement(['direct', 'indirect', 'contingency']),
            'quantity' => $quantity,
            'unit' => $this->faker->optional()->randomElement(['m3', 'm2', 'bộ', 'công', 'kg', 'm']),
            'unit_price' => $unitPrice,
            'total_amount' => $totalAmount,
            'currency' => $this->faker->randomElement(['USD', 'VND', 'EUR', 'GBP']),
            'wbs_code' => $this->faker->optional()->regexify('[A-Z0-9]{8}'),
            'status' => $this->faker->randomElement(['planned', 'approved', 'locked', 'cancelled']),
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
     * Round 43: Cost Control / Budget vs Actual (Backend-only Foundation)
     * 
     * Behavior:
     * - Default: If tenant_id is not explicitly set (null or factory instance), sync from contract
     * - Explicit: If test explicitly sets tenant_id in state, respect it and don't override
     */
    public function configure(): static
    {
        return $this->afterMaking(function (ContractBudgetLine $line) {
            // Only auto-sync tenant_id if it wasn't explicitly set
            // Check if tenant_id is null or is a factory instance (not explicitly set)
            $tenantIdIsExplicit = $line->tenant_id !== null 
                && !($line->tenant_id instanceof \Illuminate\Database\Eloquent\Factories\Factory);
            
            // If tenant_id was explicitly set, don't override
            if ($tenantIdIsExplicit) {
                return;
            }
            
            // Otherwise, sync from contract if available
            if ($line->contract_id) {
                $contract = $line->contract ?? Contract::find($line->contract_id);
                if ($contract && $contract->tenant_id) {
                    $line->tenant_id = $contract->tenant_id;
                }
            }
        });
    }
}

