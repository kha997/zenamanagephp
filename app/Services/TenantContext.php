<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class TenantContext
{
    public static function id(?Request $request = null): ?string
    {
        $request = $request ?? request();

        $tenantId = null;

        if ($request?->attributes) {
            $tenantId = $request->attributes->get('tenant_id');
        }

        if (empty($tenantId) && app()->bound('current_tenant_id')) {
            $tenantId = app('current_tenant_id');
        }

        if (empty($tenantId) && Auth::check()) {
            $tenantId = Auth::user()?->tenant_id;
        }

        if ($tenantId === null) {
            return null;
        }

        return (string) $tenantId;
    }
}
