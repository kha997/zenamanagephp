<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\Treasury;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Project;
use App\Models\Treasury\TreasuryFinancialDocument;
use App\Models\User;
use App\Services\Treasury\TreasuryExpenseService;
use App\Services\Treasury\TreasuryRuleViolation;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * GAP-066 S3 — project treasury expenses and their approval queue.
 */
class TreasuryExpenseController extends BaseApiController
{
    public function __construct(private readonly TreasuryExpenseService $expenses)
    {
    }

    public function index(Request $request, string $project): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.view-project', $model);

        $query = TreasuryFinancialDocument::query()
            ->where('tenant_id', (string) $model->tenant_id)
            ->where('project_id', (string) $model->id)
            ->where('document_type', TreasuryFinancialDocument::TYPE_EXPENSE)
            ->orderByDesc('created_at');
        if (($status = (string) $request->input('status')) !== '') {
            $query->where('status', $status);
        }

        return $this->listSuccessResponse($query->paginate(min((int) $request->input('per_page', 50), 100)), 'Treasury expenses retrieved successfully');
    }

    public function payables(string $project): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.view-project', $model);

        return $this->successResponse($this->expenses->payables($model), 'Treasury payables retrieved successfully');
    }

    public function store(Request $request, string $project): JsonResponse
    {
        return $this->act($project, 'treasury.create-expense', fn (Project $p) => $this->validated($request, fn (array $data) => $this->expenses->createDraft($p, $this->user(), $data)), 201);
    }

    public function update(Request $request, string $project, string $treasuryExpense): JsonResponse
    {
        return $this->act($project, 'treasury.create-expense', fn (Project $p) => $this->withExpense($p, $treasuryExpense,
            fn (TreasuryFinancialDocument $e) => $this->validated($request, fn (array $data) => $this->expenses->updateDraft($p, $this->user(), $e, $data))));
    }

    public function submit(string $project, string $treasuryExpense): JsonResponse
    {
        return $this->act($project, 'treasury.submit-expense', fn (Project $p) => $this->withExpense($p, $treasuryExpense,
            fn (TreasuryFinancialDocument $e) => $this->expenses->submit($p, $this->user(), $e)));
    }

    public function approve(Request $request, string $project, string $treasuryExpense): JsonResponse
    {
        return $this->act($project, 'treasury.approve-expense', fn (Project $p) => $this->withExpense($p, $treasuryExpense,
            fn (TreasuryFinancialDocument $e) => $this->expenses->approve($p, $this->user(), $e, $request->input('note'))));
    }

    public function reject(Request $request, string $project, string $treasuryExpense): JsonResponse
    {
        return $this->act($project, 'treasury.approve-expense', fn (Project $p) => $this->withExpense($p, $treasuryExpense,
            fn (TreasuryFinancialDocument $e) => $this->expenses->reject($p, $this->user(), $e, (string) $request->input('note', ''))));
    }

    public function copy(string $project, string $treasuryExpense): JsonResponse
    {
        return $this->act($project, 'treasury.create-expense', fn (Project $p) => $this->withExpense($p, $treasuryExpense,
            fn (TreasuryFinancialDocument $e) => $this->expenses->copyToDraft($p, $this->user(), $e)), 201);
    }

    /**
     * @param Closure(Project): (TreasuryFinancialDocument|JsonResponse) $action
     */
    private function act(string $project, string $ability, Closure $action, int $status = 200): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize($ability, $model);

        try {
            $result = $action($model);
        } catch (TreasuryRuleViolation $e) {
            return $this->validationError([$e->field ?? 'expense' => [$e->getMessage()]]);
        }

        return $result instanceof JsonResponse ? $result : $this->successResponse($result->fresh(), 'Treasury expense saved', $status);
    }

    /**
     * @param Closure(TreasuryFinancialDocument): (TreasuryFinancialDocument|JsonResponse) $action
     */
    private function withExpense(Project $project, string $id, Closure $action): TreasuryFinancialDocument|JsonResponse
    {
        $expense = TreasuryFinancialDocument::query()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->where('document_type', TreasuryFinancialDocument::TYPE_EXPENSE)
            ->whereKey($id)
            ->first();
        if ($expense === null) {
            return $this->notFound('Treasury expense not found');
        }

        return $action($expense);
    }

    /**
     * @param Closure(array<string, mixed>): TreasuryFinancialDocument $action
     */
    private function validated(Request $request, Closure $action): TreasuryFinancialDocument|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'source_wallet_id' => ['required', 'string'],
            'destination_party_id' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99', 'decimal:0,2'],
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'allocations' => ['nullable', 'array', 'max:50'],
            'allocations.*.cost_source_type' => ['required', Rule::in([TreasuryExpenseService::COST_CONTRACT_EXPENSE, TreasuryExpenseService::COST_MATERIAL_LINE])],
            'allocations.*.cost_source_id' => ['required', 'string'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'new_contract_expense' => ['nullable', 'array'],
            'new_contract_expense.contract_id' => ['required_with:new_contract_expense', 'string'],
            'new_contract_expense.category' => ['required_with:new_contract_expense', 'string'],
            'new_contract_expense.amount' => ['required_with:new_contract_expense', 'numeric', 'gt:0', 'decimal:0,2'],
            'new_contract_expense.description' => ['nullable', 'string', 'max:1000'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        return $action($validator->validated());
    }

    private function findProject(string $id): ?Project
    {
        return Project::query()
            ->where('tenant_id', (string) data_get(Auth::user(), 'tenant_id'))
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
