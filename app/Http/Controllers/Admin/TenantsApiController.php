<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Response;

class TenantsApiController extends Controller
{
    /**
     * Get tenants list with search, filters, and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,suspended,trial,disabled',
            'plan' => 'nullable|in:Basic,Professional,Enterprise',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'sort' => 'nullable|string|in:name,-name,domain,-domain,plan,-plan,status,-status,usersCount,-usersCount,lastActiveAt,-lastActiveAt,createdAt,-createdAt',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid request parameters',
                    'details' => $validator->errors()
                ]
            ], 422);
        }

        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);
        $sort = $request->get('sort', 'name');
        
        // Generate cache key based on query parameters
        $cacheKey = 'tenants_list_' . md5(serialize($request->query()));
        
        // Check ETag for caching
        $etag = md5($cacheKey . '_' . $page . '_' . $perPage);
        if ($request->header('If-None-Match') === $etag) {
            return response()->json(null, 304);
        }

        // Mock data for now - replace with real database queries
        $tenants = $this->getMockTenants();
        
        // Apply filters
        $filteredTenants = $this->applyFilters($tenants, $request);
        
        // Apply sorting
        $sortedTenants = $this->applySorting($filteredTenants, $sort);
        
        // Apply pagination
        $total = count($sortedTenants);
        $lastPage = ceil($total / $perPage);
        $paginatedTenants = array_slice($sortedTenants, ($page - 1) * $perPage, $perPage);

        $response = [
            'data' => $paginatedTenants,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => $lastPage,
                'generatedAt' => now()->toISOString()
            ]
        ];

        return response()->json($response)
            ->header('ETag', $etag)
            ->header('Cache-Control', 'public, max-age=30, stale-while-revalidate=30');
    }

    /**
     * Create a new tenant
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'domain' => 'required|string|max:255|unique:tenants,domain',
            'ownerName' => 'required|string|max:255',
            'ownerEmail' => 'required|email|max:255',
            'plan' => 'required|in:Basic,Professional,Enterprise'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid request data',
                    'details' => $validator->errors()
                ]
            ], 422);
        }

        // Mock creation - replace with real database operation
        $tenant = [
            'id' => (string) (time() + rand(1000, 9999)),
            'name' => $request->name,
            'domain' => $request->domain,
            'ownerName' => $request->ownerName,
            'ownerEmail' => $request->ownerEmail,
            'plan' => $request->plan,
            'status' => 'active',
            'usersCount' => 0,
            'projectsCount' => 0,
            'lastActiveAt' => now()->toISOString(),
            'createdAt' => now()->toISOString()
        ];

        return response()->json(['data' => $tenant], 201);
    }

    /**
     * Get tenant details
     */
    public function show(string $id): JsonResponse
    {
        // Mock data - replace with real database query
        $tenant = $this->getMockTenantById($id);
        
        if (!$tenant) {
            return response()->json([
                'error' => [
                    'code' => 'TENANT_NOT_FOUND',
                    'message' => 'Tenant not found',
                    'details' => null
                ]
            ], 404);
        }

        return response()->json(['data' => $tenant]);
    }

    /**
     * Update tenant
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'domain' => 'sometimes|string|max:255',
            'ownerName' => 'sometimes|string|max:255',
            'ownerEmail' => 'sometimes|email|max:255',
            'plan' => 'sometimes|in:Basic,Professional,Enterprise'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid request data',
                    'details' => $validator->errors()
                ]
            ], 422);
        }

        // Mock update - replace with real database operation
        $tenant = $this->getMockTenantById($id);
        
        if (!$tenant) {
            return response()->json([
                'error' => [
                    'code' => 'TENANT_NOT_FOUND',
                    'message' => 'Tenant not found',
                    'details' => null
                ]
            ], 404);
        }

        // Update fields
        foreach ($request->all() as $key => $value) {
            if (isset($tenant[$key])) {
                $tenant[$key] = $value;
            }
        }

        return response()->json(['data' => $tenant]);
    }

    /**
     * Delete tenant
     */
    public function destroy(string $id): JsonResponse
    {
        // Mock deletion - replace with real database operation
        $tenant = $this->getMockTenantById($id);
        
        if (!$tenant) {
            return response()->json([
                'error' => [
                    'code' => 'TENANT_NOT_FOUND',
                    'message' => 'Tenant not found',
                    'details' => null
                ]
            ], 404);
        }

        return response()->json(['message' => 'Tenant deleted successfully']);
    }

