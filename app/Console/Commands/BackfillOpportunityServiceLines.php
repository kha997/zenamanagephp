<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Opportunity;
use App\Models\OpportunityServiceLine;
use App\Support\ServiceLine;
use App\Support\ServiceLineProvenance;
use Illuminate\Console\Command;

/**
 * GAP-046 Gate 2 §7 — Opportunity-side legacy backfill.
 *
 * Maps the legacy Opportunity.service_category scalar to a canonical
 * Service-Line membership row, conservatively:
 *
 *   architecture|interior|landscape|structure|mep -> DESIGN / INFERRED
 *   construction                                  -> CONSTRUCTION / INFERRED
 *   inspection|consulting|combined_package        -> no row (ambiguous)
 *   null|unrecognized                             -> no row (unknown-by-absence)
 *
 * Never writes CONFIRMED. Never touches service_category. Idempotent via
 * firstOrCreate keyed on the (tenant_id, opportunity_id, service_line)
 * unique constraint.
 */
class BackfillOpportunityServiceLines extends Command
{
    protected $signature = 'service-lines:backfill-opportunities {--chunk=500} {--dry-run}';

    protected $description = 'Backfill canonical Service-Line rows for Opportunities from their legacy service_category (GAP-046)';

    /**
     * @var array<string, string>
     */
    private const MAP = [
        'architecture' => ServiceLine::DESIGN,
        'interior' => ServiceLine::DESIGN,
        'landscape' => ServiceLine::DESIGN,
        'structure' => ServiceLine::DESIGN,
        'mep' => ServiceLine::DESIGN,
        'construction' => ServiceLine::CONSTRUCTION,
        // inspection, consulting, combined_package, and any unrecognized
        // value are deliberately absent from this map — no membership row
        // is created for them (Gate 2 §7 cases E/F).
    ];

    public function handle(): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $baseQuery = Opportunity::query()->select(['id', 'tenant_id', 'service_category']);
        $totalOpportunities = (clone $baseQuery)->count();

        if ($totalOpportunities === 0) {
            $this->info('No Opportunities found.');

            return Command::SUCCESS;
        }

        $wouldCreate = 0;
        $skipped = 0;
        $created = 0;
        $alreadyPresent = 0;

        $baseQuery->orderBy('id')->chunkById($chunkSize, function ($rows) use (
            $dryRun,
            &$wouldCreate,
            &$skipped,
            &$created,
            &$alreadyPresent
        ): void {
            foreach ($rows as $opportunity) {
                $line = self::MAP[$opportunity->service_category] ?? null;

                if ($line === null) {
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $wouldCreate++;
                    continue;
                }

                $existed = OpportunityServiceLine::query()
                    ->where('tenant_id', $opportunity->tenant_id)
                    ->where('opportunity_id', $opportunity->id)
                    ->where('service_line', $line)
                    ->exists();

                OpportunityServiceLine::query()->firstOrCreate(
                    [
                        'tenant_id' => $opportunity->tenant_id,
                        'opportunity_id' => $opportunity->id,
                        'service_line' => $line,
                    ],
                    [
                        'provenance' => ServiceLineProvenance::INFERRED,
                        'source' => 'backfill:legacy_service_category',
                    ]
                );

                if ($existed) {
                    $alreadyPresent++;
                } else {
                    $created++;
                }
            }
        });

        if ($dryRun) {
            $this->info("Dry run: {$wouldCreate} Service-Line rows would be created; {$skipped} Opportunities would produce no row.");

            return Command::SUCCESS;
        }

        $this->info("Created {$created} Service-Line rows ({$alreadyPresent} already present, idempotent no-op); {$skipped} Opportunities produced no row.");

        return Command::SUCCESS;
    }
}
