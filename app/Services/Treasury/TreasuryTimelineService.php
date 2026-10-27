<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\Models\Project;
use App\Models\Treasury\TreasuryAdvance as Advance;
use App\Models\Treasury\TreasuryAdvanceSettlement as Settlement;
use App\Models\Treasury\TreasuryExpenseApproval as Approval;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryPaymentRoute as Route;
use App\Models\Treasury\TreasuryPaymentRouteLeg as Leg;
use App\Models\Treasury\TreasuryWallet;
use Illuminate\Support\Carbon;

/**
 * GAP-097 — Treasury S6 financial timeline (spec §10.7, approved Gate 2
 * Option A): one sentence per money event of a month, oldest first, each
 * linked to its document, route or advance. Deterministic, read-only.
 */
class TreasuryTimelineService
{
    /**
     * @return list<array{at: string, kind: string, sentence: string, amount: string, link: array{type: string, id: string}|null}>
     */
    public function month(Project $project, string $month): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfDay();
        $end = $start->copy()->endOfMonth();
        $wallets = TreasuryWallet::query()->where('tenant_id', (string) $project->tenant_id)->where('project_id', (string) $project->id)->pluck('name', 'id')->all();
        $parties = TreasuryFinancialParty::query()->where('tenant_id', (string) $project->tenant_id)->pluck('name', 'id')->all();
        $wallet = static fn (mixed $id): string => $id === null ? 'bên ngoài' : 'ví ' . ($wallets[(string) $id] ?? '?');
        $party = static fn (mixed $id): string => (string) ($parties[(string) $id] ?? '?');
        $money = static fn (string $amount): string => number_format((float) $amount, 0, ',', '.') . ' ₫';
        $events = [];

        $selfApproved = Approval::query()->whereIn('event', ['approved', TreasuryAdvanceService::EVENT_APPROVED])->where('context->approval_mode', 'self_approval')
            ->pluck('financial_document_id')->map(static fn ($id): string => (string) $id)->all();
        $documents = Document::query()->where('tenant_id', (string) $project->tenant_id)->where('project_id', (string) $project->id)
            ->whereIn('status', TreasuryReportService::LEDGER_STATUSES)
            ->whereDate('transaction_date', '>=', $start->toDateString())->whereDate('transaction_date', '<=', $end->toDateString())
            ->get();
        $originals = Document::query()->whereIn('id', $documents->pluck('reversed_document_id')->filter()->all())->get()->keyBy('id');
        $routeOf = Route::query()->whereIn('linked_financial_document_id', $documents->pluck('id')->all())->pluck('id', 'linked_financial_document_id')->all();
        foreach ($documents as $doc) {
            /** @var Document $doc */
            $amount = (string) $doc->amount;
            $self = in_array((string) $doc->id, $selfApproved, true) ? ', tự duyệt' : '';
            $sentence = match ($doc->document_type) {
                Document::TYPE_FUNDING => $party($doc->source_party_id) . ' trả ' . $money($amount) . ' vào ' . $wallet($doc->destination_wallet_id)
                    . ($doc->posting_path === Document::POSTING_PATH_VIA_ROUTE ? ' (qua trung gian)' : ''),
                Document::TYPE_OWNER_CONTRIBUTION => $party($doc->source_party_id) . ' góp vốn ' . $money($amount) . ' vào ' . $wallet($doc->destination_wallet_id),
                Document::TYPE_INTERNAL_TRANSFER => ucfirst($wallet($doc->source_wallet_id)) . ' chuyển ' . $money($amount) . ' sang ' . $wallet($doc->destination_wallet_id),
                Document::TYPE_EXPENSE => ucfirst($wallet($doc->source_wallet_id)) . ' trả ' . $money($amount) . ' cho ' . $party($doc->destination_party_id) . ' (đã duyệt' . $self . ')',
                Document::TYPE_ADVANCE => ucfirst($wallet($doc->source_wallet_id)) . ' tạm ứng ' . $money($amount) . ' cho ' . $party($doc->destination_party_id) . ' (đã duyệt' . $self . ')',
                Document::TYPE_ADVANCE_RETURN => $party($doc->source_party_id) . ' nộp lại ' . $money($amount) . ' tạm ứng vào ' . $wallet($doc->destination_wallet_id),
                Document::TYPE_ADJUSTMENT => 'Điều chỉnh ' . ($doc->destination_wallet_id !== null ? 'tăng ' . $wallet($doc->destination_wallet_id) : 'giảm ' . $wallet($doc->source_wallet_id))
                    . ' ' . $money($amount) . ': ' . (string) $doc->description,
                default => 'Đảo ' . mb_strtolower(TreasuryReportService::TYPE_LABELS[(string) data_get($originals[(string) $doc->reversed_document_id] ?? null, 'document_type')] ?? 'chứng từ')
                    . ' ' . $money($amount) . ': ' . (string) $doc->description,
            };
            if ($doc->status === Document::STATUS_REVERSED) {
                $sentence .= ' — đã đảo';
            }
            $link = match (true) {
                isset($routeOf[(string) $doc->id]) => ['type' => 'route', 'id' => (string) $routeOf[(string) $doc->id]],
                $doc->document_type === Document::TYPE_ADVANCE => ['type' => 'advance', 'id' => (string) $doc->id],
                default => ['type' => 'document', 'id' => (string) $doc->id],
            };
            $events[] = $this->event($doc->transaction_date?->copy()->startOfDay() ?? $start, (string) $doc->created_at, 'document', $sentence, $amount, $link);
        }

