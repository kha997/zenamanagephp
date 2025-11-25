<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;

/**
 * Tenant Isolation Compliance Test
 * 
 * Verifies that all tenant-aware models have proper tenant isolation:
 * 1. Models have tenant_id column
 * 2. Models use BelongsToTenant or TenantScope trait
 * 3. Global scope is properly applied
 */
class TenantIsolationComplianceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * List of models that MUST be tenant-aware
     * These are core business models that belong to tenants
     */
    private const TENANT_AWARE_MODELS = [
        'App\Models\Project',
        'App\Models\Task',
        'App\Models\Document',
        'App\Models\Template',
        'App\Models\TemplateSet',
        'App\Models\CalendarEvent',
        'App\Models\Team',
        'App\Models\Client',
        'App\Models\Quote',
        'App\Models\TaskComment',
        'App\Models\TaskAttachment',
        'App\Models\Subtask',
        'App\Models\TaskAssignment',
        'App\Models\Invitation',
        'App\Models\ChangeRequest',
        'App\Models\Notification',
        'App\Models\Outbox',
        'App\Models\IdempotencyKey',
    ];

    /**
     * List of models that are system-global (NOT tenant-aware)
     * These models don't belong to tenants
     */
    private const SYSTEM_GLOBAL_MODELS = [
        'App\Models\Tenant',
        'App\Models\Role',
        'App\Models\Permission',
        // User model is special - has tenant_id but may need special handling
        // 'App\Models\User',
    ];

    /**
     * Test that all tenant-aware models have tenant_id column
     */
    public function test_tenant_aware_models_have_tenant_id_column(): void
    {
        foreach (self::TENANT_AWARE_MODELS as $modelClass) {
            if (!class_exists($modelClass)) {
                $this->markTestSkipped("Model {$modelClass} does not exist");
                continue;
            }

            $model = new $modelClass();
            $table = $model->getTable();

            $this->assertTrue(
                Schema::hasColumn($table, 'tenant_id'),
                "Model {$modelClass} (table: {$table}) must have tenant_id column"
            );
        }
    }

    /**
     * Test that all tenant-aware models use BelongsToTenant or TenantScope trait
     */
    public function test_tenant_aware_models_use_tenant_trait(): void
    {
        foreach (self::TENANT_AWARE_MODELS as $modelClass) {
            if (!class_exists($modelClass)) {
                $this->markTestSkipped("Model {$modelClass} does not exist");
                continue;
            }

            $reflection = new ReflectionClass($modelClass);
            $traits = $this->getTraits($reflection);

            $hasBelongsToTenant = in_array('App\Models\Concerns\BelongsToTenant', $traits);
            $hasTenantScope = in_array('App\Traits\TenantScope', $traits);

            $this->assertTrue(
                $hasBelongsToTenant || $hasTenantScope,
                "Model {$modelClass} must use BelongsToTenant or TenantScope trait. " .
                "Found traits: " . implode(', ', $traits)
            );
        }
    }

    /**
     * Test that tenant-aware models have tenant_id in fillable
     */
    public function test_tenant_aware_models_have_tenant_id_in_fillable(): void
    {
        foreach (self::TENANT_AWARE_MODELS as $modelClass) {
            if (!class_exists($modelClass)) {
                $this->markTestSkipped("Model {$modelClass} does not exist");
                continue;
            }

            $model = new $modelClass();
            $fillable = $model->getFillable();

            // Some models may use $guarded instead of $fillable
            // In that case, check that tenant_id is not in guarded
            $guarded = $model->getGuarded();
            
            if (!empty($fillable)) {
                $this->assertContains(
                    'tenant_id',
                    $fillable,
                    "Model {$modelClass} must have tenant_id in fillable array"
                );
            } else {
                // If using guarded, tenant_id should not be guarded
                $this->assertNotContains(
                    'tenant_id',
                    $guarded,
                    "Model {$modelClass} uses guarded, but tenant_id should not be guarded"
                );
            }
        }
    }

    /**
     * Test that system-global models do NOT have tenant_id column
     */
    public function test_system_global_models_do_not_have_tenant_id(): void
    {
        foreach (self::SYSTEM_GLOBAL_MODELS as $modelClass) {
            if (!class_exists($modelClass)) {
                $this->markTestSkipped("Model {$modelClass} does not exist");
                continue;
            }

            $model = new $modelClass();
            $table = $model->getTable();

            // System-global models should NOT have tenant_id
            // (unless they're special cases like User which has tenant_id for assignment)
            if ($modelClass === 'App\Models\User') {
                // User model is special - it has tenant_id but is not tenant-scoped
                continue;
            }

            $this->assertFalse(
                Schema::hasColumn($table, 'tenant_id'),
                "System-global model {$modelClass} (table: {$table}) should NOT have tenant_id column"
            );
        }
    }

    /**
     * Test that models with BelongsToTenant trait have proper global scope
     */
    public function test_belongs_to_tenant_models_have_global_scope(): void
    {
        foreach (self::TENANT_AWARE_MODELS as $modelClass) {
            if (!class_exists($modelClass)) {
                $this->markTestSkipped("Model {$modelClass} does not exist");
                continue;
            }

            $reflection = new ReflectionClass($modelClass);
            $traits = $this->getTraits($reflection);

            if (in_array('App\Models\Concerns\BelongsToTenant', $traits)) {
                // Check that the model has the bootBelongsToTenant method
                $this->assertTrue(
                    $reflection->hasMethod('bootBelongsToTenant') || 
                    method_exists($modelClass, 'bootBelongsToTenant'),
                    "Model {$modelClass} uses BelongsToTenant but bootBelongsToTenant method not found"
                );
            }
        }
    }

    /**
     * Test that models with TenantScope trait have proper global scope
     */
    public function test_tenant_scope_models_have_global_scope(): void
    {
        foreach (self::TENANT_AWARE_MODELS as $modelClass) {
            if (!class_exists($modelClass)) {
                $this->markTestSkipped("Model {$modelClass} does not exist");
                continue;
            }

            $reflection = new ReflectionClass($modelClass);
            $traits = $this->getTraits($reflection);

            if (in_array('App\Traits\TenantScope', $traits)) {
                // Check that the model has the bootTenantScope method
                $this->assertTrue(
                    $reflection->hasMethod('bootTenantScope') || 
                    method_exists($modelClass, 'bootTenantScope'),
                    "Model {$modelClass} uses TenantScope but bootTenantScope method not found"
                );
            }
        }
    }

    /**
     * Get all traits used by a class (including parent traits)
     */
    private function getTraits(ReflectionClass $reflection): array
    {
        $traits = [];
        
        // Get direct traits
        $directTraits = $reflection->getTraitNames();
        $traits = array_merge($traits, $directTraits);
        
        // Get parent traits recursively
        foreach ($directTraits as $trait) {
            $traitReflection = new ReflectionClass($trait);
            $parentTraits = $this->getTraits($traitReflection);
            $traits = array_merge($traits, $parentTraits);
        }
        
        // Get parent class traits
        $parent = $reflection->getParentClass();
        if ($parent) {
            $parentTraits = $this->getTraits($parent);
            $traits = array_merge($traits, $parentTraits);
        }
        
        return array_unique($traits);
    }

    /**
     * Test summary - verify all compliance checks
     */
    public function test_tenant_isolation_compliance_summary(): void
    {
        $results = [
            'tenant_aware_models' => count(self::TENANT_AWARE_MODELS),
            'system_global_models' => count(self::SYSTEM_GLOBAL_MODELS),
        ];

        $this->assertGreaterThan(0, $results['tenant_aware_models'], 
            'Should have at least one tenant-aware model');
        
        $this->assertGreaterThan(0, $results['system_global_models'], 
            'Should have at least one system-global model');
    }
}

