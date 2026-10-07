<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\Treasury;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Project;
use App\Models\Treasury\TreasuryFinancialDocument;
use App\Services\Treasury\TreasuryBalanceService;
use App\Services\Treasury\TreasuryDuplicateSuspected;
use App\Services\Treasury\TreasuryPostingService;
use App\Services\Treasury\TreasuryRuleViolation;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * GAP-064 S2 — project treasury documents, balances and posting actions.
 */
class TreasuryDocumentController extends BaseApiController
{
    public function __construct(
        private readonly TreasuryPostingService $posting,
        private readonly TreasuryBalanceService $balances,
    ) {
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
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at');
        foreach (['document_type', 'status'] as $filter) {
            if (($value = (string) $request->input($filter)) !== '') {
                $query->where($filter, $value);
            }
        }
        if (($from = (string) $request->input('from')) !== '') {
            $query->whereDate('transaction_date', '>=', $from);
        }
        if (($to = (string) $request->input('to')) !== '') {
            $query->whereDate('transaction_date', '<=', $to);
        }

        return $this->listSuccessResponse($query->paginate(min((int) $request->input('per_page', 50), 100)), 'Treasury documents retrieved successfully');
    }

    public function show(string $project, string $treasuryDocument): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.view-project', $model);
        $doc = $this->findDocument($model, $treasuryDocument);
        if ($doc === null) {
            return $this->notFound('Treasury document not found');
        }

        return $this->successResponse($doc, 'Treasury document retrieved successfully');
    }

    public function balances(string $project): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.view-project', $model);

        return $this->successResponse($this->balances->projectSummary($model), 'Treasury balances retrieved successfully');
    }

    public function funding(Request $request, string $project): JsonResponse
    {
        return $this->mutate($project, 'treasury.declare-funding', $request, [
            'document_type' => ['required', Rule::in([TreasuryFinancialDocument::TYPE_FUNDING, TreasuryFinancialDocument::TYPE_OWNER_CONTRIBUTION])],
            'source_party_id' => ['required', 'string'],
            'destination_wallet_id' => ['required', 'string'],
        ], fn (Project $p, array $data) => $this->posting->declareFunding($p, $this->user(), $data, $request->boolean('confirm_duplicate')));
    }

    public function transfer(Request $request, string $project): JsonResponse
    {
        return $this->mutate($project, 'treasury.create-transfer', $request, [
            'source_wallet_id' => ['required', 'string'],
            'destination_wallet_id' => ['required', 'string'],
        ], fn (Project $p, array $data) => $this->posting->transfer($p, $this->user(), $data));
    }

    public function adjust(Request $request, string $project): JsonResponse
    {
        return $this->mutate($project, 'treasury.adjust', $request, [
            'wallet_id' => ['required', 'string'],
            'direction' => ['required', Rule::in(['increase', 'decrease'])],
            'description' => ['required', 'string', 'max:2000'],
        ], fn (Project $p, array $data) => $this->posting->adjust($p, $this->user(), $data));
    }

    public function reverse(Request $request, string $project, string $treasuryDocument): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $doc = $this->findDocument($model, $treasuryDocument);
        if ($doc === null) {
            return $this->notFound('Treasury document not found');
        }

        return $this->mutate($project, 'treasury.reverse', $request, [
            'amount' => ['prohibited'],
            'description' => ['required', 'string', 'max:2000'],
        ], fn (Project $p, array $data) => $this->posting->reverse($p, $this->user(), $doc, $data), withAmount: false);
    }

    public function replacement(Request $request, string $project, string $treasuryDocument): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.reverse', $model);
        $reversal = $this->findDocument($model, $treasuryDocument);
        if ($reversal === null) {
            return $this->notFound('Treasury document not found');
        }

        $validator = Validator::make($request->all(), ['replacement_document_id' => ['required', 'string']]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }
        $replacement = $this->findDocument($model, (string) $request->input('replacement_document_id'));
        if ($replacement === null) {
            return $this->validationError(['replacement_document_id' => ['Chứng từ thay thế không thuộc dự án này.']]);
        }

        try {
            return $this->successResponse($this->posting->linkReplacement($model, $this->user(), $reversal, $replacement), 'Replacement linked');
        } catch (TreasuryRuleViolation $e) {
            return $this->validationError([$e->field ?? 'document' => [$e->getMessage()]]);
        }
    }

    /**
     * @param array<string, array<int, mixed>> $rules
     * @param Closure(Project, array<string, mixed>): TreasuryFinancialDocument $action
     */
    private function mutate(string $project, string $ability, Request $request, array $rules, Closure $action, bool $withAmount = true): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize($ability, $model);

        $common = [
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
        if ($withAmount) {
            $common['amount'] = ['required', 'numeric', 'gt:0', 'max:9999999999999.99', 'decimal:0,2'];
        }
        $validator = Validator::make($request->all(), $rules + $common);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $posted = $action($model, $validator->validated());
        } catch (TreasuryDuplicateSuspected $e) {
            return $this->errorResponse($e->getMessage(), 409, ['code' => 'DUPLICATE_SUSPECTED', 'existing_document_id' => (string) $e->existing->id]);
        } catch (TreasuryRuleViolation $e) {
            return $this->validationError([$e->field ?? 'document' => [$e->getMessage()]]);
        }

        return $this->successResponse($posted->fresh(), 'Treasury document posted', 201);
    }

    private function findProject(string $id): ?Project
    {
        return Project::query()
            ->where('tenant_id', (string) data_get(Auth::user(), 'tenant_id'))
            ->whereKey($id)
            ->first();
    }

    private function findDocument(Project $project, string $id): ?TreasuryFinancialDocument
    {
        return TreasuryFinancialDocument::query()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->whereKey($id)
            ->first();
    }

    private function user(): \App\Models\User
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return $user;
    }
}
