<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Treasury;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Treasury\TreasuryBankStatementImport;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryBankImportService;
use App\Services\Treasury\TreasuryPeriodLockService;
use App\Services\Treasury\TreasuryRuleViolation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * GAP-107 — L-1 / U-1: lock a project's Treasury period through a date, or move it back / clear it with a reason
 * (rbac:treasury.manage_period_lock). I-1: bank statement import of a bank wallet (read: Treasury project policy;
 * write: treasury.reconcile policy).
 */
class TreasuryPeriodPageController extends Controller
{
    public function __construct(
        private readonly TreasuryPeriodLockService $locks,
        private readonly TreasuryBankImportService $bankImports,
    ) {
    }

    public function lock(Request $request, string $project): RedirectResponse
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.view-project', $model);
        $data = $request->validate([
            'locked_through' => ['required', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->locks->lock($model, $this->user(), (string) $data['locked_through'], $data['reason'] ?? null);
        } catch (TreasuryRuleViolation $e) {
            return back()->withInput()->withErrors([$e->field ?? 'locked_through' => $e->getMessage()]);
        }

        return redirect()->route('operator.treasury.projects.show', $model->id)->with('success', 'Đã khoá kỳ.');
    }

    public function unlock(Request $request, string $project): RedirectResponse
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.view-project', $model);
        $data = $request->validate([
            'locked_through' => ['nullable', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->locks->unlock($model, $this->user(), $data['locked_through'] ?? null, (string) $data['reason']);
        } catch (TreasuryRuleViolation $e) {
            return back()->withInput()->withErrors([$e->field ?? 'locked_through' => $e->getMessage()]);
        }

        return redirect()->route('operator.treasury.projects.show', $model->id)->with('success', 'Đã mở khoá kỳ.');
    }

    /** GAP-107 I-1: upload form and the wallet's previous imports. */
    public function bankImport(string $project, string $wallet): View
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.view-project', $model);
        $walletModel = $this->findWallet($model, $wallet);
        abort_unless($this->bankImports->isBankWallet($walletModel), 404);

        return view('treasury.bank-import', [
            'project' => $model,
            'wallet' => $walletModel,
            'import' => null,
            'imports' => $this->bankImports->imports($walletModel),
            'columnMap' => $this->bankImports->lastColumnMap($walletModel),
            'canReconcile' => Gate::forUser($this->user())->allows('treasury.reconcile', $model),
        ]);
    }

    public function storeBankImport(Request $request, string $project, string $wallet): RedirectResponse
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.reconcile', $model);
        $walletModel = $this->findWallet($model, $wallet);
        $data = $request->validate([
            'file' => ['required', 'file', 'max:' . TreasuryBankImportService::MAX_KILOBYTES],
            'statement_reference' => ['required', 'string', 'max:100'],
            'has_header' => ['nullable', 'boolean'],
            'map' => ['required', 'array'],
            'map.*' => ['nullable', 'integer', 'min:0', 'max:50'],
        ]);

        try {
            /** @var \Illuminate\Http\UploadedFile $file */
            $file = $request->file('file');
            $import = $this->bankImports->import($model, $this->user(), $walletModel, $file, (array) $data['map'], (string) $data['statement_reference'], $request->boolean('has_header', true));
        } catch (TreasuryRuleViolation $e) {
            return back()->withInput()->withErrors([$e->field ?? 'file' => $e->getMessage()]);
        }

        return redirect()->route('operator.treasury.projects.bank-imports.show', [$model->id, $import->id])->with('success', 'Đã import ' . $import->line_count . ' dòng sao kê.');
    }

    public function showBankImport(string $project, string $bankImport): View
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.view-project', $model);
        $import = $this->findImport($model, $bankImport);
        $walletModel = $this->findWallet($model, (string) $import->wallet_id);
        $canReconcile = Gate::forUser($this->user())->allows('treasury.reconcile', $model);

        return view('treasury.bank-import', [
            'project' => $model,
            'wallet' => $walletModel,
            'import' => $import,
            'lines' => $this->bankImports->lines($import),
            'suggestions' => $canReconcile ? $this->bankImports->suggestions($model, $import) : [],
            'canReconcile' => $canReconcile,
        ]);
    }

    public function confirmBankImport(Request $request, string $project, string $bankImport): RedirectResponse
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.reconcile', $model);
        $import = $this->findImport($model, $bankImport);
        $data = $request->validate([
            'pairs' => ['required', 'array'],
            'pairs.*' => ['nullable', 'string'],
        ]);

        try {
            $this->bankImports->confirm($model, $this->user(), $import, array_map('strval', array_filter((array) $data['pairs'])));
        } catch (TreasuryRuleViolation $e) {
            return back()->withErrors([$e->field ?? 'pairs' => $e->getMessage()]);
        }

        return redirect()->route('operator.treasury.projects.bank-imports.show', [$model->id, $import->id])->with('success', 'Đã đối soát các dòng đã chọn.');
    }

    public function ignoreBankLine(Request $request, string $project, string $bankImport, string $line): RedirectResponse
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.reconcile', $model);
        $import = $this->findImport($model, $bankImport);
        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);

        try {
            $this->bankImports->ignore($model, $this->user(), $import, $line, (string) $data['note']);
        } catch (TreasuryRuleViolation $e) {
            return back()->withErrors([$e->field ?? 'note' => $e->getMessage()]);
        }

        return redirect()->route('operator.treasury.projects.bank-imports.show', [$model->id, $import->id])->with('success', 'Đã bỏ qua dòng sao kê.');
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

    private function findImport(Project $project, string $id): TreasuryBankStatementImport
    {
        $import = $this->bankImports->importOf($project, $id);
        abort_if($import === null, 404);

        return $import;
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

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
