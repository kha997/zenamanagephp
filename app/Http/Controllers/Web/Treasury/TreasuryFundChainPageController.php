<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Treasury;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Treasury\TreasuryFundChain;
use App\Models\User;
use App\Services\Treasury\TreasuryFundChainService;
use App\Services\Treasury\TreasuryRuleViolation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * GAP-105 F-1 — fund chain pages: list and create chains, show a chain's trace, add / remove members. Reading needs
 * the Treasury project view; editing needs treasury.manage-routes. Rules live in TreasuryFundChainService.
 */
class TreasuryFundChainPageController extends Controller
{
    public function __construct(private readonly TreasuryFundChainService $chains)
    {
    }

    public function index(string $project): View
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.view-project', $model);

        return view('treasury.fund-chains', [
            'project' => $model,
            'chains' => $this->chains->chains($model),
            'canManage' => Gate::forUser($this->user())->allows('treasury.manage-routes', $model),
        ]);
    }

    public function store(Request $request, string $project): RedirectResponse
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.manage-routes', $model);
        $data = $request->validate([
            'chain_reference' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $chain = $this->chains->create($model, $this->user(), $data);
        } catch (TreasuryRuleViolation $e) {
            return back()->withInput()->withErrors([$e->field ?? 'chain_reference' => $e->getMessage()]);
        }

        return redirect()->route('operator.treasury.projects.fund-chains.show', [$model->id, $chain->id])->with('success', 'Đã tạo chuỗi quỹ.');
    }

    public function show(string $project, string $fundChain): View
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.view-project', $model);
        $chain = $this->findChain($model, $fundChain);
        $canManage = Gate::forUser($this->user())->allows('treasury.manage-routes', $model);

        return view('treasury.fund-chain', [
            'project' => $model,
            'chain' => $chain,
            'members' => $this->chains->trace($model, $chain),
            'candidates' => $canManage ? $this->chains->candidates($model) : ['documents' => [], 'routes' => []],
            'canManage' => $canManage,
        ]);
    }

    public function storeMember(Request $request, string $project, string $fundChain): RedirectResponse
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.manage-routes', $model);
        $chain = $this->findChain($model, $fundChain);
        $data = $request->validate([
            'member_financial_document_id' => ['nullable', 'string'],
            'member_payment_route_id' => ['nullable', 'string'],
        ]);

        try {
            $this->chains->addMember($model, $this->user(), $chain, $data['member_financial_document_id'] ?? null, $data['member_payment_route_id'] ?? null);
        } catch (TreasuryRuleViolation $e) {
            return back()->withInput()->withErrors([$e->field ?? 'member' => $e->getMessage()]);
        }

        return redirect()->route('operator.treasury.projects.fund-chains.show', [$model->id, $chain->id])->with('success', 'Đã thêm vào chuỗi.');
    }

    public function destroyMember(string $project, string $fundChain, string $member): RedirectResponse
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.manage-routes', $model);
        $chain = $this->findChain($model, $fundChain);

        try {
            $this->chains->removeMember($model, $this->user(), $chain, $member);
        } catch (TreasuryRuleViolation $e) {
            return back()->withErrors([$e->field ?? 'member' => $e->getMessage()]);
        }

        return redirect()->route('operator.treasury.projects.fund-chains.show', [$model->id, $chain->id])->with('success', 'Đã bỏ khỏi chuỗi.');
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

    private function findChain(Project $project, string $id): TreasuryFundChain
    {
        $chain = $this->chains->projectChain($project, $id);
        abort_if($chain === null, 404);

        return $chain;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
