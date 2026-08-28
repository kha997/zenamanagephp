<?php declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\ServiceLine;
use App\Support\ServiceLineProvenance;
use InvalidArgumentException;
use RuntimeException;

/**
 * GAP-046 Gate 2 §4/§5 — shared write-path integrity for the two
 * Service-Line membership models (OpportunityServiceLine,
 * ProjectServiceLine).
 *
 * Enforces, on every persisted write (create AND update, via `saving` so
 * the same rules apply uniformly — Gate 3 Correction Round 1, item 2):
 *  - service_line is one of the exact three canonical values;
 *  - provenance is one of the declared four-state enum;
 *  - the parent (Opportunity/Project) is resolvable;
 *  - tenant_id is derived from the row's true parent (looked up without
 *    the tenant global scope, so the check is truthful regardless of the
 *    acting/current tenant context) — never trusted from caller input.
 *    A tenant_id explicitly set on the model that disagrees with the
 *    parent's true tenant_id is rejected (fail closed), not silently
 *    overwritten. This also covers an update that reassigns the parent
 *    to one belonging to a different tenant: the already-persisted
 *    tenant_id on the model becomes "explicit" input that is checked
 *    against the newly-resolved parent's tenant.
 *  - the ACTING/CURRENT tenant context (same precedence as
 *    App\Traits\TenantScope: app('tenant') -> current_tenant_id ->
 *    request attribute tenant_id; TenantScope itself is not modified)
 *    must not disagree with the parent's true tenant — Gate 3 Correction
 *    Round 1, item 1. A write with no bound tenant context (e.g. a
 *    legitimate CLI backfill) is unaffected by this check; the write is
 *    still permitted and tenant_id is still derived from the parent.
 */
trait EnforcesServiceLineIntegrity
{
    protected static function bootEnforcesServiceLineIntegrity(): void
    {
        static::saving(function ($model): void {
            if (!in_array($model->service_line, ServiceLine::VALUES, true)) {
                throw new InvalidArgumentException(
                    "Invalid service_line [{$model->service_line}]; must be one of: " . implode(', ', ServiceLine::VALUES)
                );
            }

            if (!in_array($model->provenance, ServiceLineProvenance::VALUES, true)) {
                throw new InvalidArgumentException(
                    "Invalid provenance [{$model->provenance}]; must be one of: " . implode(', ', ServiceLineProvenance::VALUES)
                );
            }

            $parentTenantId = $model->resolveParentTenantId();

            if ($parentTenantId === null) {
                throw new RuntimeException('Cannot persist a service-line row without a resolvable parent.');
            }

            $actingTenantId = static::resolveActingTenantId();

            if ($actingTenantId !== null && (string) $actingTenantId !== (string) $parentTenantId) {
                throw new RuntimeException(
                    'Cross-tenant service-line write rejected: the acting/current tenant context does not match the parent tenant.'
                );
            }

            if ($model->tenant_id !== null && (string) $model->tenant_id !== (string) $parentTenantId) {
                throw new RuntimeException(
                    'Cross-tenant service-line write rejected: child tenant_id does not match parent tenant_id.'
                );
            }

            $model->tenant_id = $parentTenantId;
        });
    }

    /**
     * Resolve the true tenant_id of this row's parent record, bypassing
     * the tenant global scope so the invariant check is truthful
     * regardless of the caller's current tenant context.
     */
    abstract protected function resolveParentTenantId(): ?string;

    /**
     * Resolve the acting/current tenant context, if any, using the exact
     * same precedence order as App\Traits\TenantScope (not itself
     * modified by GAP-046): app('tenant') -> current_tenant_id bound in
     * the container -> request()->attributes['tenant_id']. Returns null
     * when no tenant context is bound (e.g. a console/CLI process),
     * which permits a parent-derived write to proceed unimpeded.
     */
    protected static function resolveActingTenantId(): ?string
    {
        if (app()->has('tenant')) {
            $tenant = app('tenant');

            return $tenant?->id !== null ? (string) $tenant->id : null;
        }

        if (app()->bound('current_tenant_id')) {
            $tenantId = app('current_tenant_id');

            return $tenantId !== null ? (string) $tenantId : null;
        }

        if (function_exists('request') && request()?->attributes->has('tenant_id')) {
            $tenantId = request()->attributes->get('tenant_id');

            return $tenantId !== null ? (string) $tenantId : null;
        }

        return null;
    }
}
