<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

/**
 * Minimal RBAC helper for tests.
 * - Primary goal: prevent autoload fatal (missing trait file).
 * - Secondary goal: provide a couple of common helpers that degrade gracefully
 *   depending on what RBAC system exists in the app.
 */
trait InteractsWithRbac
{
    /**
     * Create a user and authenticate for tests.
     * If Sanctum exists, use Sanctum::actingAs; otherwise fallback to actingAs.
     */
    protected function signIn(?User $user = null, array $abilities = ['*']): User
    {
        $user ??= User::factory()->create();

        if (class_exists(Sanctum::class)) {
            Sanctum::actingAs($user, $abilities);
        } else {
            // For web-guard based tests
            $this->actingAs($user);
        }

        return $user;
    }

    /**
     * Best-effort: assign a role to a user if the project has a recognizable RBAC layer.
     * If no RBAC tables/classes are present, it becomes a no-op (tests that don't depend
     * on RBAC details still run).
     */
    protected function assignRole(User $user, string $role): void
    {
        // 1) Common pattern: Spatie permission
        if (method_exists($user, 'assignRole')) {
            try {
                $user->assignRole($role);
                return;
            } catch (\Throwable $e) {
                // fall through
            }
        }

        // 2) Custom models (try common names)
        $candidateRoleClasses = [
            \App\Models\ZenaRole::class,
            \App\Models\UserRole::class,
            \App\Models\Role::class,
        ];

        foreach ($candidateRoleClasses as $cls) {
            if (class_exists($cls)) {
                try {
                    // Try "code" then "name"
                    $roleRow = $cls::query()->where('code', $role)->orWhere('name', $role)->first();
                    if (!$roleRow) {
                        $roleRow = $cls::query()->create(['name' => $role, 'code' => $role]);
                    }

                    // Try common attach methods
                    if (method_exists($user, 'roles') && method_exists($user->roles(), 'attach')) {
                        $user->roles()->syncWithoutDetaching([$roleRow->getKey()]);
                        return;
                    }
                } catch (\Throwable $e) {
                    // fall through
                }
            }
        }

        // 3) Table-level fallback (best-effort)
        // Try a couple of common pivot tables
        $pivots = [
            ['table' => 'model_has_roles', 'user_col' => 'model_id', 'role_col' => 'role_id'],
            ['table' => 'user_roles', 'user_col' => 'user_id', 'role_col' => 'role_id'],
            ['table' => 'project_user_roles', 'user_col' => 'user_id', 'role_col' => 'role_id'],
        ];

        foreach ($pivots as $p) {
            if (!Schema::hasTable($p['table'])) continue;

            // Try to resolve role id from known role tables
            $roleId = null;
            foreach (['roles', 'user_roles', 'zena_roles'] as $roleTable) {
                if (!Schema::hasTable($roleTable)) continue;
                $cols = Schema::getColumnListing($roleTable);
                $nameCol = in_array('code', $cols, true) ? 'code' : (in_array('name', $cols, true) ? 'name' : null);
                if (!$nameCol) continue;

                $roleId = DB::table($roleTable)->where($nameCol, $role)->value('id');
                if (!$roleId) {
                    $insert = [$nameCol => $role];
                    if (in_array('created_at', $cols, true)) $insert['created_at'] = now();
                    if (in_array('updated_at', $cols, true)) $insert['updated_at'] = now();
                    $roleId = DB::table($roleTable)->insertGetId($insert);
                }
                if ($roleId) break;
            }

            if ($roleId) {
                try {
                    DB::table($p['table'])->updateOrInsert(
                        [$p['user_col'] => $user->getKey(), $p['role_col'] => $roleId],
                        []
                    );
                    return;
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }
    }

    /**
     * Convenience: sign in user and best-effort assign role.
     */
    protected function signInWithRole(string $role = 'admin', array $abilities = ['*']): User
    {
        $user = $this->signIn(null, $abilities);
        $this->assignRole($user, $role);
        return $user;
    }
}
