<?php declare(strict_types=1);

namespace App\Http\Controllers\Web\Treasury;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Models\UserRoleProject;
use App\Models\Treasury\TreasuryFinancialDocument;
use App\Services\Treasury\TreasuryBalanceService;
use App\Services\Treasury\TreasuryDuplicateSuspected;
use App\Services\Treasury\TreasuryPostingService;
use App\Services\Treasury\TreasuryRuleViolation;
use App\Services\Treasury\TreasurySetupService;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * GAP-063 S1 — operator pages: treasury project list, financial parties,
 * and a project's wallets. Business rules live in TreasurySetupService
 * (shared with the API); every action is checked with the treasury.* gates.
 */
class TreasuryPageController extends Controller
{
    public function __construct(
        private readonly TreasurySetupService $setup,
        private readonly TreasuryPostingService $posting,
        private readonly TreasuryBalanceService $balances,
    ) {
    }

    public function index(): View
    {
        $user = $this->user();
        $query = Project::query()->where('tenant_id', (string) $user->tenant_id)->orderBy('name');

        if (!$user->hasPermission('treasury.all_projects')) {
            $memberProjectIds = UserRoleProject::query()
                ->where('user_id', (string) $user->id)
                ->whereNull('deleted_at')
                ->pluck('project_id');
            $query->whereIn('id', $memberProjectIds);
        }

        return view('treasury.index', ['projects' => $query->paginate(30)]);
    }

    public function parties(): View
    {
        $this->authorize('treasury.manage-parties');

        return view('treasury.parties', [
            'parties' => TreasuryFinancialParty::query()
                ->where('tenant_id', (string) $this->user()->tenant_id)
                ->orderBy('name')
                ->paginate(30),
            'partyTypes' => TreasurySetupService::PARTY_TYPES,
        ]);
    }

    public function storeParty(Request $request): RedirectResponse
    {
        $this->authorize('treasury.manage-parties');
        $data = $request->validate($this->setup->partyRules((string) $this->user()->tenant_id));
        $this->setup->createParty((string) $this->user()->tenant_id, $data);

        return redirect()->route('operator.treasury.parties.index')->with('success', 'Đã thêm đối tác tài chính.');
    }

    public function editParty(string $party): View
    {
        $this->authorize('treasury.manage-parties');

        return view('treasury.party-edit', [
            'party' => $this->findParty($party),
            'partyTypes' => TreasurySetupService::PARTY_TYPES,
        ]);
    }

    public function updateParty(Request $request, string $party): RedirectResponse
    {
        $this->authorize('treasury.manage-parties');
        $model = $this->findParty($party);
        $data = $request->validate($this->setup->partyRules((string) $this->user()->tenant_id));
        $this->setup->updateParty($model, $data);

        return redirect()->route('operator.treasury.parties.index')->with('success', 'Đã cập nhật đối tác.');
    }

    public function destroyParty(string $party): RedirectResponse
    {
        $this->authorize('treasury.manage-parties');
        $model = $this->findParty($party);

        if ($this->setup->partyInUse($model)) {
            return back()->with('error', 'Đối tác đang được dùng (ví hoặc giao dịch) nên không thể xoá.');
        }

        $model->delete();

        return redirect()->route('operator.treasury.parties.index')->with('success', 'Đã xoá đối tác.');
    }

    public function project(string $project): View
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.view-project', $model);
        $user = $this->user();

