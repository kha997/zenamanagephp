<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;

class TenancyService
{
    private ?string $currentTenantId = null;
    private ?Tenant $currentTenant = null;

    public function setTenantContext(string $tenantId, ?Tenant $tenant = null): void
    {
        $this->currentTenantId = $tenantId;
        $this->currentTenant = $tenant;

        app()->instance('current_tenant_id', $tenantId);

        if ($tenant) {
            app()->instance('tenant', $tenant);
        } elseif (app()->bound('tenant')) {
            app()->forgetInstance('tenant');
        }
    }

    public function currentTenantId(): ?string
    {
        return $this->currentTenantId;
    }

    public function currentTenant(): ?Tenant
    {
        return $this->currentTenant;
    }

    public function clearTenantContext(): void
    {
        $this->currentTenantId = null;
        $this->currentTenant = null;

        foreach (['current_tenant_id', 'tenant'] as $binding) {
            if (app()->bound($binding)) {
                app()->forgetInstance($binding);
            }
        }
    }

    public function resolveTenantIdForRequest(Request $request, ?User $user): ?string
    {
        $headerTenant = trim((string) $request->header('X-Tenant-ID'));

        if ($headerTenant !== '') {
            return $headerTenant;
        }

        $userTenant = trim((string) ($user?->tenant_id ?? ''));
        if ($userTenant !== '') {
            return $userTenant;
        }

        return null;
    }
}
