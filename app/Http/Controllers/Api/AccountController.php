<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ZenaContractResponseTrait;
use App\Models\Account;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccountController extends BaseApiController
{
    use ZenaContractResponseTrait;

    /** @var list<string> */
    private const RESPONSE_FIELDS = [
        'id',
        'tenant_id',
        'account_code',
        'account_type',
        'display_name',
        'legal_name',
        'tax_code',
        'phone',
        'email',
        'address',
        'province_or_city',
        'source_summary',
        'owner_id',
        'status',
        'created_at',
        'updated_at',
    ];

    private function tenantId(Request $request): string
    {
        $tenantId = $request->attributes->get('tenant_id')
            ?? app('current_tenant_id')
            ?? Auth::user()?->tenant_id;

        return $tenantId ? (string) $tenantId : '';
    }

    private function scopedQuery(string $tenantId): Builder
    {
        return Account::query()->forTenant($tenantId);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Account $account): array
    {
        return Arr::only($account->attributesToArray(), self::RESPONSE_FIELDS);
    }

    private function rules(): array
    {
        return [
            'account_type' => ['nullable', Rule::in(Account::VALID_TYPES)],
            'display_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_code' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'province_or_city' => ['nullable', 'string', 'max:100'],
            'source_summary' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(Account::VALID_STATUSES)],
        ];
    }

    public function index(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $this->authorize('viewAny', Account::class);

        $query = $this->scopedQuery($tenantId);

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('q')) {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], (string) $request->input('q'));
            $query->where(fn ($w) => $w
                ->where('display_name', 'like', '%' . $escaped . '%')
                ->orWhere('phone', 'like', '%' . $escaped . '%'));
        }

        $accounts = $query
            ->orderBy('display_name')
            ->get()
            ->map(fn (Account $account): array => $this->serialize($account))
            ->values();

        return $this->zenaSuccessResponse($accounts, 'Accounts retrieved successfully');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if (!Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $account = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$account instanceof Account) {
            return $this->notFound('Account not found');
        }

        $this->authorize('view', $account);

        return $this->zenaSuccessResponse($this->serialize($account), 'Account retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $this->authorize('create', Account::class);

        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $account = Account::query()->create([
            'tenant_id' => $tenantId,
            'account_code' => 'ACC-' . Str::upper((string) Str::ulid()),
            'account_type' => (string) $request->input('account_type', Account::TYPE_INDIVIDUAL),
            'display_name' => (string) $request->input('display_name'),
            'legal_name' => $request->input('legal_name'),
            'tax_code' => $request->input('tax_code'),
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'address' => $request->input('address'),
            'province_or_city' => $request->input('province_or_city'),
            'source_summary' => $request->input('source_summary'),
            'owner_id' => (string) $user->id,
            'status' => (string) $request->input('status', Account::STATUS_ACTIVE),
        ]);

        return $this->zenaSuccessResponse(
            $this->serialize($account->fresh() ?? $account),
            'Account created successfully',
            201
        );
    }

    public function update(Request $request, string $id): JsonResponse
    {
        if (!Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $account = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$account instanceof Account) {
            return $this->notFound('Account not found');
        }

        $this->authorize('update', $account);

        $rules = $this->rules();
        $rules['display_name'] = ['sometimes', 'required', 'string', 'max:255'];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $account->fill($request->only([
            'account_type', 'display_name', 'legal_name', 'tax_code', 'phone', 'email',
            'address', 'province_or_city', 'source_summary', 'status',
        ]));
        $account->save();

        return $this->zenaSuccessResponse(
            $this->serialize($account->fresh() ?? $account),
            'Account updated successfully'
        );
    }
}
