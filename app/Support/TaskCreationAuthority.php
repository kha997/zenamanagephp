<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * UAT-RECOVERY O-17 (Owner-approved Option B, 2026-11-24): who may create tasks in a project.
 *
 * An actor may create a task in project P only if ALL hold:
 *   1. the actor is an authenticated User of the same tenant as P;
 *   2. the actor holds the canonical `task.create` permission through real RBAC grants (User::hasPermission — no role-name
 *      shortcut, no `isSuperAdmin()`, nothing derived from the legacy `users.role` column); template application additionally
 *      requires the canonical `template.apply` permission;
 *   3. the actor is P's current PM (`projects.pm_id`) OR an active participant of P (a `project_user_roles` row with
 *      `deleted_at` NULL, the same membership predicate as TreasuryPolicy / GAP-063).
 * There is no System Admin and no `super_admin` exception: every actor satisfies the same three conditions.
 *
 * This class is called INSIDE each creation action, so the decision does not depend on route middleware (CB-4: the global
 * RoleBasedAccessControlMiddleware short-circuit for a role named `super_admin` is deliberately left untouched here).
 */
final class TaskCreationAuthority
{
    public const PERMISSION = 'task.create';
    public const TEMPLATE_PERMISSION = 'template.apply';
    public const AUDIT_ACTION = 'zena.task.created';

    public const DENY_UNAUTHENTICATED = 'unauthenticated';
    public const DENY_TENANT = 'tenant_mismatch';
    public const DENY_PERMISSION = 'missing_task_create';
    public const DENY_TEMPLATE_PERMISSION = 'missing_template_apply';
    public const DENY_RELATIONSHIP = 'no_project_relationship';

    /** Null when allowed; otherwise a stable machine-readable denial reason. */
    public static function denialReason(?Authenticatable $actor, Model $project, bool $viaTemplate = false): ?string
    {
        if (!$actor instanceof User) {
            return self::DENY_UNAUTHENTICATED;
        }

        $projectTenant = (string) $project->getAttribute('tenant_id');
        if ($projectTenant === '' || (string) $actor->tenant_id !== $projectTenant) {
            return self::DENY_TENANT;
        }

        if (!$actor->hasPermission(self::PERMISSION)) {
            return self::DENY_PERMISSION;
        }

        if ($viaTemplate && !$actor->hasPermission(self::TEMPLATE_PERMISSION)) {
            return self::DENY_TEMPLATE_PERMISSION;
        }

        if (!self::isProjectPm((string) $actor->id, $project) && !self::isActiveParticipant((string) $actor->id, $project)) {
            return self::DENY_RELATIONSHIP;
        }

        return null;
    }

    public static function allows(?Authenticatable $actor, Model $project, bool $viaTemplate = false): bool
    {
        return self::denialReason($actor, $project, $viaTemplate) === null;
    }

    public static function isProjectPm(string $userId, Model $project): bool
    {
        $pm = $project->getAttribute('pm_id');

        return $userId !== '' && $pm !== null && (string) $pm === $userId;
    }

    /** Active `project_user_roles` row for this project, held by a user of the project's own tenant. */
    public static function isActiveParticipant(string $userId, Model $project): bool
    {
        if ($userId === '') {
            return false;
        }

        return DB::table('project_user_roles')
            ->join('users', 'users.id', '=', 'project_user_roles.user_id')
            ->where('project_user_roles.project_id', (string) $project->getAttribute('id'))
            ->where('project_user_roles.user_id', $userId)
            ->whereNull('project_user_roles.deleted_at')
            ->where('users.tenant_id', (string) $project->getAttribute('tenant_id'))
            ->whereNull('users.deleted_at')
            ->exists();
    }

    /**
     * May this user be named on a task of the project (assignee, watcher, assignment user)? Same tenant as the project, not
     * deleted, AND the project's PM or an active participant. Never an existence oracle for other tenants: a foreign id is
     * simply "not eligible".
     */
    public static function eligibleAssignee(Model $project, mixed $userId): bool
    {
        if (!is_string($userId) || $userId === '') {
            return false;
        }

        $inTenant = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', (string) $project->getAttribute('tenant_id'))
            ->whereNull('deleted_at')
            ->exists();

        return $inTenant && (self::isProjectPm($userId, $project) || self::isActiveParticipant($userId, $project));
    }

    /** A task id that belongs to this very project (and therefore to its tenant). */
    public static function taskOfProject(Model $project, mixed $taskId): bool
    {
        if (!is_string($taskId) || $taskId === '') {
            return false;
        }

        return DB::table('tasks')
            ->where('id', $taskId)
            ->where('project_id', (string) $project->getAttribute('id'))
            ->where('tenant_id', (string) $project->getAttribute('tenant_id'))
            ->exists();
    }

    /** Warning-level record of a denial: actor, project, reason, source. Never the request body. */
    public static function logDenial(?Authenticatable $actor, ?Model $project, string $reason, string $source): void
    {
        Log::warning('Task creation denied', [
            'actor_id' => $actor instanceof User ? (string) $actor->id : null,
            'actor_tenant_id' => $actor instanceof User ? (string) $actor->tenant_id : null,
            'project_id' => $project ? (string) $project->getAttribute('id') : null,
            'reason' => $reason,
            'source' => $source,
        ]);
    }

    /**
     * Strict audit row for one created task. Call INSIDE the creating transaction: an insert failure throws and rolls the
     * creation back, so a task never exists without its audit record.
     */
    public static function audit(?Authenticatable $actor, Model $task, string $source): void
    {
        self::auditIds(
            $actor,
            (string) $task->getAttribute('id'),
            (string) $task->getAttribute('project_id'),
            (string) $task->getAttribute('tenant_id'),
            $source,
            $task->getAttribute('created_by') === null ? null : (string) $task->getAttribute('created_by'),
        );
    }

    /** Same strict audit row for a task inserted without a model (template application bulk insert). */
    public static function auditIds(?Authenticatable $actor, string $taskId, string $projectId, string $tenantId, string $source, ?string $fallbackUserId = null, string $entityType = 'task'): void
    {
        $actorId = $actor instanceof User ? (string) $actor->id : null;
        $request = request();
        $routeName = \Illuminate\Support\Facades\Route::currentRouteName(); // null outside HTTP (service called directly)

        DB::table('audit_logs')->insert([
            'user_id' => (string) ($actorId ?? $fallbackUserId),
            'action' => self::AUDIT_ACTION,
            'entity_type' => $entityType,
            'entity_id' => $taskId,
            'project_id' => $projectId,
            'tenant_id' => $tenantId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'route' => $request->path(),
            'method' => $request->method(),
            'status_code' => 201,
            'meta' => json_encode([
                'source' => $source,
                'route_name' => $routeName,
                'route_path' => $request->path(),
                'method' => $request->method(),
                'status_code' => 201,
                'tenant_id' => $tenantId,
                'user_id' => $actorId,
                'entity_id' => $taskId,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
