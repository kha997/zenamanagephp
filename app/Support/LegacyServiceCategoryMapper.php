<?php declare(strict_types=1);

namespace App\Support;

/**
 * GAP-048 §4 — the single shared legacy→canonical mapping source. Every
 * writer (BackfillOpportunityServiceLines,
 * Api\OpportunityController::store()/update(), Api\LeadController::convert())
 * MUST consume this class rather than re-declaring the mapping table.
 */
final class LegacyServiceCategoryMapper
{
    /** @var array<string, string> */
    private const MAP = [
        'architecture' => ServiceLine::DESIGN,
        'interior' => ServiceLine::DESIGN,
        'landscape' => ServiceLine::DESIGN,
        'structure' => ServiceLine::DESIGN,
        'mep' => ServiceLine::DESIGN,
        'construction' => ServiceLine::CONSTRUCTION,
        // inspection, consulting, combined_package, null, and any
        // unrecognized value are deliberately absent — no membership row.
    ];

    public static function mapToServiceLine(?string $legacyCategory): ?string
    {
        if ($legacyCategory === null) {
            return null;
        }

        return self::MAP[$legacyCategory] ?? null;
    }
}
