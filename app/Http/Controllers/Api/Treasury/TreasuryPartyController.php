<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\Treasury;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Services\Treasury\TreasurySetupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * GAP-063 S1 — tenant-level treasury financial parties.
 */
class TreasuryPartyController extends BaseApiController
{
    public function __construct(private readonly TreasurySetupService $setup)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('treasury.view-parties');

        $perPage = min((int) $request->input('per_page', 50), 100);
        $query = TreasuryFinancialParty::query()
            ->where('tenant_id', $this->tenantId())
            ->orderBy('name');

        if (($type = (string) $request->input('party_type')) !== '') {
            $query->where('party_type', $type);
        }

        return $this->listSuccessResponse($query->paginate($perPage), 'Treasury parties retrieved successfully');
    }

    public function show(string $party): JsonResponse
    {
        $this->authorize('treasury.view-parties');
        $model = $this->findParty($party);
        if ($model === null) {
            return $this->notFound('Treasury party not found');
        }

        return $this->successResponse($model, 'Treasury party retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('treasury.manage-parties');

        $validator = Validator::make($request->all(), $this->setup->partyRules($this->tenantId()));
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $party = $this->setup->createParty($this->tenantId(), $validator->validated());

        return $this->successResponse($party, 'Treasury party created successfully', 201);
    }

    public function update(Request $request, string $party): JsonResponse
    {
        $this->authorize('treasury.manage-parties');
        $model = $this->findParty($party);
        if ($model === null) {
            return $this->notFound('Treasury party not found');
        }

        $validator = Validator::make($request->all(), $this->setup->partyRules($this->tenantId(), true));
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        return $this->successResponse(
            $this->setup->updateParty($model, $validator->validated()),
            'Treasury party updated successfully'
        );
    }

    public function destroy(string $party): JsonResponse
    {
        $this->authorize('treasury.manage-parties');
        $model = $this->findParty($party);
        if ($model === null) {
            return $this->notFound('Treasury party not found');
        }

        if ($this->setup->partyInUse($model)) {
            return $this->errorResponse('Đối tác đang được dùng (ví hoặc giao dịch) nên không thể xoá.', 409);
        }

        $model->delete();

        return $this->successResponse(null, 'Treasury party deleted successfully');
    }

    private function findParty(string $id): ?TreasuryFinancialParty
    {
        return TreasuryFinancialParty::query()
            ->where('tenant_id', $this->tenantId())
            ->whereKey($id)
            ->first();
    }

    private function tenantId(): string
    {
        return (string) data_get(Auth::user(), 'tenant_id');
    }
}