        return view('treasury.project', [
            'project' => $model,
            'wallets' => TreasuryWallet::query()
                ->where('tenant_id', (string) $model->tenant_id)
                ->where('project_id', (string) $model->id)
                ->with('custodianParty')
                ->orderBy('name')
                ->get(),
            'canManageWallets' => Gate::forUser($user)->allows('treasury.manage-wallets', $model),
            // GAP-064 S2: balances, register and the actions this user may take.
            'summary' => $this->balances->projectSummary($model),
            'documents' => TreasuryFinancialDocument::query()
                ->where('tenant_id', (string) $model->tenant_id)
                ->where('project_id', (string) $model->id)
                ->with(['sourceWallet', 'destinationWallet', 'sourceParty', 'destinationParty'])
                ->orderByDesc('transaction_date')
                ->orderByDesc('created_at')
                ->limit(100)
                ->get(),
            'can' => [
                'declare' => Gate::forUser($user)->allows('treasury.declare-funding', $model),
                'transfer' => Gate::forUser($user)->allows('treasury.create-transfer', $model),
                'adjust' => Gate::forUser($user)->allows('treasury.adjust', $model),
                'reverse' => Gate::forUser($user)->allows('treasury.reverse', $model),
            ],
            'transferSources' => TreasuryWallet::query()
                ->where('tenant_id', (string) $model->tenant_id)
                ->where('project_id', (string) $model->id)
                ->orderBy('name')
                ->get()
                ->filter(fn ($wallet): bool => Gate::forUser($user)->allows('treasury.transfer-from-wallet', $wallet))
                ->values(),
            'walletTypes' => TreasurySetupService::WALLET_TYPES,
            'parties' => TreasuryFinancialParty::query()
                ->where('tenant_id', (string) $model->tenant_id)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeFunding(Request $request, string $project): RedirectResponse
    {
        return $this->post($request, $project, 'treasury.declare-funding', [
            'document_type' => ['required', Rule::in([TreasuryFinancialDocument::TYPE_FUNDING, TreasuryFinancialDocument::TYPE_OWNER_CONTRIBUTION])],
            'source_party_id' => ['required', 'string'],
            'destination_wallet_id' => ['required', 'string'],
        ], fn (Project $p, array $data) => $this->posting->declareFunding($p, $this->user(), $data, $request->boolean('confirm_duplicate')), 'Đã ghi nhận tiền nhận.');
    }

    public function storeTransfer(Request $request, string $project): RedirectResponse
    {
        return $this->post($request, $project, 'treasury.create-transfer', [
            'source_wallet_id' => ['required', 'string'],
            'destination_wallet_id' => ['required', 'string'],
        ], fn (Project $p, array $data) => $this->posting->transfer($p, $this->user(), $data), 'Đã chuyển tiền giữa hai ví.');
    }

    public function storeAdjustment(Request $request, string $project): RedirectResponse
    {
        return $this->post($request, $project, 'treasury.adjust', [
            'wallet_id' => ['required', 'string'],
            'direction' => ['required', Rule::in(['increase', 'decrease'])],
            'description' => ['required', 'string', 'max:2000'],
        ], fn (Project $p, array $data) => $this->posting->adjust($p, $this->user(), $data), 'Đã ghi điều chỉnh.');
    }

    public function reverseDocument(Request $request, string $project, string $treasuryDocument): RedirectResponse
    {
        $model = $this->findProject($project);
        $doc = $this->findDocument($model, $treasuryDocument);

        return $this->post($request, $project, 'treasury.reverse', [
            'description' => ['required', 'string', 'max:2000'],
        ], fn (Project $p, array $data) => $this->posting->reverse($p, $this->user(), $doc, $data), 'Đã đảo bút toán.', withAmount: false);
    }

    public function linkReplacement(Request $request, string $project, string $treasuryDocument): RedirectResponse
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.reverse', $model);
        $reversal = $this->findDocument($model, $treasuryDocument);
        $data = $request->validate(['replacement_document_id' => ['required', 'string']]);
        $replacement = $this->findDocument($model, (string) $data['replacement_document_id']);

        try {
            $this->posting->linkReplacement($model, $this->user(), $reversal, $replacement);
        } catch (TreasuryRuleViolation $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('operator.treasury.projects.show', $model->id)->with('success', 'Đã gắn chứng từ thay thế.');
    }

    /**
     * @param array<string, array<int, mixed>> $rules
     * @param Closure(Project, array<string, mixed>): TreasuryFinancialDocument $action
     */
    private function post(Request $request, string $project, string $ability, array $rules, Closure $action, string $success, bool $withAmount = true): RedirectResponse
    {
        $model = $this->findProject($project);
        $this->authorize($ability, $model);

        $common = [
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
        if ($withAmount) {
            $common['amount'] = ['required', 'numeric', 'gt:0', 'max:9999999999999.99', 'decimal:0,2'];
        }
        $data = $request->validate($rules + $common);

        try {
            $action($model, $data);
        } catch (TreasuryDuplicateSuspected $e) {
            return back()->withInput()->with('treasury_duplicate', $e->getMessage());
        } catch (TreasuryRuleViolation $e) {
            return back()->withInput()->withErrors([$e->field ?? 'document' => $e->getMessage()]);
        }

        return redirect()->route('operator.treasury.projects.show', $model->id)->with('success', $success);
    }

    private function findDocument(Project $project, string $id): TreasuryFinancialDocument
    {
        /** @var TreasuryFinancialDocument $found */
        $found = TreasuryFinancialDocument::query()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->whereKey($id)
            ->firstOrFail();

        return $found;
    }

    public function storeWallet(Request $request, string $project): RedirectResponse
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.manage-wallets', $model);
        $data = $request->validate($this->setup->walletRules((string) $model->tenant_id));
        $this->setup->createWallet($model, $data);

        return redirect()->route('operator.treasury.projects.show', $model->id)->with('success', 'Đã thêm ví.');
    }

    public function editWallet(string $project, string $wallet): View
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.manage-wallets', $model);

        return view('treasury.wallet-edit', [
            'project' => $model,
            'wallet' => $this->findWallet($model, $wallet),
            'walletTypes' => TreasurySetupService::WALLET_TYPES,
            'parties' => TreasuryFinancialParty::query()
                ->where('tenant_id', (string) $model->tenant_id)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function updateWallet(Request $request, string $project, string $wallet): RedirectResponse
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.manage-wallets', $model);
        $walletModel = $this->findWallet($model, $wallet);
        $data = $request->validate($this->setup->walletRules((string) $model->tenant_id));
        $this->setup->updateWallet($walletModel, $data);

        return redirect()->route('operator.treasury.projects.show', $model->id)->with('success', 'Đã cập nhật ví.');
    }

    public function destroyWallet(string $project, string $wallet): RedirectResponse
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.manage-wallets', $model);
        $walletModel = $this->findWallet($model, $wallet);

        if ($this->setup->walletInUse($walletModel)) {
            return back()->with('error', 'Ví đã có giao dịch nên không thể xoá.');
        }

        $walletModel->delete();

        return redirect()->route('operator.treasury.projects.show', $model->id)->with('success', 'Đã xoá ví.');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    private function findParty(string $id): TreasuryFinancialParty
    {
        /** @var TreasuryFinancialParty $party */
        $party = TreasuryFinancialParty::query()
            ->where('tenant_id', (string) $this->user()->tenant_id)
            ->whereKey($id)
            ->firstOrFail();

        return $party;
    }

    private function findProject(string $id): Project
    {
        /** @var Project $project */
        $project = Project::query()
            ->where('tenant_id', (string) $this->user()->tenant_id)
            ->whereKey($id)
            ->firstOrFail();

        return $project;
    }

    private function findWallet(Project $project, string $id): TreasuryWallet
    {
        /** @var TreasuryWallet $wallet */
        $wallet = TreasuryWallet::query()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->whereKey($id)
            ->firstOrFail();

        return $wallet;
    }
}
