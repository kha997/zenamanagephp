<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UsersApiController extends Controller
{
    /**
     * Get users list with search, filters, and pagination
     */
    public function index(Request $request): JsonResponse
    {
        // Validate request parameters
        $validated = $request->validate([
            'q' => 'nullable|string|max:255',
            'tenant' => 'nullable|string',
            'role' => 'nullable|string|in:SuperAdmin,TenantAdmin,PM,Staff,Viewer',
            'status' => 'nullable|string|in:active,disabled,locked,invited',
            'mfa' => 'nullable|string|in:true,false',
            'activeWithin' => 'nullable|string|in:7d,30d,90d',
            'lastLoginFrom' => 'nullable|date',
            'lastLoginTo' => 'nullable|date',
            'createdFrom' => 'nullable|date',
            'createdTo' => 'nullable|date',
            'sort' => 'nullable|string|in:name,tenantName,role,status,mfaEnabled,lastLoginAt,createdAt,-name,-tenantName,-role,-status,-mfaEnabled,-lastLoginAt,-createdAt',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        // Remove null/empty values
        $validated = array_filter($validated, function($value) {
            return $value !== null && $value !== '' && $value !== 'undefined';
        });

        Log::info('Users API request', $validated);

        // Get mock users data
        $users = $this->getMockUsers();
        
        // Apply filters
        $filteredUsers = $this->applyFilters($users, $validated);
        
        // Apply sorting
        $sortedUsers = $this->applySorting($filteredUsers, $validated['sort'] ?? 'name');
        
        // Apply pagination
        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 20;
        $total = count($sortedUsers);
        $lastPage = ceil($total / $perPage);
        
        $paginatedUsers = array_slice($sortedUsers, ($page - 1) * $perPage, $perPage);

        // Generate ETag for caching
        $etag = md5(json_encode($validated) . $total);
        
        // Check If-None-Match header
        if ($request->header('If-None-Match') === $etag) {
            return response()->json(null, 304);
        }

        return response()->json([
            'data' => $paginatedUsers,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => $lastPage,
                'generatedAt' => now()->toISOString()
            ]
        ])->withHeaders([
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=30, stale-while-revalidate=30',
            'X-Generated-At' => now()->toISOString()
        ]);
    }

    /**
     * Get user details by ID
     */
    public function show(string $id): JsonResponse
    {
        $users = $this->getMockUsers();
        $user = collect($users)->firstWhere('id', $id);

        if (!$user) {
            return $this->sendErrorResponse('USER_NOT_FOUND', 'User not found', 404);
        }

        return response()->json([
            'data' => $user
        ]);
    }

    /**
     * Create a new user
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'tenantId' => 'required|string',
            'role' => 'required|string|in:SuperAdmin,TenantAdmin,PM,Staff,Viewer'
        ]);

        // Mock creation
        $newUser = [
            'id' => (string) (count($this->getMockUsers()) + 1),
            'name' => $validated['name'],
            'email' => $validated['email'],
            'tenantId' => $validated['tenantId'],
            'tenantName' => $this->getTenantName($validated['tenantId']),
            'role' => $validated['role'],
            'status' => 'invited',
            'mfaEnabled' => false,
            'lastLoginAt' => null,
            'createdAt' => now()->toISOString()
        ];

        return response()->json([
            'data' => $newUser
        ], 201);
    }

    /**
     * Update user
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'tenantId' => 'sometimes|string',
            'role' => 'sometimes|string|in:SuperAdmin,TenantAdmin,PM,Staff,Viewer'
        ]);

        $users = $this->getMockUsers();
        $user = collect($users)->firstWhere('id', $id);

        if (!$user) {
            return $this->sendErrorResponse('USER_NOT_FOUND', 'User not found', 404);
        }

        // Mock update
        $updatedUser = array_merge($user, $validated);
        if (isset($validated['tenantId'])) {
            $updatedUser['tenantName'] = $this->getTenantName($validated['tenantId']);
        }

        return response()->json([
            'data' => $updatedUser
        ]);
    }

    /**
     * Delete user
     */
    public function destroy(string $id): JsonResponse
    {
        $users = $this->getMockUsers();
        $user = collect($users)->firstWhere('id', $id);

        if (!$user) {
            return $this->sendErrorResponse('USER_NOT_FOUND', 'User not found', 404);
        }

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Enable user
     */
    public function enable(string $id): JsonResponse
    {
        $users = $this->getMockUsers();
        $user = collect($users)->firstWhere('id', $id);

        if (!$user) {
            return $this->sendErrorResponse('USER_NOT_FOUND', 'User not found', 404);
        }

        $user['status'] = 'active';

        return response()->json([
            'data' => $user
        ]);
    }

    /**
     * Disable user
     */
    public function disable(string $id): JsonResponse
    {
        $users = $this->getMockUsers();
        $user = collect($users)->firstWhere('id', $id);

        if (!$user) {
            return $this->sendErrorResponse('USER_NOT_FOUND', 'User not found', 404);
        }

        $user['status'] = 'disabled';

        return response()->json([
            'data' => $user
        ]);
    }

    /**
     * Unlock user
     */
    public function unlock(string $id): JsonResponse
    {
        $users = $this->getMockUsers();
        $user = collect($users)->firstWhere('id', $id);

        if (!$user) {
            return $this->sendErrorResponse('USER_NOT_FOUND', 'User not found', 404);
        }

        $user['status'] = 'active';

        return response()->json([
            'data' => $user
        ]);
    }

    /**
     * Change user role
     */
    public function changeRole(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'role' => 'required|string|in:SuperAdmin,TenantAdmin,PM,Staff,Viewer'
        ]);

        $users = $this->getMockUsers();
        $user = collect($users)->firstWhere('id', $id);

        if (!$user) {
            return $this->sendErrorResponse('USER_NOT_FOUND', 'User not found', 404);
        }

        $user['role'] = $validated['role'];

        return response()->json([
            'data' => $user
        ]);
    }

    /**
     * Force MFA
     */
    public function forceMfa(string $id): JsonResponse
    {
        $users = $this->getMockUsers();
        $user = collect($users)->firstWhere('id', $id);

        if (!$user) {
            return $this->sendErrorResponse('USER_NOT_FOUND', 'User not found', 404);
        }

        $user['mfaEnabled'] = true;

        return response()->json([
            'data' => $user
        ]);
    }

    /**
     * Send reset password link
     */
    public function sendResetLink(string $id): JsonResponse
    {
        $users = $this->getMockUsers();
        $user = collect($users)->firstWhere('id', $id);

        if (!$user) {
            return $this->sendErrorResponse('USER_NOT_FOUND', 'User not found', 404);
        }

        return response()->json([
            'message' => 'Reset link sent successfully'
        ]);
    }

    /**
     * Bulk actions
     */
    public function bulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:enable,disable,unlock,change-role,force-mfa,send-reset,delete',
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
            'role' => 'required_if:action,change-role|string|in:SuperAdmin,TenantAdmin,PM,Staff,Viewer',
            'required' => 'required_if:action,force-mfa|boolean'
        ]);

        $users = $this->getMockUsers();
        $ok = [];
        $failed = [];

        foreach ($validated['ids'] as $id) {
            $user = collect($users)->firstWhere('id', $id);
            
            if (!$user) {
                $failed[] = [
                    'id' => $id,
                    'error' => 'User not found'
                ];
                continue;
            }

            // Mock bulk action
            switch ($validated['action']) {
                case 'enable':
                    $user['status'] = 'active';
                    break;
                case 'disable':
                    $user['status'] = 'disabled';
                    break;
                case 'unlock':
                    $user['status'] = 'active';
                    break;
                case 'change-role':
                    $user['role'] = $validated['role'];
                    break;
                case 'force-mfa':
                    $user['mfaEnabled'] = true;
                    break;
                case 'send-reset':
                    // Mock send reset link
                    break;
                case 'delete':
                    // Mock delete
                    break;
            }

            $ok[] = $id;
        }

        return response()->json([
            'ok' => $ok,
            'failed' => $failed
        ]);
    }

    /**
     * Export users
     */
    public function export(Request $request): Response
    {
        // Rate limiting
        $key = 'users_export_' . ($request->ip() ?? 'unknown');
        $attempts = Cache::get($key, 0);
        
        if ($attempts >= 30) {
            return response()->json([
                'error' => [
                    'code' => 'RATE_LIMITED',
                    'message' => 'Too many export requests. Please try again later.'
                ]
            ], 429)->withHeaders([
                'Retry-After' => '600' // 10 minutes
            ]);
        }

        Cache::put($key, $attempts + 1, 600); // 10 minutes

        // Get filtered users
        $validated = $request->validate([
            'q' => 'nullable|string|max:255',
            'tenant' => 'nullable|string',
            'role' => 'nullable|string',
            'status' => 'nullable|string',
            'mfa' => 'nullable|string',
            'activeWithin' => 'nullable|string',
            'lastLoginFrom' => 'nullable|date',
            'lastLoginTo' => 'nullable|date',
            'createdFrom' => 'nullable|date',
            'createdTo' => 'nullable|date',
            'sort' => 'nullable|string'
        ]);

        $users = $this->getMockUsers();
        $filteredUsers = $this->applyFilters($users, $validated);
        $sortedUsers = $this->applySorting($filteredUsers, $validated['sort'] ?? 'name');

        // Generate CSV
        $csv = "ID,Name,Email,Tenant,Role,Status,MFA,Last Login,Created\n";
        foreach ($sortedUsers as $user) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $user['id'],
                $user['name'],
                $user['email'],
                $user['tenantName'],
                $user['role'],
                $user['status'],
                $user['mfaEnabled'] ? 'Enabled' : 'Not Enabled',
                $user['lastLoginAt'] ? date('Y-m-d H:i:s', strtotime($user['lastLoginAt'])) : 'Never',
                date('Y-m-d H:i:s', strtotime($user['createdAt']))
            );
        }

        $filename = 'users_export_' . date('Y-m-d_H-i-s') . '.csv';

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Get mock users data
     */
    private function getMockUsers(): array
    {
        return [
            [
                'id' => '1',
                'name' => 'John Doe',
                'email' => 'john@acme.com',
                'tenantId' => '1',
                'tenantName' => 'Acme Corp',
                'role' => 'TenantAdmin',
                'status' => 'active',
                'mfaEnabled' => true,
                'lastLoginAt' => '2024-09-27T10:30:00Z',
                'createdAt' => '2024-08-15T09:00:00Z'
            ],
            [
                'id' => '2',
                'name' => 'Jane Smith',
                'email' => 'jane@techstart.com',
                'tenantId' => '2',
                'tenantName' => 'TechStart',
                'role' => 'PM',
                'status' => 'active',
                'mfaEnabled' => false,
                'lastLoginAt' => '2024-09-26T14:20:00Z',
                'createdAt' => '2024-09-01T11:30:00Z'
            ],
            [
                'id' => '3',
                'name' => 'Bob Wilson',
                'email' => 'bob@enterprise.com',
                'tenantId' => '3',
                'tenantName' => 'Enterprise Inc',
                'role' => 'Staff',
                'status' => 'locked',
                'mfaEnabled' => true,
                'lastLoginAt' => '2024-09-20T16:45:00Z',
                'createdAt' => '2024-07-10T08:15:00Z'
            ],
            [
                'id' => '4',
                'name' => 'Alice Johnson',
                'email' => 'alice@acme.com',
                'tenantId' => '1',
                'tenantName' => 'Acme Corp',
                'role' => 'Staff',
                'status' => 'active',
                'mfaEnabled' => false,
                'lastLoginAt' => '2024-09-25T09:15:00Z',
                'createdAt' => '2024-08-20T14:30:00Z'
            ],
            [
                'id' => '5',
                'name' => 'Charlie Brown',
                'email' => 'charlie@techstart.com',
                'tenantId' => '2',
                'tenantName' => 'TechStart',
                'role' => 'Viewer',
                'status' => 'invited',
                'mfaEnabled' => false,
                'lastLoginAt' => null,
                'createdAt' => '2024-09-28T16:00:00Z'
            ]
        ];
    }

    /**
     * Apply filters to users
     */
    private function applyFilters(array $users, array $filters): array
    {
        return array_filter($users, function ($user) use ($filters) {
            // Search query
            if (isset($filters['q']) && $filters['q']) {
                $query = strtolower($filters['q']);
                if (!str_contains(strtolower($user['name']), $query) && 
                    !str_contains(strtolower($user['email']), $query)) {
                    return false;
                }
            }

            // Tenant filter
            if (isset($filters['tenant']) && $filters['tenant']) {
                if ($user['tenantId'] !== $filters['tenant']) {
                    return false;
                }
            }

            // Role filter
            if (isset($filters['role']) && $filters['role']) {
                if ($user['role'] !== $filters['role']) {
                    return false;
                }
            }

            // Status filter
            if (isset($filters['status']) && $filters['status']) {
                if ($user['status'] !== $filters['status']) {
                    return false;
                }
            }

            // MFA filter
            if (isset($filters['mfa']) && $filters['mfa']) {
                $mfaEnabled = $filters['mfa'] === 'true';
                if ($user['mfaEnabled'] !== $mfaEnabled) {
                    return false;
                }
            }

            // Active within filter
            if (isset($filters['activeWithin']) && $filters['activeWithin']) {
                $days = (int) str_replace('d', '', $filters['activeWithin']);
                $cutoff = now()->subDays($days);
                if (!$user['lastLoginAt'] || strtotime($user['lastLoginAt']) < $cutoff->timestamp) {
                    return false;
                }
            }

            // Last login date range
            if (isset($filters['lastLoginFrom']) && $filters['lastLoginFrom']) {
                if (!$user['lastLoginAt'] || strtotime($user['lastLoginAt']) < strtotime($filters['lastLoginFrom'])) {
                    return false;
                }
            }

            if (isset($filters['lastLoginTo']) && $filters['lastLoginTo']) {
                if (!$user['lastLoginAt'] || strtotime($user['lastLoginAt']) > strtotime($filters['lastLoginTo'])) {
                    return false;
                }
            }

            // Created date range
            if (isset($filters['createdFrom']) && $filters['createdFrom']) {
                if (strtotime($user['createdAt']) < strtotime($filters['createdFrom'])) {
                    return false;
                }
            }

            if (isset($filters['createdTo']) && $filters['createdTo']) {
                if (strtotime($user['createdAt']) > strtotime($filters['createdTo'])) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Apply sorting to users
     */
    private function applySorting(array $users, string $sort): array
    {
        $sortField = ltrim($sort, '-');
        $sortOrder = str_starts_with($sort, '-') ? 'desc' : 'asc';

        usort($users, function ($a, $b) use ($sortField, $sortOrder) {
            $aValue = $a[$sortField] ?? '';
            $bValue = $b[$sortField] ?? '';

            if (is_string($aValue)) {
                $aValue = strtolower($aValue);
            }
            if (is_string($bValue)) {
                $bValue = strtolower($bValue);
            }

            if ($sortOrder === 'asc') {
                return $aValue <=> $bValue;
            } else {
                return $bValue <=> $aValue;
            }
        });

        return $users;
    }

    /**
     * Get tenant name by ID
     */
    private function getTenantName(string $tenantId): string
    {
        $tenants = [
            '1' => 'Acme Corp',
            '2' => 'TechStart',
            '3' => 'Enterprise Inc'
        ];

        return $tenants[$tenantId] ?? 'Unknown';
    }

    /**
     * Send error response
     */
    private function sendErrorResponse(string $code, string $message, int $status = 400): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => null
            ]
        ], $status);
    }
}
