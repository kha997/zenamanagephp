<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Http\Requests\Unified\UserManagementRequest;
use App\Services\UserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\User;

/**
 * Users API Controller (V1)
 * 
 * Pure API controller for user operations.
 * Only returns JSON responses - no view rendering.
 * 
 * This replaces the unified UserManagementController for API routes.
 */
class UsersController extends BaseApiV1Controller
{
    public function __construct(
        private UserManagementService $userService
    ) {}

    /**
     * Get users list (API)
     * 
     * @param UserManagementRequest $request
     * @return JsonResponse
     */
    public function index(UserManagementRequest $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'status', 'role', 'is_active']);
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $tenantId = $this->getTenantId();

            // Check if cursor pagination is requested
            $cursor = $request->get('cursor');
            if ($cursor) {
                $limit = min((int) $request->get('limit', 15), 100);
                
                $result = $this->userService->getUsersCursor(
                    $filters,
                    $limit,
                    $cursor,
                    $sortBy,
                    $sortDirection,
                    $tenantId
                );
                
                $usersData = $result['data']->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'tenant_id' => $user->tenant_id,
                        'role' => $user->role,
                        'is_active' => $user->is_active,
                        'avatar' => $user->avatar,
                        'created_at' => $user->created_at?->toISOString(),
                        'updated_at' => $user->updated_at?->toISOString(),
                    ];
                })->toArray();
                
                return $this->successResponse([
                    'data' => $usersData,
                    'pagination' => [
                        'next_cursor' => $result['next_cursor'],
                        'has_more' => $result['has_more'],
                    ]
                ], 'Users retrieved successfully');
            }

            // Default: offset pagination
            $perPage = min((int) $request->get('per_page', 15), 1000);

            $users = $this->userService->getUsers(
                $filters,
                $perPage,
                $sortBy,
                $sortDirection,
                $tenantId
            );

            if (method_exists($users, 'items')) {
                $usersData = collect($users->items())->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'tenant_id' => $user->tenant_id,
                        'role' => $user->role,
                        'is_active' => $user->is_active,
                        'avatar' => $user->avatar,
                        'created_at' => $user->created_at?->toISOString(),
                        'updated_at' => $user->updated_at?->toISOString(),
                    ];
                })->toArray();

                return $this->paginatedResponse(
                    $usersData,
                    [
                        'current_page' => $users->currentPage(),
                        'per_page' => $users->perPage(),
                        'total' => $users->total(),
                        'last_page' => $users->lastPage(),
                        'from' => $users->firstItem(),
                        'to' => $users->lastItem(),
                    ],
                    'Users retrieved successfully'
                );
            }

            return $this->successResponse($users, 'Users retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, [
                'user_id' => Auth::id(),
                'tenant_id' => Auth::user()?->tenant_id,
            ]);
            
            return $this->errorResponse(
                'Failed to retrieve users: ' . $e->getMessage(),
                500,
                null,
                'USERS_RETRIEVE_FAILED'
            );
        }
    }

    /**
     * Get user by ID (API)
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $user = $this->userService->getUserById($id, $tenantId);
            
            if (!$user) {
                return $this->errorResponse('User not found', 404, null, 'USER_NOT_FOUND');
            }

            return $this->successResponse([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'tenant_id' => $user->tenant_id,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'avatar' => $user->avatar,
                'created_at' => $user->created_at?->toISOString(),
                'updated_at' => $user->updated_at?->toISOString(),
            ], 'User retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, [
                'user_id' => Auth::id(),
                'target_user_id' => $id,
            ]);
            
            return $this->errorResponse(
                'Failed to retrieve user: ' . $e->getMessage(),
                500,
                null,
                'USER_RETRIEVE_FAILED'
            );
        }
    }

    /**
     * Create user (API)
     * 
     * @param UserManagementRequest $request
     * @return JsonResponse
     */
    public function store(UserManagementRequest $request): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $user = $this->userService->createUser($request->all(), $tenantId);
            
            return $this->successResponse([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'tenant_id' => $user->tenant_id,
                'role' => $user->role,
                'is_active' => $user->is_active,
            ], 'User created successfully', 201);
        } catch (\Exception $e) {
            $this->logError($e, [
                'user_id' => Auth::id(),
            ]);
            
            return $this->errorResponse(
                'Failed to create user: ' . $e->getMessage(),
                $e->getCode() ?: 500,
                null,
                'USER_CREATE_FAILED'
            );
        }
    }

    /**
     * Update user (API)
     * 
     * @param UserManagementRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UserManagementRequest $request, int $id): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $user = $this->userService->updateUser($id, $request->all(), $tenantId);
            
            return $this->successResponse([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'tenant_id' => $user->tenant_id,
                'role' => $user->role,
                'is_active' => $user->is_active,
            ], 'User updated successfully');
        } catch (\Exception $e) {
            $this->logError($e, [
                'user_id' => Auth::id(),
                'target_user_id' => $id,
            ]);
            
            return $this->errorResponse(
                'Failed to update user: ' . $e->getMessage(),
                $e->getCode() ?: 500,
                null,
                'USER_UPDATE_FAILED'
            );
        }
    }

    /**
     * Delete user (API)
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $deleted = $this->userService->deleteUser($id, $tenantId);
            
            if (!$deleted) {
                return $this->errorResponse('Failed to delete user', 500, null, 'USER_DELETE_FAILED');
            }
            
            return $this->successResponse(null, 'User deleted successfully');
        } catch (\Exception $e) {
            $this->logError($e, [
                'user_id' => Auth::id(),
                'target_user_id' => $id,
            ]);
            
            return $this->errorResponse(
                'Failed to delete user: ' . $e->getMessage(),
                $e->getCode() ?: 500,
                null,
                'USER_DELETE_FAILED'
            );
        }
    }

    /**
     * Get Users KPIs with trends
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getKpis(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $period = $request->get('period', 'week');
            
            $now = Carbon::now();
            $currentPeriodStart = match($period) {
                'week' => $now->copy()->startOfWeek(),
                'month' => $now->copy()->startOfMonth(),
                default => $now->copy()->startOfWeek(),
            };
            
            $previousPeriodStart = match($period) {
                'week' => $currentPeriodStart->copy()->subWeek(),
                'month' => $currentPeriodStart->copy()->subMonth(),
                default => $currentPeriodStart->copy()->subWeek(),
            };
            $previousPeriodEnd = $currentPeriodStart->copy()->subSecond();

            $totalUsers = User::where('tenant_id', $tenantId)->count();
            $activeUsers = User::where('tenant_id', $tenantId)
                ->where('is_active', true)->count();
            $newUsers = User::where('tenant_id', $tenantId)
                ->where('created_at', '>=', $currentPeriodStart)->count();

            $byRole = User::where('tenant_id', $tenantId)
                ->selectRaw('role, COUNT(*) as count')
                ->groupBy('role')
                ->pluck('count', 'role')
                ->toArray();

            $previousTotalUsers = User::where('tenant_id', $tenantId)
                ->where('created_at', '<=', $previousPeriodEnd)->count();
            $previousActiveUsers = User::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->where('created_at', '<=', $previousPeriodEnd)->count();
            $previousNewUsers = User::where('tenant_id', $tenantId)
                ->where('created_at', '>=', $previousPeriodStart)
                ->where('created_at', '<=', $previousPeriodEnd)->count();

            $calculateTrend = function($current, $previous) {
                if ($previous == 0) return $current > 0 ? ['value' => 100, 'direction' => 'up'] : ['value' => 0, 'direction' => 'neutral'];
                $change = (($current - $previous) / $previous) * 100;
                return [
                    'value' => round(abs($change), 1),
                    'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'neutral'),
                ];
            };

            return $this->successResponse([
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'new_users' => $newUsers,
                'by_role' => $byRole,
                'trends' => [
                    'total_users' => $calculateTrend($totalUsers, $previousTotalUsers),
                    'active_users' => $calculateTrend($activeUsers, $previousActiveUsers),
                    'new_users' => $calculateTrend($newUsers, $previousNewUsers),
                ],
                'period' => $period,
            ], 'Users KPIs retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, [
                'user_id' => Auth::id(),
                'tenant_id' => $tenantId ?? null,
            ]);
            
            return $this->errorResponse(
                'Failed to load users KPIs: ' . $e->getMessage(),
                500,
                null,
                'USERS_KPIS_FAILED'
            );
        }
    }

    /**
     * Get Users Alerts
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getAlerts(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();

            $alerts = [];
            
            $inactiveUsers = User::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->where(function($q) {
                    $q->whereNull('last_login_at')
                      ->orWhere('last_login_at', '<', Carbon::now()->subDays(90));
                })
                ->get();
                
            foreach ($inactiveUsers as $inactiveUser) {
                $daysSinceLogin = $inactiveUser->last_login_at 
                    ? Carbon::parse($inactiveUser->last_login_at)->diffInDays(Carbon::now())
                    : Carbon::parse($inactiveUser->created_at)->diffInDays(Carbon::now());
                    
                $alerts[] = [
                    'id' => 'inactive-user-' . $inactiveUser->id,
                    'title' => 'Inactive User Detected',
                    'message' => "User '{$inactiveUser->name}' has not logged in for {$daysSinceLogin} days",
                    'severity' => $daysSinceLogin > 180 ? 'high' : 'medium',
                    'status' => 'unread',
                    'type' => 'inactive_user',
                    'source' => 'user',
                    'createdAt' => now()->toISOString(),
                    'metadata' => ['user_id' => $inactiveUser->id, 'days_since_login' => $daysSinceLogin]
                ];
            }

            return $this->successResponse($alerts, 'Users alerts retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, [
                'user_id' => Auth::id(),
                'tenant_id' => $tenantId ?? null,
            ]);
            
            return $this->errorResponse(
                'Failed to load users alerts: ' . $e->getMessage(),
                500,
                null,
                'USERS_ALERTS_FAILED'
            );
        }
    }

    /**
     * Get Users Activity
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getActivity(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $limit = (int) $request->get('limit', 10);

            $activity = [];
            
            $recentUsers = User::where('tenant_id', $tenantId)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
                
            foreach ($recentUsers as $recentUser) {
                $activity[] = [
                    'id' => 'user-' . $recentUser->id,
                    'type' => 'user',
                    'action' => 'created',
                    'description' => "User '{$recentUser->name}' was created",
                    'timestamp' => $recentUser->created_at->toISOString(),
                    'user' => [
                        'id' => $recentUser->id,
                        'name' => $recentUser->name
                    ]
                ];
            }

            $recentUpdates = User::where('tenant_id', $tenantId)
                ->where('updated_at', '>', Carbon::now()->subDays(7))
                ->whereColumn('updated_at', '>', 'created_at')
                ->orderBy('updated_at', 'desc')
                ->limit($limit)
                ->get();
                
            foreach ($recentUpdates as $updatedUser) {
                $activity[] = [
                    'id' => 'user-update-' . $updatedUser->id,
                    'type' => 'user',
                    'action' => 'updated',
                    'description' => "User '{$updatedUser->name}' profile was updated",
                    'timestamp' => $updatedUser->updated_at->toISOString(),
                    'user' => [
                        'id' => $updatedUser->id,
                        'name' => $updatedUser->name
                    ]
                ];
            }

            usort($activity, fn($a, $b) => strtotime($b['timestamp']) - strtotime($a['timestamp']));
            $activity = array_slice($activity, 0, $limit);

            return $this->successResponse($activity, 'Users activity retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, [
                'user_id' => Auth::id(),
                'tenant_id' => $tenantId ?? null,
            ]);
            
            return $this->errorResponse(
                'Failed to load users activity: ' . $e->getMessage(),
                500,
                null,
                'USERS_ACTIVITY_FAILED'
            );
        }
    }
}