    /**
     * Enable tenant
     */
    public function enable(string $id): JsonResponse
    {
        $tenant = $this->getMockTenantById($id);
        
        if (!$tenant) {
            return response()->json([
                'error' => [
                    'code' => 'TENANT_NOT_FOUND',
                    'message' => 'Tenant not found',
                    'details' => null
                ]
            ], 404);
        }

        $tenant['status'] = 'active';
        return response()->json(['data' => $tenant]);
    }

    /**
     * Disable tenant
     */
    public function disable(string $id): JsonResponse
    {
        $tenant = $this->getMockTenantById($id);
        
        if (!$tenant) {
            return response()->json([
                'error' => [
                    'code' => 'TENANT_NOT_FOUND',
                    'message' => 'Tenant not found',
                    'details' => null
                ]
            ], 404);
        }

        $tenant['status'] = 'suspended';
        return response()->json(['data' => $tenant]);
    }

    /**
     * Change tenant plan
     */
    public function changePlan(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan' => 'required|in:Basic,Professional,Enterprise'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid plan',
                    'details' => $validator->errors()
                ]
            ], 422);
        }

        $tenant = $this->getMockTenantById($id);
        
        if (!$tenant) {
            return response()->json([
                'error' => [
                    'code' => 'TENANT_NOT_FOUND',
                    'message' => 'Tenant not found',
                    'details' => null
                ]
            ], 404);
        }

        $tenant['plan'] = $request->plan;
        return response()->json(['data' => $tenant]);
    }

    /**
     * Bulk actions
     */
    public function bulk(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:activate,suspend,delete,change-plan',
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
            'plan' => 'required_if:action,change-plan|in:Basic,Professional,Enterprise'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid bulk action parameters',
                    'details' => $validator->errors()
                ]
            ], 422);
        }

        $action = $request->action;
        $ids = $request->ids;
        $ok = [];
        $failed = [];

        foreach ($ids as $id) {
            try {
                $tenant = $this->getMockTenantById($id);
                
                if (!$tenant) {
                    $failed[] = ['id' => $id, 'error' => 'Tenant not found'];
                    continue;
                }

                // Mock bulk action
                switch ($action) {
                    case 'activate':
                        $tenant['status'] = 'active';
                        break;
                    case 'suspend':
                        $tenant['status'] = 'suspended';
                        break;
                    case 'delete':
                        // Mock deletion
                        break;
                    case 'change-plan':
                        $tenant['plan'] = $request->plan;
                        break;
                }

                $ok[] = $id;
            } catch (\Exception $e) {
                $failed[] = ['id' => $id, 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'ok' => $ok,
            'failed' => $failed,
            'message' => sprintf('Bulk %s completed: %d success, %d failed', $action, count($ok), count($failed))
        ]);
    }

    /**
     * Export tenants
     */
    public function export(Request $request): JsonResponse
    {
        // Rate limiting check (mock)
        $rateLimitKey = 'export_rate_limit_' . $request->ip();
        $attempts = Cache::get($rateLimitKey, 0);
        
        if ($attempts >= 30) {
            return response()->json([
                'error' => [
                    'code' => 'RATE_LIMITED',
                    'message' => 'Too many export requests. Please try again later.',
                    'details' => null
                ]
            ], 429)->header('Retry-After', '600');
        }

        Cache::put($rateLimitKey, $attempts + 1, 600); // 10 minutes

        // Get filtered tenants
        $tenants = $this->getMockTenants();
        $filteredTenants = $this->applyFilters($tenants, $request);
        
        // Generate CSV
        $csv = "ID,Name,Domain,Owner,Owner Email,Plan,Status,Users,Projects,Last Active,Created\n";
        foreach ($filteredTenants as $tenant) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%d,%d,%s,%s\n",
                $tenant['id'],
                $tenant['name'],
                $tenant['domain'],
                $tenant['ownerName'],
                $tenant['ownerEmail'],
                $tenant['plan'],
                $tenant['status'],
                $tenant['usersCount'] ?? 0,
                $tenant['projectsCount'] ?? 0,
                $tenant['lastActiveAt'],
                $tenant['createdAt']
            );
        }

        $filename = 'tenants-export-' . now()->format('Y-m-d') . '.csv';
        
        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ]);
    }

    /**
     * Mock data methods - replace with real database queries
     */
    private function getMockTenants(): array
    {
        return [
            [
                'id' => '1',
                'name' => 'TechCorp',
                'domain' => 'techcorp.com',
                'ownerName' => 'John Doe',
                'ownerEmail' => 'john@techcorp.com',
                'plan' => 'Professional',
                'status' => 'active',
                'usersCount' => 25,
                'projectsCount' => 8,
                'lastActiveAt' => '2024-09-27T10:30:00Z',
                'createdAt' => '2024-01-15T00:00:00Z'
            ],
            [
                'id' => '2',
                'name' => 'DesignStudio',
                'domain' => 'designstudio.com',
                'ownerName' => 'Jane Smith',
                'ownerEmail' => 'jane@designstudio.com',
                'plan' => 'Basic',
                'status' => 'active',
                'usersCount' => 8,
                'projectsCount' => 3,
                'lastActiveAt' => '2024-09-26T15:45:00Z',
                'createdAt' => '2024-02-20T00:00:00Z'
            ],
            [
                'id' => '3',
                'name' => 'StartupXYZ',
                'domain' => 'startupxyz.com',
                'ownerName' => 'Mike Johnson',
                'ownerEmail' => 'mike@startupxyz.com',
                'plan' => 'Enterprise',
                'status' => 'suspended',
                'usersCount' => 45,
                'projectsCount' => 12,
                'lastActiveAt' => '2024-09-20T09:15:00Z',
                'createdAt' => '2024-03-10T00:00:00Z'
            ],
            [
                'id' => '4',
                'name' => 'TrialCompany',
                'domain' => 'trialcompany.com',
                'ownerName' => 'Sarah Wilson',
                'ownerEmail' => 'sarah@trialcompany.com',
                'plan' => 'Basic',
                'status' => 'trial',
                'usersCount' => 3,
                'projectsCount' => 1,
                'lastActiveAt' => '2024-09-25T14:20:00Z',
                'createdAt' => '2024-09-01T00:00:00Z'
            ]
        ];
    }

    private function getMockTenantById(string $id): ?array
    {
        $tenants = $this->getMockTenants();
        foreach ($tenants as $tenant) {
            if ($tenant['id'] === $id) {
                return $tenant;
            }
        }
        return null;
    }

    private function applyFilters(array $tenants, Request $request): array
    {
        $query = $request->get('q');
        $status = $request->get('status');
        $plan = $request->get('plan');
        $from = $request->get('from');
        $to = $request->get('to');

        return array_filter($tenants, function ($tenant) use ($query, $status, $plan, $from, $to) {
            // Search query
            if ($query) {
                $searchFields = [$tenant['name'], $tenant['domain'], $tenant['ownerName'], $tenant['ownerEmail']];
                $matchesQuery = false;
                foreach ($searchFields as $field) {
                    if (stripos($field, $query) !== false) {
                        $matchesQuery = true;
                        break;
                    }
                }
                if (!$matchesQuery) return false;
            }

            // Status filter
            if ($status && $tenant['status'] !== $status) {
                return false;
            }

            // Plan filter
            if ($plan && $tenant['plan'] !== $plan) {
                return false;
            }

            // Date range filters
            if ($from) {
                $createdAt = new \DateTime($tenant['createdAt']);
                $fromDate = new \DateTime($from);
                if ($createdAt < $fromDate) return false;
            }

            if ($to) {
                $createdAt = new \DateTime($tenant['createdAt']);
                $toDate = new \DateTime($to);
                if ($createdAt > $toDate) return false;
            }

            return true;
        });
    }

    private function applySorting(array $tenants, string $sort): array
    {
        $field = ltrim($sort, '-');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        usort($tenants, function ($a, $b) use ($field, $direction) {
            $valueA = $a[$field] ?? '';
            $valueB = $b[$field] ?? '';

            if (is_numeric($valueA) && is_numeric($valueB)) {
                $result = $valueA <=> $valueB;
            } else {
                $result = strcasecmp($valueA, $valueB);
            }

            return $direction === 'desc' ? -$result : $result;
        });

        return $tenants;
    }
}
