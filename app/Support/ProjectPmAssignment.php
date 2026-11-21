<?php declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * UAT-RECOVERY-04d (O-15, Owner-approved "A-minimal"): who may change a project's pm_id.
 *
 * projects.pm_id is PM authority (TaskPolicy derives project-PM rights from it), so appointing a PM is an independent decision,
 * NOT a side effect of editing project metadata:
 *   - unchanged value: always allowed;
 *   - otherwise: same tenant AND the actor holds project.update (the endpoint gate) AND (the actor IS the project's current PM
 *     — hand-over — OR the actor holds the independent permission project.assign_manager);
 *   - the new PM (if any) must belong to the project's tenant;
 *   - a project WITHOUT a PM can only be given one by a project.assign_manager holder (no self-claim, no System Admin bypass).
 * Project CREATION still lets the creator (project.create) name the first PM — see ProjectService::createProject.
 * Every change is recorded in audit_logs as `project.manager.changed` with before/after values.
 */
final class ProjectPmAssignment
{
    public const PERMISSION = 'project.assign_manager';
    public const AUDIT_ACTION = 'project.manager.changed';

    /** May this actor change (appoint/replace/clear) the PM of this project at all? */
    public static function canChange(?User $actor, Model $project): bool
    {
        if ($actor === null || (string) $actor->tenant_id !== (string) $project->tenant_id) {
            return false;
        }

        if (!$actor->hasPermission('project.update')) {
            return false;
        }

        $current = $project->pm_id === null ? null : (string) $project->pm_id;

        return ($current !== null && $current === (string) $actor->id) || $actor->hasPermission(self::PERMISSION);
    }

    public static function allows(?User $actor, Model $project, mixed $newPmId): bool
    {
        if ($actor === null || (string) $actor->tenant_id !== (string) $project->tenant_id) {
            return false;
        }

        $new = $newPmId === null || $newPmId === '' ? null : (string) $newPmId;
        $current = $project->pm_id === null ? null : (string) $project->pm_id;

        if ($current === $new) {
            return true;
        }

        if (!self::canChange($actor, $project)) {
            return false;
        }

        // O-15 "no self-claim" is preserved for project.assign_manager holders (O-16): an actor may not appoint THEMSELVES as the
        // PM of a project they do not already manage — another holder (or the current PM's hand-over) must do it. Prevents a
        // System Admin from regaining task authority by self-appointment.
        if ($new !== null && $new === (string) $actor->id) {
            return false;
        }

        return $new === null
            || User::query()->whereKey($new)->where('tenant_id', $project->tenant_id)->exists();
    }

    /**
     * Writes the before/after audit record. Call INSIDE the same DB transaction as the change so a failed audit aborts it.
     */
    public static function audit(?User $actor, Model $project, ?string $oldPm, ?string $newPm, string $source): void
    {
        if ($oldPm === $newPm) {
            return;
        }

        DB::table('audit_logs')->insert([
            'user_id' => (string) ($actor?->id ?? $project->created_by ?? $newPm ?? $oldPm),
            'action' => self::AUDIT_ACTION,
            'entity_type' => 'project',
            'entity_id' => (string) $project->id,
            'project_id' => (string) $project->id,
            'tenant_id' => (string) $project->tenant_id,
            'old_data' => json_encode(['pm_id' => $oldPm]),
            'new_data' => json_encode(['pm_id' => $newPm]),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'route' => request()?->path(),
            'method' => request()?->method(),
            'meta' => json_encode(['source' => $source, 'actor_id' => $actor?->id]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
