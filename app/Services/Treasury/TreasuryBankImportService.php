<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Treasury\TreasuryBankStatementImport as Import;
use App\Models\Treasury\TreasuryBankStatementLine as Line;
use App\Models\Treasury\TreasuryLedgerEntry as Entry;
use App\Models\Treasury\TreasuryReconciliation as Reconciliation;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * GAP-107 (Gate 2 I-1) — bank statement import for a bank wallet: a CSV file is parsed with a user column map, its lines
 * are stored, each unmatched line is offered at most one unreconciled ledger entry of the wallet (same direction, same
 * amount, date within ±3 days, nearest date first, each entry used once), and confirmed pairs are reconciled through
 * TreasuryReconciliationService (type bank_statement). A bank line never creates a Treasury document.
 */
class TreasuryBankImportService
{
    public const BANK_WALLET_TYPES = ['company_bank', 'owner_personal', 'employee_bank'];

    public const MAX_KILOBYTES = 2048;

    public const MAX_LINES = 5000;

    public const MATCH_DAYS = 3;

    /** Column-map keys; amount is either one signed column or debit + credit columns. */
    public const MAP_KEYS = ['date', 'amount', 'debit', 'credit', 'description', 'reference'];

    public function __construct(private readonly TreasuryReconciliationService $reconciliation)
    {
    }

    public function isBankWallet(TreasuryWallet $wallet): bool
    {
        return in_array($wallet->wallet_type, self::BANK_WALLET_TYPES, true);
    }

    /**
     * The column map of the wallet's latest import, to pre-fill the form.
     *
     * @return array<string, int|null>
     */
    public function lastColumnMap(TreasuryWallet $wallet): array
    {
        $map = Import::query()->withoutGlobalScopes()
            ->where('tenant_id', (string) $wallet->tenant_id)
            ->where('wallet_id', (string) $wallet->id)
            ->orderByDesc('created_at')
            ->value('column_map');
        $map = is_string($map) ? (array) json_decode($map, true) : (array) $map;

        return $map === [] ? ['date' => 0, 'amount' => 1, 'debit' => null, 'credit' => null, 'description' => 2, 'reference' => null] : $map;
    }

    /**
     * Parses a CSV statement. Returns the rows and the errors per line; nothing is saved.
     *
     * @param array<string, int|string|null> $map
     * @return array{rows: list<array{line_no: int, date: string, direction: string, cents: int, description: string|null, reference: string|null}>, errors: list<string>, header: list<string>}
     */
    public function parse(string $content, array $map, bool $hasHeader = true): array
    {
        $content = $this->toUtf8($content);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];
        $lines = array_values(array_filter($lines, static fn (string $line): bool => trim($line) !== ''));
        if (count($lines) > self::MAX_LINES + 1) {
            throw new TreasuryRuleViolation('Sao kê vượt quá ' . self::MAX_LINES . ' dòng.', 'file');
        }
        $delimiter = $this->delimiter($lines[0] ?? '');
        $map = $this->normaliseMap($map);

        $header = [];
        $rows = [];
        $errors = [];
        foreach ($lines as $index => $raw) {
            $cells = array_map(static fn ($cell): string => trim((string) $cell), str_getcsv($raw, $delimiter, '"', ''));
            if ($index === 0 && $hasHeader) {
                $header = $cells;
                continue;
            }
            $lineNo = $index + 1;
            $date = $this->date($cells[$map['date']] ?? '');
            if ($date === null) {
                $errors[] = "Dòng {$lineNo}: ngày không đọc được.";
                continue;
            }
            if ($map['amount'] !== null) {
                $cents = $this->cents($cells[$map['amount']] ?? '');
            } else {
                $debit = $this->cents($cells[(int) $map['debit']] ?? '');
                $credit = $this->cents($cells[(int) $map['credit']] ?? '');
                $cents = $debit === null && $credit === null ? null : (int) ($credit ?? 0) - (int) ($debit ?? 0);
            }
            if ($cents === null || $cents === 0) {
                $errors[] = "Dòng {$lineNo}: số tiền không đọc được hoặc bằng 0.";
                continue;
            }
            $rows[] = [
                'line_no' => $lineNo,
                'date' => $date,
                'direction' => $cents > 0 ? Line::DIRECTION_IN : Line::DIRECTION_OUT,
                'cents' => abs($cents),
                'description' => $map['description'] === null ? null : (($cells[$map['description']] ?? '') === '' ? null : mb_substr($cells[$map['description']], 0, 1000)),
                'reference' => $map['reference'] === null ? null : (($cells[$map['reference']] ?? '') === '' ? null : mb_substr($cells[$map['reference']], 0, 255)),
            ];
        }

