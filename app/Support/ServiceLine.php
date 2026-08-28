<?php declare(strict_types=1);

namespace App\Support;

/**
 * Canonical Service-Line value set (GAP-046, Gate 2 §2).
 *
 * Exactly three values, normatively fixed by
 * docs/superpowers/specs/2026-08-15-zena-one-page-management-canonical-semantics.md
 * §2.1. UNKNOWN and NEEDS_REVIEW are provenance states (see
 * ServiceLineProvenance), never Service-Line values — see Gate 2 §4.
 */
final class ServiceLine
{
    public const DESIGN = 'DESIGN';
    public const CONSTRUCTION = 'CONSTRUCTION';
    public const INSPECTION = 'INSPECTION';

    public const VALUES = [self::DESIGN, self::CONSTRUCTION, self::INSPECTION];
}
