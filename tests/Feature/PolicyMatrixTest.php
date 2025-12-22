<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Services\AbilityMatrixService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Policy Matrix Tests
 * 
 * Ensures FE/BE permission synchronization by testing:
 * - All abilities in matrix are valid
 * - Required roles match actual Gate/Policy behavior
 * - Frontend can rely on ability matrix for permission checks
 */
class PolicyMatrixTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private AbilityMatrixService $abilityService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->abilityService = app(AbilityMatrixService::class);
    }

    /**
     * Test that ability matrix is valid
     */
    public function test_ability_matrix_is_valid(): void
    {
        $matrix = $this->abilityService->getAbilityMatrix();
        
        $this->assertIsArray($matrix);
        $this->assertNotEmpty($matrix);
        
        // Each ability should have required_roles and description
        foreach ($matrix as $ability => $config) {
            $this->assertIsString($ability);
            $this->assertIsArray($config);
            $this->assertArrayHasKey('required_roles', $config);
            $this->assertArrayHasKey('description', $config);
            $this->assertIsArray($config['required_roles']);
            $this->assertIsString($config['description']);
        }
    }

    /**
     * Test that super_admin has all abilities
     */
    public function test_super_admin_has_all_abilities(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'super_admin',
        ]);
        
        $matrix = $this->abilityService->getAbilityMatrix();
        
        foreach (array_keys($matrix) as $ability) {
            // Super admin should have all abilities
            $this->assertTrue(
                Gate::forUser($user)->allows($ability) || $user->hasPermission($ability),
                "Super admin should have ability: {$ability}"
            );
        }
    }

    /**
     * Test that required roles match actual Gate behavior
     */
    public function test_required_roles_match_gate_behavior(): void
    {
        $matrix = $this->abilityService->getAbilityMatrix();
        $roles = ['super_admin', 'admin', 'project_manager', 'member', 'client'];
        
        foreach ($matrix as $ability => $config) {
            $requiredRoles = $config['required_roles'];
            
            // Test each role
            foreach ($roles as $role) {
                $user = User::factory()->create([
                    'tenant_id' => $this->tenant->id,
                    'role' => $role,
                ]);
                
                $hasAbility = Gate::forUser($user)->allows($ability) || $user->hasPermission($ability);
                $shouldHaveAbility = in_array($role, $requiredRoles);
                
                $this->assertEquals(
                    $shouldHaveAbility,
                    $hasAbility,
                    "Role '{$role}' ability '{$ability}' mismatch. " .
                    "Matrix says: " . ($shouldHaveAbility ? 'yes' : 'no') . ", " .
                    "Gate says: " . ($hasAbility ? 'yes' : 'no')
                );
            }
        }
    }

    /**
     * Test that ability matrix can be exported for OpenAPI
     */
    public function test_ability_matrix_export_for_openapi(): void
    {
        $export = $this->abilityService->exportForOpenAPI();
        
        $this->assertIsArray($export);
        $this->assertArrayHasKey('x-abilities', $export);
        $this->assertArrayHasKey('x-ability-descriptions', $export);
        
        $this->assertIsArray($export['x-abilities']);
        $this->assertIsArray($export['x-ability-descriptions']);
    }

    /**
     * Test that all abilities have descriptions
     */
    public function test_all_abilities_have_descriptions(): void
    {
        $matrix = $this->abilityService->getAbilityMatrix();
        
        foreach ($matrix as $ability => $config) {
            $this->assertNotEmpty(
                $config['description'],
                "Ability '{$ability}' is missing description"
            );
            $this->assertNotEquals(
                "Ability: {$ability}",
                $config['description'],
                "Ability '{$ability}' has default description (needs custom description)"
            );
        }
    }

    /**
     * Test that common abilities exist in matrix
     */
    public function test_common_abilities_exist(): void
    {
        $matrix = $this->abilityService->getAbilityMatrix();
        $commonAbilities = [
            'admin.access',
            'admin.access.tenant',
            'projects.view',
            'projects.create',
            'tasks.view',
            'tasks.create',
            'documents.view',
            'documents.create',
        ];
        
        foreach ($commonAbilities as $ability) {
            $this->assertArrayHasKey(
                $ability,
                $matrix,
                "Common ability '{$ability}' is missing from matrix"
            );
        }
    }
}
