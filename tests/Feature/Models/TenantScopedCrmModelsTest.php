<?php declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Account;
use App\Models\DesignItem;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Traits\TenantScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class TenantScopedCrmModelsTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    /**
     * Static guard: these tenant-owned models must keep the TenantScope
     * trait so isolation never again depends on per-query where() calls.
     */
    public function test_tenant_owned_crm_models_use_the_tenant_scope_trait(): void
    {
        foreach ([Lead::class, Account::class, Opportunity::class, DesignItem::class] as $model) {
            $this->assertContains(
                TenantScope::class,
                class_uses_recursive($model),
                "{$model} must use App\\Traits\\TenantScope"
            );
        }
    }

    public function test_lead_queries_are_scoped_to_the_bound_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $makeLead = function (Tenant $t, string $hint) {
            $user = $this->createTenantUser($t, [], ['admin'], []);

            return Lead::query()->withoutGlobalScope('tenant')->create([
                'tenant_id' => (string) $t->id,
                'contact_hint' => $hint,
                'project_description' => 'desc',
                'source' => 'zalo',
                'status' => Lead::STATUS_NEW,
                'captured_by' => (string) $user->id,
            ]);
        };

        $makeLead($tenantA, 'lead-a');
        $makeLead($tenantB, 'lead-b');

        app()->instance('tenant', $tenantA);
        try {
            $hints = Lead::query()->pluck('contact_hint')->all();
            $this->assertSame(['lead-a'], $hints);
        } finally {
            app()->forgetInstance('tenant');
        }
    }

    public function test_lead_queries_are_unscoped_when_no_tenant_is_bound(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        foreach ([[$tenantA, 'lead-a'], [$tenantB, 'lead-b']] as [$tenant, $hint]) {
            $user = $this->createTenantUser($tenant, [], ['admin'], []);

            Lead::query()->create([
                'tenant_id' => (string) $tenant->id,
                'contact_hint' => $hint,
                'project_description' => 'desc',
                'source' => 'zalo',
                'status' => Lead::STATUS_NEW,
                'captured_by' => (string) $user->id,
            ]);
        }

        $this->assertSame(2, Lead::query()->count());
    }
}
