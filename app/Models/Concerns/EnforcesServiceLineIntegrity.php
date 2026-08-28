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
 * Enforces, on every create():
 *  - service_line is one of the exact three canonical values;
 *  - provenance is one of the declared four-state enum;
 *  - tenant_id is derived from the row's true parent (looked up without
 *    the tenant global scope, so the check is truthful regardless of the
 *    acting/current tenant context) — never trusted from caller input.
 *    A tenant_id explicitly set on the model that disagrees with the
 *    parent's true tenant_id is rejected (fail closed), not silently
 *    overwritten.
 */
trait EnforcesServiceLineIntegrity
{
    protected static function bootEnforcesServiceLineIntegrity(): void
    {
        static::creating(function ($model): void {
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
                throw new RuntimeException('Cannot create a service-line row without a resolvable parent.');
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
}
