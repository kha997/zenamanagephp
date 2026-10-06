<?php declare(strict_types=1);

namespace App\Services\Treasury;

use App\Models\Project;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * GAP-063 S1 — financial parties (tenant level) and project wallets.
 *
 * Shared by the API and the operator web pages so both enforce the same
 * types, same-tenant links and delete-when-unreferenced rule. Wallets are
 * project-scoped only in S1 (company wallets come later) and their
 * project_id never changes after creation.
 */
class TreasurySetupService
{
    /** PR #245 §7.1 (validated in the application; the column is free text). */
    public const PARTY_TYPES = [
        'investor' => 'Nhà đầu tư / chủ đầu tư',
        'intermediary' => 'Bên trung gian',
        'owner' => 'Chủ doanh nghiệp',
        'employee' => 'Nhân viên',
        'labour' => 'Tổ đội / nhân công',
        'supplier' => 'Nhà cung cấp',
        'subcontractor' => 'Thầu phụ',
        'authority' => 'Cơ quan nhà nước',
        'other' => 'Khác',
    ];

    /** PR #245 §7.2. */
    public const WALLET_TYPES = [
        'company_bank' => 'Tài khoản ngân hàng công ty',
        'company_cash' => 'Tiền mặt công ty',
        'owner_personal' => 'Tài khoản cá nhân của chủ',
        'employee_cash' => 'Tiền mặt nhân viên giữ',
        'employee_bank' => 'Tài khoản nhân viên',
        'intermediary_control' => 'Tài khoản bên trung gian',
        'other' => 'Khác',
    ];

    /** Every column that references a party (GAP-037 v17 schema). */
    private const PARTY_REFERENCES = [
        ['treasury_wallets', 'custodian_party_id'],
        ['treasury_financial_documents', 'source_party_id'],
        ['treasury_financial_documents', 'destination_party_id'],
        ['treasury_advances', 'financial_party_id'],
    ];

    /** Every column that references a wallet (GAP-037 v17 schema). */
    private const WALLET_REFERENCES = [
        ['treasury_financial_documents', 'source_wallet_id'],
        ['treasury_financial_documents', 'destination_wallet_id'],
        ['treasury_payment_routes', 'expected_destination_wallet_id'],
        ['treasury_payment_route_legs', 'from_wallet_id'],
        ['treasury_payment_route_legs', 'to_wallet_id'],
        ['treasury_ledger_entries', 'wallet_id'],
        ['treasury_reconciliations', 'wallet_id'],
    ];

    /**
     * @return array<string, array<int, mixed>>
     */
    public function partyRules(string $tenantId, bool $partial = false): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'party_type' => [...$required, 'string', Rule::in(array_keys(self::PARTY_TYPES))],
            'name' => [...$required, 'string', 'max:255'],
            'linked_user_id' => ['nullable', 'string', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'linked_account_id' => ['nullable', 'string', Rule::exists('accounts', 'id')->where('tenant_id', $tenantId)],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function walletRules(string $tenantId, bool $partial = false): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'wallet_type' => [...$required, 'string', Rule::in(array_keys(self::WALLET_TYPES))],
            'name' => [...$required, 'string', 'max:255'],
            'custodian_party_id' => ['nullable', 'string', Rule::exists('treasury_financial_parties', 'id')->where('tenant_id', $tenantId)],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createParty(string $tenantId, array $data): TreasuryFinancialParty
    {
        return TreasuryFinancialParty::query()->create([
            'tenant_id' => $tenantId,
            'party_type' => $data['party_type'],
            'name' => $data['name'],
            'linked_user_id' => $data['linked_user_id'] ?? null,
            'linked_account_id' => $data['linked_account_id'] ?? null,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateParty(TreasuryFinancialParty $party, array $data): TreasuryFinancialParty
    {
        $party->fill(array_intersect_key($data, array_flip(['party_type', 'name', 'linked_user_id', 'linked_account_id'])));
        $party->save();

        return $party;
    }

    public function partyInUse(TreasuryFinancialParty $party): bool
    {
        return $this->referenced(self::PARTY_REFERENCES, (string) $party->tenant_id, (string) $party->id);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createWallet(Project $project, array $data): TreasuryWallet
    {
        return TreasuryWallet::query()->create([
            'tenant_id' => (string) $project->tenant_id,
            'project_id' => (string) $project->id,
            'wallet_type' => $data['wallet_type'],
            'name' => $data['name'],
            'custodian_party_id' => $data['custodian_party_id'] ?? null,
        ]);
    }

    /**
     * project_id is deliberately not updatable.
     *
     * @param array<string, mixed> $data
     */
    public function updateWallet(TreasuryWallet $wallet, array $data): TreasuryWallet
    {
        $wallet->fill(array_intersect_key($data, array_flip(['wallet_type', 'name', 'custodian_party_id'])));
        $wallet->save();

        return $wallet;
    }

    public function walletInUse(TreasuryWallet $wallet): bool
    {
        return $this->referenced(self::WALLET_REFERENCES, (string) $wallet->tenant_id, (string) $wallet->id);
    }

    /**
     * @param list<array{0: string, 1: string}> $references
     */
    private function referenced(array $references, string $tenantId, string $id): bool
    {
        foreach ($references as [$table, $column]) {
            if (DB::table($table)->where('tenant_id', $tenantId)->where($column, $id)->exists()) {
                return true;
            }
        }

        return false;
    }
}
