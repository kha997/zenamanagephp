<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * GAP-129 C-1 — the signed-in user's own company: details, and renaming it. The slug never changes here (client-portal
 * links use it).
 */
final class CompanyAdminController extends Controller
{
    public function show(Request $request): View
    {
        $tenant = $this->tenant($request);

        return view('app.admin.company', [
            'tenant' => $tenant,
            'activeUsers' => User::query()->where('tenant_id', (string) $tenant->id)->where('is_active', true)->count(),
            'totalUsers' => User::query()->where('tenant_id', (string) $tenant->id)->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $tenant = $this->tenant($request);
        $old = $tenant->name;
        $tenant->name = trim($data['name']);
        $tenant->save();

        AuditLog::query()->create([
            'user_id' => (string) $request->user()?->id,
            'tenant_id' => (string) $tenant->id,
            'action' => 'admin.company.renamed',
            'entity_type' => 'tenant',
            'entity_id' => (string) $tenant->id,
            'old_data' => ['name' => $old],
            'new_data' => ['name' => $tenant->name, 'source' => 'web'],
        ]);

        return redirect()->route('app.admin.company')->with('success', 'Đã lưu tên công ty.');
    }

    private function tenant(Request $request): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::query()->findOrFail((string) $request->user()?->tenant_id);

        return $tenant;
    }
}
