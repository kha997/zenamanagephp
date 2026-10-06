<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\Treasury;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Project;
use App\Models\Treasury\TreasuryWallet;
use App\Services\Treasury\TreasurySetupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * GAP-063 S1 — project treasury wallets (project-scoped only in S1).
 */
class TreasuryWalletController extends BaseApiController
{
    public function __construct(private readonly TreasurySetupService $setup)
    {
    }

    public function index(string $project): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.view-project', $model);

        $wallets = TreasuryWallet::query()
            ->where('tenant_id', (string) $model->tenant_id)
            ->where('project_id', (string) $model->id)
            ->with('custodianParty')
            ->orderBy('name')
            ->get();

        return $this->successResponse($wallets, 'Treasury wallets retrieved successfully');
    }

    public function show(string $project, string $wallet): JsonResponse
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

        return $this->successResponse($walletModel->load('custodianParty'), 'Treasury wallet retrieved successfully');
    }

    public function store(Request $request, string $project): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.manage-wallets', $model);

        $validator = Validator::make($request->all(), $this->setup->walletRules((string) $model->tenant_id));
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $wallet = $this->setup->createWallet($model, $validator->validated());

        return $this->successResponse($wallet, 'Treasury wallet created successfully', 201);
    }

    public function update(Request $request, string $project, string $wallet): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.manage-wallets', $model);

        $walletModel = $this->findWallet($model, $wallet);
        if ($walletModel === null) {
            return $this->notFound('Treasury wallet not found');
        }

        if ($request->has('project_id') && (string) $request->input('project_id') !== (string) $model->id) {
            return $this->validationError(['project_id' => ['Không đổi được dự án của ví.']]);
        }

        $validator = Validator::make($request->all(), $this->setup->walletRules((string) $model->tenant_id, true));
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        return $this->successResponse(
            $this->setup->updateWallet($walletModel, $validator->validated()),
            'Treasury wallet updated successfully'
        );
    }

    public function destroy(string $project, string $wallet): JsonResponse
    {
        $model = $this->findProject($project);
        if ($model === null) {
            return $this->notFound('Project not found');
        }
        $this->authorize('treasury.manage-wallets', $model);

        $walletModel = $this->findWallet($model, $wallet);
        if ($walletModel === null) {
            return $this->notFound('Treasury wallet not found');
        }

        if ($this->setup->walletInUse($walletModel)) {
            return $this->errorResponse('Ví đã có giao dịch nên không thể xoá.', 409);
        }

        $walletModel->delete();

        return $this->successResponse(null, 'Treasury wallet deleted successfully');
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
}
