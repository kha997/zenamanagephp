<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TenantIndexRequest;
use App\Http\Requests\TenantStoreRequest;
use App\Http\Requests\TenantUpdateRequest;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TenantController extends Controller
{
    /**
     * Get tenants list with search, filters, and pagination
     */
    public function index(TenantIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $query = Tenant::query()->withCount(['users', 'projects']);

        // Search
        if ($q = $validated['q'] ?? null) {
            $query->where(function($w) use ($q) {
                $w->where('name', 'like', "%$q%")
                  ->orWhere('domain', 'like', "%$q%")
                  ->orWhereJsonContains('settings->ownerEmail', $q);
            });
        }
        
        // Filters
        if ($s = $validated['status'] ?? null)   $query->where('status', $s);
        if ($p = $validated['plan'] ?? null)     $query->where('settings->plan', $p);
        if ($from = $validated['from'] ?? null)  $query->where('created_at', '>=', $from);
        if ($to = $validated['to'] ?? null)      $query->where('created_at', '<=', $to);

        // Sort whitelist
        $allowedSort = ['name', 'created_at', 'updated_at', 'status'];
        $sort = $validated['sort'] ?? '-created_at';
        $dir = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $col = ltrim($sort, '-');
        abort_unless(in_array($col, $allowedSort), 422, 'Invalid sort');

        $query->orderBy($col, $dir);

        $perPage = min(max((int)($validated['per_page'] ?? 20), 1), 100);
        $page = (int)($validated['page'] ?? 1);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $payload = [
            'data' => $paginator->items(),
            'meta' => [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'generatedAt' => now()->toIso8601String()
            ]
        ];

        $etag = '"' . substr(hash('xxh3', json_encode([$col, $dir, $validated, $paginator->total()])), 0, 16) . '"';
        if ($request->header('If-None-Match') === $etag) {
            return response()->noContent(304)->header('ETag', $etag);
        }
        
        return response()->json($payload)
            ->header('ETag', $etag)
            ->header('Cache-Control', 'public, max-age=30, stale-while-revalidate=30');
    }
    
    public function store(TenantStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        $tenant = Tenant::create([
            'name' => $validated['name'],
            'domain' => $validated['domain'],
            'settings' => [
                'ownerName' => $validated['ownerName'],
                'ownerEmail' => $validated['ownerEmail'],
                'plan' => $validated['plan']
            ],
            'status' => 'active'
        ]);

        // Log audit
        Log::info('Tenant created', [
            'tenant_id' => $tenant->id,
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
            'x_request_id' => $request->header('X-Request-Id')
        ]);

        return response()->json(['data' => $tenant], 201);
    }
    
    public function show(string $id): JsonResponse
    {
        $tenant = Tenant::withCount(['users', 'projects'])->findOrFail($id);
        
        return response()->json(['data' => $tenant]);
    }
    
    public function update(TenantUpdateRequest $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $validated = $request->validated();
        
        $tenant->update($validated);

        // Log audit
        Log::info('Tenant updated', [
            'tenant_id' => $tenant->id,
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
            'x_request_id' => $request->header('X-Request-Id')
        ]);

        return response()->json(['data' => $tenant]);
    }
    
    public function destroy(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        
        // Log audit
        Log::info('Tenant deleted', [
            'tenant_id' => $tenant->id,
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
            'x_request_id' => request()->header('X-Request-Id')
        ]);
        
        $tenant->delete();

        return response()->json(['message' => 'Tenant deleted successfully']);
    }
}