        return ['rows' => $rows, 'errors' => $errors, 'header' => $header];
    }

    /**
     * @param array<string, int|string|null> $map
     */
    public function import(Project $project, User $actor, TreasuryWallet $wallet, UploadedFile $file, array $map, string $statementReference, bool $hasHeader = true): Import
    {
        $this->requireWallet($project, $wallet);
        if (!$this->isBankWallet($wallet)) {
            throw new TreasuryRuleViolation('Chỉ import sao kê cho ví ngân hàng.', 'wallet');
        }
        $reference = trim($statementReference);
        if ($reference === '') {
            throw new TreasuryRuleViolation('Cần nhập số / tên sao kê.', 'statement_reference');
        }
        if (!in_array(strtolower((string) $file->getClientOriginalExtension()), ['csv', 'txt'], true)) {
            throw new TreasuryRuleViolation('Chỉ nhận file CSV (lưu từ Excel bằng "CSV UTF-8").', 'file');
        }
        if ((int) $file->getSize() > self::MAX_KILOBYTES * 1024) {
            throw new TreasuryRuleViolation('File sao kê vượt quá 2 MB.', 'file');
        }
        $content = (string) file_get_contents((string) $file->getRealPath());
        $hash = hash('sha256', $content);
        $parsed = $this->parse($content, $map, $hasHeader);
        if ($parsed['errors'] !== []) {
            throw new TreasuryRuleViolation(implode(' ', array_slice($parsed['errors'], 0, 5)) . (count($parsed['errors']) > 5 ? ' …' : ''), 'file');
        }
        if ($parsed['rows'] === []) {
            throw new TreasuryRuleViolation('Sao kê không có dòng nào.', 'file');
        }
        $exists = Import::query()->withoutGlobalScopes()->where('tenant_id', (string) $wallet->tenant_id)->where('wallet_id', (string) $wallet->id)->where('file_hash', $hash)->exists();
        if ($exists) {
            throw new TreasuryRuleViolation('File sao kê này đã được import cho ví này.', 'file');
        }

        try {
            return DB::transaction(function () use ($project, $actor, $wallet, $file, $map, $reference, $hash, $parsed): Import {
                $import = Import::query()->create([
                    'tenant_id' => (string) $wallet->tenant_id,
                    'project_id' => (string) $project->id,
                    'wallet_id' => (string) $wallet->id,
                    'statement_reference' => mb_substr($reference, 0, 100),
                    'original_name' => mb_substr((string) $file->getClientOriginalName(), 0, 255),
                    'file_hash' => $hash,
                    'column_map' => $this->normaliseMap($map),
                    'line_count' => count($parsed['rows']),
                    'created_by' => (string) $actor->id,
                ]);
                $known = $this->knownFingerprints($wallet);
                foreach ($parsed['rows'] as $row) {
                    $fingerprint = hash('sha256', implode('|', [$wallet->id, $row['date'], $row['direction'], $row['cents'], $row['reference'] ?? '', $row['description'] ?? '']));
                    $duplicate = isset($known[$fingerprint]);
                    $known[$fingerprint] = true;
                    Line::query()->create([
                        'tenant_id' => (string) $wallet->tenant_id,
                        'import_id' => (string) $import->id,
                        'line_no' => $row['line_no'],
                        'transaction_date' => $row['date'],
                        'direction' => $row['direction'],
                        'amount' => TreasuryBalanceService::fromCents($row['cents']),
                        'description' => $row['description'],
                        'bank_reference' => $row['reference'],
                        'fingerprint' => $fingerprint,
                        'status' => $duplicate ? Line::STATUS_DUPLICATE : Line::STATUS_UNMATCHED,
                    ]);
                }
                $this->audit($actor, $project, 'treasury.bank_import.imported', (string) $import->id, [
                    'wallet_id' => (string) $wallet->id, 'statement_reference' => $import->statement_reference, 'lines' => $import->line_count, 'file_hash' => $hash,
                ]);

                return $import;
            });
        } catch (UniqueConstraintViolationException) {
            throw new TreasuryRuleViolation('File sao kê này đã được import cho ví này.', 'file');
        }
    }

    /**
     * One suggested unreconciled ledger entry per unmatched line (or none).
     *
     * @return array<string, array<string, mixed>> keyed by line id
     */
    public function suggestions(Project $project, Import $import): array
    {
        $wallet = $this->walletOf($project, $import);
        $candidates = $this->reconciliation->unreconciledEntries($wallet);
        $taken = array_flip(Line::query()->withoutGlobalScopes()->where('tenant_id', (string) $import->tenant_id)
            ->where('status', Line::STATUS_MATCHED)->whereNotNull('ledger_entry_id')->pluck('ledger_entry_id')->map(static fn ($id): string => (string) $id)->all());

        $lines = $this->lines($import)->where('status', Line::STATUS_UNMATCHED);
        $result = [];
        foreach ($lines as $line) {
            $best = null;
            $bestDays = null;
            foreach ($candidates as $candidate) {
                $id = $candidate['ledger_entry_id'];
                if (isset($taken[$id]) || $candidate['transaction_date'] === null) {
                    continue;
                }
                $direction = $candidate['direction'] === Entry::DIRECTION_CREDIT ? Line::DIRECTION_IN : Line::DIRECTION_OUT;
                if ($direction !== $line->direction || TreasuryBalanceService::toCents($candidate['amount']) !== TreasuryBalanceService::toCents((string) $line->amount)) {
                    continue;
                }
                $days = (int) abs(Carbon::parse($candidate['transaction_date'])->diffInDays($line->transaction_date));
                if ($days > self::MATCH_DAYS || ($bestDays !== null && $days >= $bestDays)) {
                    continue;
                }
                $best = $candidate;
                $bestDays = $days;
            }
            if ($best !== null) {
                $taken[$best['ledger_entry_id']] = true;
                $result[(string) $line->id] = $best;
            }
        }

        return $result;
    }

    /**
     * Confirms line → ledger-entry pairs through one bank reconciliation.
     *
     * @param array<string, string> $pairs line id => ledger entry id
     */
    public function confirm(Project $project, User $actor, Import $import, array $pairs): Reconciliation
    {
        $wallet = $this->walletOf($project, $import);
        $pairs = array_filter($pairs, static fn ($entry, $line): bool => (string) $entry !== '' && (string) $line !== '', ARRAY_FILTER_USE_BOTH);
        if ($pairs === []) {
            throw new TreasuryRuleViolation('Chọn ít nhất một cặp để đối soát.', 'pairs');
        }
        if (count(array_unique(array_values($pairs))) !== count($pairs)) {
            throw new TreasuryRuleViolation('Mỗi giao dịch chỉ ghép với một dòng sao kê.', 'pairs');
        }

        return DB::transaction(function () use ($project, $actor, $import, $wallet, $pairs): Reconciliation {
            $lines = Line::query()->withoutGlobalScopes()
                ->where('tenant_id', (string) $import->tenant_id)
                ->where('import_id', (string) $import->id)
                ->whereIn('id', array_keys($pairs))
                ->lockForUpdate()
                ->get()
                ->keyBy(static fn ($line): string => (string) $line->id);
            foreach ($pairs as $lineId => $entryId) {
                /** @var Line|null $line */
                $line = $lines->get((string) $lineId);
                if ($line === null || $line->status !== Line::STATUS_UNMATCHED) {
                    throw new TreasuryRuleViolation('Có dòng sao kê không còn chờ ghép, hãy tải lại trang.', 'pairs');
                }
                /** @var Entry|null $entry */
                $entry = Entry::query()->withoutGlobalScopes()->where('tenant_id', (string) $wallet->tenant_id)->whereKey((string) $entryId)->first();
                $direction = data_get($entry, 'direction') === Entry::DIRECTION_CREDIT ? Line::DIRECTION_IN : Line::DIRECTION_OUT;
                if ($entry === null || (string) $entry->wallet_id !== (string) $wallet->id
                    || $direction !== $line->direction
                    || TreasuryBalanceService::toCents((string) $entry->amount) !== TreasuryBalanceService::toCents((string) $line->amount)) {
                    throw new TreasuryRuleViolation('Dòng ' . $line->line_no . ': giao dịch không khớp ví, chiều hoặc số tiền.', 'pairs');
                }
            }

            $reconciliation = $this->reconciliation->reconcile($project, $actor, $wallet, [
                'reconciliation_type' => TreasuryReconciliationService::TYPE_BANK_STATEMENT,
                'external_reference' => $import->statement_reference,
                'reconciled_at' => Carbon::today()->toDateString(),
                'ledger_entry_ids' => array_values(array_map('strval', $pairs)),
            ]);
            foreach ($pairs as $lineId => $entryId) {
                /** @var Line $line */
                $line = $lines->get((string) $lineId);
                $line->fill(['status' => Line::STATUS_MATCHED, 'ledger_entry_id' => (string) $entryId, 'reconciliation_id' => (string) $reconciliation->id])->save();
            }
            $this->audit($actor, $project, 'treasury.bank_import.matched', (string) $import->id, [
                'reconciliation_id' => (string) $reconciliation->id, 'pairs' => $pairs,
            ]);

            return $reconciliation;
        });
    }

    public function ignore(Project $project, User $actor, Import $import, string $lineId, string $note): void
    {
        $this->walletOf($project, $import);
        if (trim($note) === '') {
            throw new TreasuryRuleViolation('Bỏ qua dòng sao kê cần ghi lý do.', 'note');
        }
        DB::transaction(function () use ($project, $actor, $import, $lineId, $note): void {
            /** @var Line|null $line */
            $line = Line::query()->withoutGlobalScopes()->where('tenant_id', (string) $import->tenant_id)
                ->where('import_id', (string) $import->id)->whereKey($lineId)->lockForUpdate()->first();
            if ($line === null || $line->status !== Line::STATUS_UNMATCHED) {
                throw new TreasuryRuleViolation('Dòng sao kê không còn chờ ghép.', 'note');
            }
            $line->fill(['status' => Line::STATUS_IGNORED, 'note' => mb_substr(trim($note), 0, 500)])->save();
            $this->audit($actor, $project, 'treasury.bank_import.line_ignored', (string) $import->id, ['line_id' => (string) $line->id, 'note' => $line->note]);
        });
    }

    /**
     * Called by reconciliation undo: lines matched to these ledger entries go back to unmatched.
     *
     * @param list<string> $ledgerEntryIds
     */
    public function reopenForEntries(string $tenantId, array $ledgerEntryIds): void
    {
        if ($ledgerEntryIds === []) {
            return;
        }
        Line::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', Line::STATUS_MATCHED)
            ->whereIn('ledger_entry_id', $ledgerEntryIds)
            ->update(['status' => Line::STATUS_UNMATCHED, 'ledger_entry_id' => null, 'reconciliation_id' => null, 'updated_at' => now()]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Line>
     */
    public function lines(Import $import): \Illuminate\Support\Collection
    {
        /** @var \Illuminate\Support\Collection<int, Line> $lines */
        $lines = Line::query()->withoutGlobalScopes()
            ->where('tenant_id', (string) $import->tenant_id)
            ->where('import_id', (string) $import->id)
            ->orderBy('line_no')
            ->get()
            ->toBase();

        return $lines;
    }

    /**
     * @return list<Import>
     */
    public function imports(TreasuryWallet $wallet): array
    {
        /** @var list<Import> $rows */
        $rows = Import::query()->withoutGlobalScopes()
            ->where('tenant_id', (string) $wallet->tenant_id)
            ->where('wallet_id', (string) $wallet->id)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->values()
            ->all();

        return $rows;
    }

    public function importOf(Project $project, string $id): ?Import
    {
        /** @var Import|null $import */
        $import = Import::query()->withoutGlobalScopes()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->whereKey($id)
            ->first();

        return $import;
    }

    /**
     * @return array<string, true>
     */
    private function knownFingerprints(TreasuryWallet $wallet): array
    {
        $importIds = Import::query()->withoutGlobalScopes()->where('tenant_id', (string) $wallet->tenant_id)->where('wallet_id', (string) $wallet->id)->pluck('id');

        return array_fill_keys(Line::query()->withoutGlobalScopes()->where('tenant_id', (string) $wallet->tenant_id)
            ->whereIn('import_id', $importIds)->pluck('fingerprint')->map(static fn ($f): string => (string) $f)->all(), true);
    }

    private function walletOf(Project $project, Import $import): TreasuryWallet
    {
        if ((string) $import->tenant_id !== (string) $project->tenant_id || (string) $import->project_id !== (string) $project->id) {
            throw new TreasuryRuleViolation('Sao kê không thuộc dự án này.', 'import');
        }
        /** @var TreasuryWallet $wallet */
        $wallet = TreasuryWallet::query()->withoutGlobalScopes()->where('tenant_id', (string) $project->tenant_id)->whereKey((string) $import->wallet_id)->firstOrFail();

        return $wallet;
    }

    private function requireWallet(Project $project, TreasuryWallet $wallet): void
    {
        if ((string) $wallet->tenant_id !== (string) $project->tenant_id || (string) $wallet->project_id !== (string) $project->id) {
            throw new TreasuryRuleViolation('Ví không thuộc dự án này.', 'wallet');
        }
    }

    /**
     * @param array<string, int|string|null> $map
     * @return array{date: int, amount: int|null, debit: int|null, credit: int|null, description: int|null, reference: int|null}
     */
    private function normaliseMap(array $map): array
    {
        $col = static fn ($value): ?int => $value === null || $value === '' ? null : max(0, (int) $value);
        $normal = [
            'date' => $col($map['date'] ?? null),
            'amount' => $col($map['amount'] ?? null),
            'debit' => $col($map['debit'] ?? null),
            'credit' => $col($map['credit'] ?? null),
            'description' => $col($map['description'] ?? null),
            'reference' => $col($map['reference'] ?? null),
        ];
        if ($normal['date'] === null) {
            throw new TreasuryRuleViolation('Chọn cột ngày.', 'column_map');
        }
        if ($normal['amount'] === null && ($normal['debit'] === null || $normal['credit'] === null)) {
            throw new TreasuryRuleViolation('Chọn cột số tiền, hoặc cả hai cột Nợ và Có.', 'column_map');
        }

        /** @var array{date: int, amount: int|null, debit: int|null, credit: int|null, description: int|null, reference: int|null} $normal */
        return $normal;
    }

    private function delimiter(string $firstLine): string
    {
        $best = ',';
        $bestCount = -1;
        foreach ([',', ';', "\t"] as $candidate) {
            $count = substr_count($firstLine, $candidate);
            if ($count > $bestCount) {
                $best = $candidate;
                $bestCount = $count;
            }
        }

        return $best;
    }

    private function toUtf8(string $content): string
    {
        if (mb_check_encoding($content, 'UTF-8')) {
            return $content;
        }
        $converted = @iconv('Windows-1258', 'UTF-8//IGNORE', $content);
        if ($converted === false || $converted === '') {
            $converted = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        return (string) $converted;
    }

    private function date(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m) === 1) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]) : null;
        }
        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})/', $value, $m) === 1) {
            return checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) : null;
        }

        return null;
    }

    /** Signed cents from "1.234.567", "1,234,567.00", "-500", "(500)", "1 234 567,50". */
    private function cents(string $value): ?int
    {
        $value = trim(str_replace([' ', "\u{00A0}", 'VND', 'đ', '₫'], '', $value));
        if ($value === '') {
            return null;
        }
        $negative = str_starts_with($value, '-') || (str_starts_with($value, '(') && str_ends_with($value, ')'));
        $value = trim($value, '-()+');
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $value) === 1 || preg_match('/^\d+,\d{1,2}$/', $value) === 1) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        } elseif (preg_match('/^\d{1,3}(,\d{3})+(\.\d{1,2})?$/', $value) === 1) {
            $value = str_replace(',', '', $value);
        }
        if (preg_match('/^\d+(\.\d{1,2})?$/', $value) !== 1) {
            return null;
        }
        $cents = TreasuryBalanceService::toCents($value);

        return $negative ? -$cents : $cents;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function audit(User $actor, Project $project, string $action, string $importId, array $data): void
    {
        AuditLog::query()->create([
            'user_id' => (string) $actor->id,
            'tenant_id' => (string) $project->tenant_id,
            'project_id' => (string) $project->id,
            'action' => $action,
            'entity_type' => 'treasury_bank_statement_import',
            'entity_id' => $importId,
            'new_data' => $data,
        ]);
    }
}
