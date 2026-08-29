<?php declare(strict_types=1);

namespace App\Support;

/**
 * Service-Line classification provenance (GAP-046, Gate 2 §4).
 *
 * Declared enum stays the full SSOT §2.5 four-state set as general
 * future-proofing for a later, separately-scoped manual
 * classification/reclassification workflow. GAP-046 itself only ever
 * writes INFERRED rows (its backfill mechanism) — it never writes
 * CONFIRMED, and it never creates a membership row merely to store
 * NEEDS_REVIEW or UNKNOWN (those are subject-level states represented by
 * the absence of a membership row — see Gate 2 §4/§7).
 */
final class ServiceLineProvenance
{
    public const CONFIRMED = 'CONFIRMED';
    public const INFERRED = 'INFERRED';
    public const NEEDS_REVIEW = 'NEEDS_REVIEW';
    public const UNKNOWN = 'UNKNOWN';

    public const VALUES = [self::CONFIRMED, self::INFERRED, self::NEEDS_REVIEW, self::UNKNOWN];
}
