<?php declare(strict_types=1);

namespace App\Services\Crm;

use App\Models\EventRecord;
use App\Models\Opportunity;
use App\Models\OpportunityServiceLine;
use App\Models\Quote;
use App\Models\User;
use App\Support\ServiceLine;
use App\Support\ServiceLineProvenance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * GAP-048 §5/§19 — atomic desired-set canonical Service-Line reconciliation.
 *
 * NOT an EnforcesServiceLineIntegrity-protected naked delete: this service
 * performs its OWN explicit tenant/parent authorization (the trait's
 * `saving` hook never sees a DELETE) and its own lifecycle-invariant check,
 * under an exclusive Opportunity row lock (canonical lock order: Opportunity
 * row first, child rows after), inside one DB transaction with the audit
 * EventRecord(s).
 */
class OpportunityServiceLineClassificationService
{
    /**
     * @param list<string> $desiredServiceLines subset of ServiceLine::VALUES
     */
    public function reconcile(User $actor, Opportunity $opportunity, array $desiredServiceLines): Opportunity
    {
        foreach ($desiredServiceLines as $line) {
            if (! in_array($line, ServiceLine::VALUES, true)) {
                throw ValidationException::withMessages(['service_line' => ["Invalid Service Line [{$line}]."]]);
            }
        }
        $desiredServiceLines = array_values(array_unique($desiredServiceLines));

        return DB::transaction(function () use ($actor, $opportunity, $desiredServiceLines): Opportunity {
            // Canonical lock order: Opportunity row FIRST.
            $locked = Opportunity::query()
                ->withoutGlobalScope('tenant')
                ->whereKey($opportunity->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Explicit tenant/parent authorization — NOT delegated to
            // EnforcesServiceLineIntegrity, which cannot see the delete half.
            if ((string) $locked->tenant_id !== (string) $actor->tenant_id) {
                throw new AuthorizationException(
                    'Cross-tenant Service-Line reconciliation rejected.'
                );
            }

            $existingRows = OpportunityServiceLine::query()
                ->where('opportunity_id', $locked->id)
                ->lockForUpdate()
                ->get();

            if ($this->requiresConfirmedInvariant($locked) && count($desiredServiceLines) === 0) {
                throw ValidationException::withMessages([
                    'service_line' => ['At least one confirmed Service Line is required at this stage.'],
                ]);
            }

            // Remove rows no longer desired (CONFIRMED lines dropped from the
            // set, and any mapper-owned INFERRED row not superseded below).
            foreach ($existingRows as $row) {
                if (! in_array($row->service_line, $desiredServiceLines, true)) {
                    $priorProvenance = $row->provenance;
                    $serviceLine = $row->service_line;
                    $row->delete();
                    $this->recordEvent($locked, $actor, 'crm.opportunity.service_line_removed', [
                        'service_line' => $serviceLine,
                        'prior_provenance' => $priorProvenance,
                        'new_provenance' => null,
                    ]);
                }
            }

            // Create/confirm every desired line.
            foreach ($desiredServiceLines as $line) {
                $row = OpportunityServiceLine::query()
                    ->where('opportunity_id', $locked->id)
                    ->where('service_line', $line)
                    ->first();

                $priorProvenance = $row?->provenance;

                if ($row === null) {
                    $row = new OpportunityServiceLine([
                        'opportunity_id' => $locked->id,
                        'service_line' => $line,
                    ]);
                }

                $row->provenance = ServiceLineProvenance::CONFIRMED;
                $row->source = $row->source ?? 'confirm:ui';
                $row->save();

                $this->recordEvent($locked, $actor, 'crm.opportunity.service_line_confirmed', [
                    'service_line' => $line,
                    'prior_provenance' => $priorProvenance,
                    'new_provenance' => ServiceLineProvenance::CONFIRMED,
                ]);
            }

            return $locked->fresh() ?? $locked;
        });
    }

    /**
     * §5 binding invariant surfaces: active/gated pipeline stage, native
     * Quote SENT/ACCEPTED, external accepted snapshot, or already-won.
     */
    private function requiresConfirmedInvariant(Opportunity $opportunity): bool
    {
        $gatedStages = [
            Opportunity::STAGE_SCOPE_DEFINED,
            Opportunity::STAGE_PROPOSAL_DRAFT,
            Opportunity::STAGE_PROPOSAL_SENT,
            Opportunity::STAGE_NEGOTIATION,
            Opportunity::STAGE_CONTRACTING,
            Opportunity::STAGE_WON,
        ];

        if (in_array((string) $opportunity->pipeline_stage, $gatedStages, true)) {
            return true;
        }

        $hasSentOrAcceptedNativeQuote = Quote::query()
            ->where('opportunity_id', (string) $opportunity->id)
            ->where('tenant_id', (string) $opportunity->tenant_id)
            ->whereIn('status', [Quote::STATUS_SENT, Quote::STATUS_ACCEPTED])
            ->exists();

        if ($hasSentOrAcceptedNativeQuote) {
            return true;
        }

        $snapshot = $opportunity->external_quote_snapshot ?? [];

        return ($snapshot['status'] ?? null) === 'ACCEPTED';
    }

    private function recordEvent(Opportunity $opportunity, User $actor, string $eventKey, array $payload): void
    {
        EventRecord::query()->create([
            'tenant_id' => (string) $opportunity->tenant_id,
            'project_id' => $opportunity->converted_project_id,
            'aggregate_type' => 'opportunity',
            'aggregate_id' => (string) $opportunity->id,
            'event_key' => $eventKey,
            'actor_user_id' => (string) $actor->id,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }
}
