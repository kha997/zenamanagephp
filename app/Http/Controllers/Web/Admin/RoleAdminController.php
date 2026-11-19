<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Console\Commands\ProductionRbacCommand;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * GAP-129 R-1 — the canonical roles, read-only: description, the company's members with the role, and the permissions
 * grouped by module (the part of the code before the first dot).
 */
final class RoleAdminController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = (string) $request->user()?->tenant_id;
        $roles = [];
        foreach (ProductionRbacCommand::ROLES as $name => $description) {
            /** @var Role|null $role */
            $role = Role::query()->where('name', $name)->first();
            $permissions = $role === null ? collect() : $role->permissions()->get();
            $roles[] = [
                'name' => $name,
                'description' => $description,
                'members' => $role === null ? 0 : User::query()
                    ->where('tenant_id', $tenantId)
                    ->whereHas('roles', fn ($q) => $q->whereKey($role->id))
                    ->count(),
                'permissionCount' => $permissions->count(),
                'groups' => $permissions
                    ->map(fn ($p) => (string) ($p->code ?: $p->name))
                    ->sort()
                    ->groupBy(fn (string $code) => str_contains($code, '.') ? strstr($code, '.', true) : $code)
                    ->sortKeys()
                    ->all(),
            ];
        }

        return view('app.admin.roles', ['roles' => $roles]);
    }
}
