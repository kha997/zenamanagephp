<?php declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Account;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalAccountAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantSlug = (string) $request->route('tenantSlug');
        $tenant = Tenant::where('slug', $tenantSlug)->first();

        if (!$tenant) {
            abort(404);
        }

        if (!Auth::guard('client')->check()) {
            return redirect()->route('portal.login', ['tenantSlug' => $tenantSlug]);
        }

        $account = Auth::guard('client')->user();

        if (!$account instanceof Account || (string) $account->tenant_id !== (string) $tenant->id) {
            Auth::guard('client')->logout();

            return redirect()->route('portal.login', ['tenantSlug' => $tenantSlug]);
        }

        $request->attributes->set('portalTenant', $tenant);

        return $next($request);
    }
}
