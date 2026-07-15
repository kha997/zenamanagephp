<?php declare(strict_types=1);

namespace App\Services;

use App\Models\EventRecord;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuoteLifecycleService
{
    /**
     * @param array{actor_user_id?: string|null, actor_account_id?: string|null, source: string, note?: string|null} $context
     * @throws ValidationException khi transition không hợp lệ
     */
    public function accept(Quote $quote, array $context): Quote
    {
        if (!Quote::canTransition($quote->status, Quote::STATUS_ACCEPTED)) {
            throw ValidationException::withMessages(['action' => 'Không thể chuyển trạng thái.']);
        }

        $tenantId = (string) $quote->tenant_id;

        DB::transaction(function () use ($quote, $tenantId) {
            $quote->update([
                'status' => Quote::STATUS_ACCEPTED,
                'decided_at' => now(),
            ]);

            Quote::query()
                ->where('tenant_id', $tenantId)
                ->where('opportunity_id', $quote->opportunity_id)
                ->where('id', '!=', $quote->id)
                ->whereIn('status', [Quote::STATUS_DRAFT, Quote::STATUS_SENT, Quote::STATUS_REJECTED])
                ->update(['status' => Quote::STATUS_SUPERSEDED]);
        });

        $payload = [
            'quote_number' => $quote->quote_number,
            'source' => $context['source'],
        ];
        if (isset($context['actor_account_id'])) {
            $payload['actor_account_id'] = $context['actor_account_id'];
        }

        EventRecord::query()->create([
            'tenant_id' => $tenantId,
            'aggregate_type' => 'quote',
            'aggregate_id' => (string) $quote->id,
            'event_key' => 'quote.accepted',
            'actor_user_id' => $context['actor_user_id'] ?? null,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);

        return $quote->fresh();
    }

    /**
     * @param array{actor_user_id?: string|null, actor_account_id?: string|null, source: string, note?: string|null} $context
     * @throws ValidationException khi transition không hợp lệ
     */
    public function reject(Quote $quote, array $context): Quote
    {
        if (!Quote::canTransition($quote->status, Quote::STATUS_REJECTED)) {
            throw ValidationException::withMessages(['action' => 'Không thể chuyển trạng thái.']);
        }

        $quote->update([
            'status' => Quote::STATUS_REJECTED,
            'decided_at' => now(),
        ]);

        $payload = [
            'quote_number' => $quote->quote_number,
            'source' => $context['source'],
        ];
        if (isset($context['actor_account_id'])) {
            $payload['actor_account_id'] = $context['actor_account_id'];
        }
        if (isset($context['note'])) {
            $payload['note'] = $context['note'];
        }

        EventRecord::query()->create([
            'tenant_id' => (string) $quote->tenant_id,
            'aggregate_type' => 'quote',
            'aggregate_id' => (string) $quote->id,
            'event_key' => 'quote.rejected',
            'actor_user_id' => $context['actor_user_id'] ?? null,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);

        return $quote->fresh();
    }
}
