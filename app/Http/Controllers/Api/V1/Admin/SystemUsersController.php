<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Models\User;
use App\Services\UserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemUsersController extends BaseApiV1Controller
{
    public function __construct(
        private readonly UserManagementService $userService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('users.manage');

        $filters = array_filter($request->only([
            'search',
            'role',
            'status',
            'is_active',
        ]), fn ($value) => $value !== null && $value !== '');

        $perPage = (int) $request->input('per_page', 25);
        $perPage = max(1, min($perPage, 100));

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = strtolower($request->input('sort_direction', 'desc'));
        $sortDirection = in_array($sortDirection, ['asc', 'desc'], true) ? $sortDirection : 'desc';

        $tenantFilter = $request->input('tenant_id');
        if ($tenantFilter === '') {
            $tenantFilter = null;
        }

        $paginator = $this->userService->getUsers(
            $filters,
            $perPage,
            $sortBy,
            $sortDirection,
            $tenantFilter
        );

        $payload = [
            'users' => $this->formatUsers($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => [
                'search' => $request->input('search'),
                'role' => $request->input('role'),
                'status' => $request->input('status'),
                'is_active' => $request->input('is_active'),
                'tenant_id' => $tenantFilter,
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
            ],
        ];

        return $this->successResponse($payload, 'System users retrieved successfully');
    }

    /**
     * Map users to JSON-friendly arrays.
     */
    private function formatUsers(array $users): array
    {
        return collect($users)->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'tenant_id' => $user->tenant_id,
            'tenant_name' => $user->tenant?->name,
            'last_login_at' => $user->last_login_at?->toISOString(),
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ])->toArray();
    }
}
