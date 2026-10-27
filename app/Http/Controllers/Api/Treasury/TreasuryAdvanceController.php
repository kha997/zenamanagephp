<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Treasury;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Project;
use App\Models\Treasury\TreasuryAdvance;
use App\Models\Treasury\TreasuryAdvanceSettlement;
use App\Models\Treasury\TreasuryFinancialDocument;
use App\Models\User;
use App\Services\Treasury\TreasuryAdvanceService;
use App\Services\Treasury\TreasuryExpenseService;
use App\Services\Treasury\TreasuryRuleViolation;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * GAP-096 S5 — advances, settlement requests, returns and ageing.
 */
class TreasuryAdvanceController extends BaseApiController
{
    public function __construct(private readonly TreasuryAdvanceService $advances)
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
            ->where('document_type', TreasuryFinancialDocument::TYPE_ADVANCE)
            ->orderByDesc('created_at');
        if (($status = (string) $request->input('status')) !== '') {
            $query->where('status', $status);
        }

        return $this->listSuccessResponse($query->paginate(min((int) $request->input('per_page', 50), 100)), 'Treasury advances retrieved successfully');
    }

    public function ageing(string $project): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.view-project', $model);

        return $this->successResponse($this->advances->ageing($model), 'Treasury advance ageing retrieved successfully');
    }

    public function show(string $project, string $treasuryAdvance): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.view-project', $model);
        $advanceDoc = $this->findAdvance($model, $treasuryAdvance);
        if ($advanceDoc === null) {
            return $this->notFound('Treasury advance not found');
        }

        return $this->successResponse($this->advances->detail($advanceDoc), 'Treasury advance retrieved successfully');
    }

    public function store(Request $request, string $project): JsonResponse
    {
        return $this->act($project, 'treasury.create-expense', fn (Project $p) => $this->validatedDraft($request, fn (array $data) => $this->advances->createDraft($p, $this->user(), $data)), 201);
    }

    public function update(Request $request, string $project, string $treasuryAdvance): JsonResponse
    {
        return $this->withAdvance($project, $treasuryAdvance, 'treasury.create-expense', fn (Project $p, TreasuryFinancialDocument $d) => $this->validatedDraft($request, fn (array $data) => $this->advances->updateDraft($p, $this->user(), $d, $data)));
    }

    public function submit(string $project, string $treasuryAdvance): JsonResponse
    {
        return $this->withAdvance($project, $treasuryAdvance, 'treasury.submit-expense', fn (Project $p, TreasuryFinancialDocument $d) => $this->advances->submit($p, $this->user(), $d));
    }

    public function approve(Request $request, string $project, string $treasuryAdvance): JsonResponse
    {
        return $this->withAdvance($project, $treasuryAdvance, 'treasury.approve-expense', fn (Project $p, TreasuryFinancialDocument $d) => $this->advances->approve($p, $this->user(), $d, $request->input('note')));
    }

    public function reject(Request $request, string $project, string $treasuryAdvance): JsonResponse
    {
        return $this->withAdvance($project, $treasuryAdvance, 'treasury.approve-expense', fn (Project $p, TreasuryFinancialDocument $d) => $this->advances->reject($p, $this->user(), $d, (string) $request->input('note', '')));
    }

    public function copy(string $project, string $treasuryAdvance): JsonResponse
    {
        return $this->withAdvance($project, $treasuryAdvance, 'treasury.create-expense', fn (Project $p, TreasuryFinancialDocument $d) => $this->advances->copyToDraft($p, $this->user(), $d), 201);
    }

    public function requestSettlement(Request $request, string $project, string $treasuryAdvance): JsonResponse
    {
        return $this->withAdvance($project, $treasuryAdvance, 'treasury.submit-expense', function (Project $p, TreasuryFinancialDocument $d) use ($request) {
            $validator = Validator::make($request->all(), [
                'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99', 'decimal:0,2'],
                'note' => ['nullable', 'string', 'max:2000'],
                'allocations' => ['required', 'array', 'min:1', 'max:50'],
                'allocations.*.cost_source_type' => ['required', Rule::in([TreasuryExpenseService::COST_CONTRACT_EXPENSE, TreasuryExpenseService::COST_MATERIAL_LINE])],
                'allocations.*.cost_source_id' => ['required', 'string'],
                'allocations.*.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            ]);
            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }
            $this->advances->requestSettlement($p, $this->user(), $d, $validator->validated());

            return $d;
        }, 201);
    }

    public function withdrawSettlement(string $project, string $treasuryAdvance): JsonResponse
    {
        return $this->withAdvance($project, $treasuryAdvance, 'treasury.submit-expense', function (Project $p, TreasuryFinancialDocument $d) {
            $this->advances->withdrawSettlementRequest($p, $this->user(), $d);

            return $d;
        });
    }

    public function approveSettlement(Request $request, string $project, string $treasuryAdvance): JsonResponse
    {
        return $this->withAdvance($project, $treasuryAdvance, 'treasury.approve-expense', function (Project $p, TreasuryFinancialDocument $d) use ($request) {
            $this->advances->approveSettlementRequest($p, $this->user(), $d, $request->input('note'));

            return $d;
        });
    }

    public function rejectSettlement(Request $request, string $project, string $treasuryAdvance): JsonResponse
    {
        return $this->withAdvance($project, $treasuryAdvance, 'treasury.approve-expense', function (Project $p, TreasuryFinancialDocument $d) use ($request) {
            $this->advances->rejectSettlementRequest($p, $this->user(), $d, (string) $request->input('note', ''));

            return $d;
        });
    }

    public function storeReturn(Request $request, string $project, string $treasuryAdvance): JsonResponse
    {
        return $this->withAdvance($project, $treasuryAdvance, 'treasury.reverse', function (Project $p, TreasuryFinancialDocument $d) use ($request) {
            $validator = Validator::make($request->all(), [
                'destination_wallet_id' => ['required', 'string'],
                'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99', 'decimal:0,2'],
                'transaction_date' => ['required', 'date_format:Y-m-d'],
                'reference' => ['nullable', 'string', 'max:100'],
                'description' => ['nullable', 'string', 'max:2000'],
            ]);
            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }
            $this->advances->recordReturn($p, $this->user(), $d, $validator->validated());

            return $d;
        }, 201);
    }

    public function removeSettlement(Request $request, string $project, string $treasuryAdvanceSettlement): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.reverse', $model);
        /** @var TreasuryAdvanceSettlement|null $settlement */
        $settlement = TreasuryAdvanceSettlement::query()
            ->whereKey($treasuryAdvanceSettlement)
            ->whereIn('advance_id', TreasuryAdvance::query()->where('project_id', (string) $model->id)->select('id'))
            ->first();
        if ($settlement === null) {
            return $this->notFound('Treasury advance settlement not found');
        }
        $validator = Validator::make($request->all(), ['reason' => ['required', 'string', 'max:2000']]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $this->advances->removeSettlement($model, $this->user(), $settlement, (string) $validator->validated()['reason']);
        } catch (TreasuryRuleViolation $e) {
            return $this->validationError([$e->field ?? 'settlement' => [$e->getMessage()]]);
        }
        /** @var TreasuryAdvance $advance */
        $advance = TreasuryAdvance::query()->whereKey($settlement->advance_id)->firstOrFail();
        /** @var TreasuryFinancialDocument $advanceDoc */
        $advanceDoc = TreasuryFinancialDocument::query()->whereKey($advance->originating_financial_document_id)->firstOrFail();

        return $this->successResponse($this->advances->detail($advanceDoc), 'Treasury advance saved');
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
            return $this->validationError([$e->field ?? 'advance' => [$e->getMessage()]]);
        }
        if ($result instanceof JsonResponse) {
            return $result;
        }
        /** @var TreasuryFinancialDocument $fresh */
        $fresh = $result->fresh();

        return $this->successResponse($this->advances->detail($fresh), 'Treasury advance saved', $status);
    }

    /**
     * @param Closure(Project, TreasuryFinancialDocument): (TreasuryFinancialDocument|JsonResponse) $action
     */
    private function withAdvance(string $project, string $id, string $ability, Closure $action, int $status = 200): JsonResponse
    {
        return $this->act($project, $ability, function (Project $p) use ($id, $action) {
            $advanceDoc = $this->findAdvance($p, $id);
            if ($advanceDoc === null) {
                return $this->notFound('Treasury advance not found');
            }

            return $action($p, $advanceDoc);
        }, $status);
    }

    /**
     * @param Closure(array<string, mixed>): TreasuryFinancialDocument $action
     */
    private function validatedDraft(Request $request, Closure $action): TreasuryFinancialDocument|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'source_wallet_id' => ['required', 'string'],
            'destination_party_id' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99', 'decimal:0,2'],
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        return $action($validator->validated());
    }

    private function findAdvance(Project $project, string $id): ?TreasuryFinancialDocument
    {
        return TreasuryFinancialDocument::query()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->where('document_type', TreasuryFinancialDocument::TYPE_ADVANCE)
            ->whereKey($id)
            ->first();
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
