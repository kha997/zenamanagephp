<?php declare(strict_types=1);

namespace App\Services\Treasury;

use App\Models\Project;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryLedgerEntry as Entry;
use App\Models\Treasury\TreasuryWallet;
use Illuminate\Support\Facades\DB;

/**
 * GAP-064 — derived balances (v17 §5: wallet_balance = SUM(credit) − SUM(debit)).
 *
 * Nothing stores a balance; every figure is recomputed from the immutable
 * ledger. Money is handled as integer cents (decimal(15,2) columns) so no
 * float rounding and no bcmath dependency.
 */
class TreasuryBalanceService
{
    public function walletBalance(TreasuryWallet $wallet): string
    {
        return $this->balancesFor([(string) $wallet->id], (string) $wallet->tenant_id)[(string) $wallet->id] ?? '0.00';
    }

    /**
     * Balance read for the negative-balance guard: a locking read (shared lock
     * on the wallet's ledger rows), so it sees the latest committed entries
     * regardless of when the transaction's snapshot was taken, and waits for
     * any concurrent uncommitted posting on the same wallet.
     */
    public function lockedWalletBalance(TreasuryWallet $wallet): string
    {
        $cents = 0;
        $rows = Entry::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $wallet->tenant_id)
            ->where('wallet_id', (string) $wallet->id)
            ->sharedLock()
            ->get(['direction', 'amount']);

        foreach ($rows as $row) {
            $amount = self::toCents((string) $row->getAttribute('amount'));
            $cents += $row->getAttribute('direction') === Entry::DIRECTION_CREDIT ? $amount : -$amount;
        }

        return self::fromCents($cents);
    }

    /**
     * @return array{wallets: array<string, string>, held_total: string, investor_funding: string, owner_contribution: string}
     */
    public function projectSummary(Project $project): array
    {
        $walletIds = TreasuryWallet::query()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();

        $wallets = $this->balancesFor($walletIds, (string) $project->tenant_id);
        $held = array_sum(array_map([self::class, 'toCents'], $wallets));

        return [
            'wallets' => $wallets,
            'held_total' => self::fromCents($held),
            'investor_funding' => $this->postedTotal($project, Document::TYPE_FUNDING),
            'owner_contribution' => $this->postedTotal($project, Document::TYPE_OWNER_CONTRIBUTION),
        ];
    }

    /**
     * @param list<string> $walletIds
     * @return array<string, string>
     */
    private function balancesFor(array $walletIds, string $tenantId): array
    {
        $result = array_fill_keys($walletIds, '0.00');
        if ($walletIds === []) {
            return $result;
        }

        $rows = Entry::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('wallet_id', $walletIds)
            ->groupBy('wallet_id', 'direction')
            // ROUND(SUM*100) keeps SQLite's float SUM exact to the cent; MySQL decimals are exact anyway.
            ->select('wallet_id', 'direction', DB::raw('ROUND(SUM(amount) * 100) as total_cents'))
            ->get();

        $cents = array_fill_keys($walletIds, 0);
        foreach ($rows as $row) {
            $amount = (int) round((float) $row->getAttribute('total_cents'));
            $walletId = (string) $row->getAttribute('wallet_id');
            $cents[$walletId] += $row->getAttribute('direction') === Entry::DIRECTION_CREDIT ? $amount : -$amount;
        }

        foreach ($cents as $walletId => $value) {
            $result[$walletId] = self::fromCents($value);
        }

        return $result;
    }

    private function postedTotal(Project $project, string $type): string
    {
        $total = Document::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->where('document_type', $type)
            ->whereIn('status', [Document::STATUS_POSTED_UNRECONCILED, Document::STATUS_POSTED_RECONCILED])
            ->value(DB::raw('ROUND(COALESCE(SUM(amount), 0) * 100)'));

        return self::fromCents((int) round((float) $total));
    }

    public static function toCents(string|int|float $amount): int
    {
        $value = trim((string) $amount);
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);
        $cents = ((int) ($whole === '' ? '0' : $whole)) * 100 + (int) $fraction;

        return $negative ? -$cents : $cents;
    }

    public static function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign . intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
