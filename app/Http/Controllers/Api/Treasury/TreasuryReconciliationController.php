<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\Treasury;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Project;
use App\Models\Treasury\TreasuryReconciliationEntry;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryReconciliationService;
use App\Services\Treasury\TreasuryRuleViolation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * GAP-067 S4a — wallet reconciliation: per-wallet view, reconcile, history, undo.
 */
class TreasuryReconciliationController extends BaseApiController
{
    public function __construct(private readonly TreasuryReconciliationService $reconciliation)
    {
    }

    public function wallet(string $project, string $wallet): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.view-project', $model);
        $walletModel = $this->findWallet($model, $wallet);
        if ($walletModel === null) {
            return $this->notFound('Treasury wallet not found');
        }

        return $this->successResponse([
            'wallet_id' => (string) $walletModel->id,
            'balances' => $this->reconciliation->walletSummary($walletModel),
            'unreconciled_entries' => $this->reconciliation->unreconciledEntries($walletModel),
        ], 'Treasury wallet reconciliation retrieved successfully');
    }

    public function store(Request $request, string $project, string $wallet): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.reconcile', $model);
        $walletModel = $this->findWallet($model, $wallet);
        if ($walletModel === null) {
            return $this->notFound('Treasury wallet not found');
        }

        $validator = Validator::make($request->all(), [
            'reconciliation_type' => ['required', Rule::in(TreasuryReconciliationService::TYPES)],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'reconciled_at' => ['required', 'date_format:Y-m-d'],
            'ledger_entry_ids' => ['required', 'array', 'min:1', 'max:500'],
            'ledger_entry_ids.*' => ['required', 'string', 'max:26'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $rec = $this->reconciliation->reconcile($model, $this->user(), $walletModel, $validator->validated());
        } catch (TreasuryRuleViolation $e) {
            return $this->validationError([$e->field ?? 'reconciliation' => [$e->getMessage()]]);
        }

        return $this->successResponse($this->historyItem($model, (string) $rec->id), 'Treasury reconciliation recorded', 201);
    }

    public function index(Request $request, string $project): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.view-project', $model);
        $walletModel = null;
        if (($walletId = (string) $request->input('wallet_id')) !== '') {
            $walletModel = $this->findWallet($model, $walletId);
            if ($walletModel === null) {
                return $this->notFound('Treasury wallet not found');
            }
        }

        return $this->successResponse($this->reconciliation->history($model, $walletModel), 'Treasury reconciliations retrieved successfully');
    }

    public function undo(Request $request, string $project, string $treasuryReconciliation): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.reconcile', $model);
        $rec = $this->reconciliation->projectReconciliation($model, $treasuryReconciliation);
        if ($rec === null) {
            return $this->notFound('Treasury reconciliation not found');
        }
        $reason = $this->reason($request);
        if ($reason instanceof JsonResponse) {
            return $reason;
        }

        try {
            $this->reconciliation->undoReconciliation($model, $this->user(), $rec, $reason);
        } catch (TreasuryRuleViolation $e) {
            return $this->validationError([$e->field ?? 'reconciliation' => [$e->getMessage()]]);
        }

        return $this->successResponse($this->historyItem($model, (string) $rec->id), 'Treasury reconciliation undone');
    }

    public function undoEntry(Request $request, string $project, string $treasuryReconciliationEntry): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.reconcile', $model);
        /** @var TreasuryReconciliationEntry|null $line */
        $line = TreasuryReconciliationEntry::query()
            ->where('tenant_id', (string) $model->tenant_id)
            ->whereKey($treasuryReconciliationEntry)
            ->first();
        if ($line === null || $this->reconciliation->projectReconciliation($model, (string) $line->reconciliation_id) === null) {
            return $this->notFound('Treasury reconciliation line not found');
        }
        $reason = $this->reason($request);
        if ($reason instanceof JsonResponse) {
            return $reason;
        }

        try {
            $this->reconciliation->undoEntry($model, $this->user(), $line, $reason);
        } catch (TreasuryRuleViolation $e) {
            return $this->validationError([$e->field ?? 'reconciliation_entry' => [$e->getMessage()]]);
        }

        return $this->successResponse($this->historyItem($model, (string) $line->reconciliation_id), 'Treasury reconciliation line undone');
    }

    private function reason(Request $request): string|JsonResponse
    {
        $validator = Validator::make($request->all(), ['reason' => ['required', 'string', 'max:2000']]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        return (string) $request->input('reason');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function historyItem(Project $project, string $reconciliationId): ?array
    {
        foreach ($this->reconciliation->history($project) as $item) {
            if ($item['id'] === $reconciliationId) {
                return $item;
            }
        }

        return null;
    }

    private function findProject(string $id): ?Project
    {
        return Project::query()
            ->where('tenant_id', (string) data_get(Auth::user(), 'tenant_id'))
            ->whereKey($id)
            ->first();
    }

    private function findWallet(Project $project, string $id): ?TreasuryWallet
    {
        return TreasuryWallet::query()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->whereKey($id)
            ->first();
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