        $routeIds = Route::query()->where('tenant_id', (string) $project->tenant_id)->where('project_id', (string) $project->id)->pluck('id')->all();
        $legs = Leg::query()->whereIn('payment_route_id', $routeIds)->whereNotNull('occurred_at')
            ->where('occurred_at', '>=', $start)->where('occurred_at', '<=', $end)->get();
        foreach ($legs as $leg) {
            $sentence = 'Chặng ' . (int) data_get($leg, 'sequence_no') . ': ' . ucfirst($wallet(data_get($leg, 'from_wallet_id'))) . ' → ' . $wallet(data_get($leg, 'to_wallet_id'))
                . ' ' . $money((string) data_get($leg, 'amount')) . (data_get($leg, 'status') === Leg::STATUS_REVERSED ? ' — đã đảo' : '');
            $events[] = $this->event(
                Carbon::parse((string) data_get($leg, 'occurred_at')),
                (string) data_get($leg, 'created_at'),
                'route_leg',
                $sentence,
                (string) data_get($leg, 'amount'),
                ['type' => 'route', 'id' => (string) data_get($leg, 'payment_route_id')]
            );
        }

        $advances = Advance::query()->where('tenant_id', (string) $project->tenant_id)->where('project_id', (string) $project->id)->get()->keyBy('id');
        $settlements = Settlement::query()->whereIn('advance_id', $advances->keys()->all())->where('settlement_type', Settlement::SETTLEMENT_TYPE_APPROVED_EXPENSE)
            ->where('created_at', '>=', $start)->where('created_at', '<=', $end)->get();
        foreach ($settlements as $settlement) {
            $advance = $advances[(string) data_get($settlement, 'advance_id')] ?? null;
            $apply = data_get($settlement, 'direction') === Settlement::DIRECTION_APPLY;
            $sentence = ($apply ? 'Quyết toán ' : 'Gỡ quyết toán ') . $money((string) data_get($settlement, 'amount')) . ' tạm ứng của ' . $party(data_get($advance, 'financial_party_id'))
                . ($apply ? ' bằng chi phí' : '');
            $at = Carbon::parse((string) data_get($settlement, 'created_at'));
            $events[] = $this->event(
                $at->copy()->startOfDay(),
                $at->toIso8601String(),
                'settlement',
                $sentence,
                (string) data_get($settlement, 'amount'),
                ['type' => 'advance', 'id' => (string) data_get($advance, 'originating_financial_document_id')]
            );
        }

        usort($events, static fn (array $a, array $b): int => [$a['at'], $a['sort']] <=> [$b['at'], $b['sort']]);

        return array_map(static function (array $event): array {
            unset($event['sort']);

            return $event;
        }, $events);
    }

    /**
     * @param array{type: string, id: string}|null $link
     * @return array{at: string, sort: string, kind: string, sentence: string, amount: string, link: array{type: string, id: string}|null}
     */
    private function event(Carbon $at, string $sort, string $kind, string $sentence, string $amount, ?array $link): array
    {
        return [
            'at' => $at->toDateString(),
            'sort' => Carbon::parse($sort)->toIso8601String(),
            'kind' => $kind,
            'sentence' => $sentence,
            'amount' => TreasuryBalanceService::fromCents(TreasuryBalanceService::toCents($amount)),
            'link' => $link,
        ];
    }
}
