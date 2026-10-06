<?php declare(strict_types=1);

namespace App\Http\Controllers\Web\Treasury;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Models\UserRoleProject;
use App\Services\Treasury\TreasurySetupService;
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
    public function __construct(private readonly TreasurySetupService $setup)
    {
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
            'walletTypes' => TreasurySetupService::WALLET_TYPES,
            'parties' => TreasuryFinancialParty::query()
                ->where('tenant_id', (string) $model->tenant_id)
                ->orderBy('name')
                ->get(),
        ]);
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
