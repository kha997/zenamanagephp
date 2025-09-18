<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SidebarConfig;
use App\Services\SecurityGuardService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SidebarConfigController extends Controller
{
    protected SecurityGuardService $securityGuardService;

    public function __construct(SecurityGuardService $securityGuardService)
    {
        $this->securityGuardService = $securityGuardService;
    }
    /**
     * Display a listing of sidebar configs.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SidebarConfig::class);

        $query = SidebarConfig::query();

        // Filter by role if provided
        if ($request->has('role')) {
            $query->forRole($request->get('role'));
        }

        // Filter by tenant if provided
        if ($request->has('tenant_id')) {
            $query->forTenant($request->get('tenant_id'));
        }

        // Only enabled configs by default
        if ($request->get('include_disabled', false) !== true) {
            $query->enabled();
        }

        $configs = $query->with(['tenant', 'updater'])->get();

        return response()->json([
            'success' => true,
            'data' => $configs,
        ]);
    }

    /**
     * Store a newly created sidebar config.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', SidebarConfig::class);

        $validated = $request->validate([
            'role_name' => 'required|string|in:' . implode(',', SidebarConfig::VALID_ROLES),
            'config' => 'required|array',
            'config.items' => 'required|array',
            'tenant_id' => 'nullable|ulid|exists:tenants,id',
            'is_enabled' => 'boolean',
        ]);

        // Security validation
        $user = Auth::user();
        $securityErrors = $this->securityGuardService->validateSidebarConfig($validated['config'], $user);
        
        if (!empty($securityErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'Security validation failed',
                'errors' => $securityErrors,
            ], 422);
        }

        // Sanitize config
        $validated['config'] = $this->securityGuardService->sanitizeConfig($validated['config']);

        // Set updated_by to current user
        $validated['updated_by'] = Auth::id();

        $config = SidebarConfig::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Sidebar config created successfully',
            'data' => $config->load(['tenant', 'updater']),
        ], 201);
    }

    /**
     * Display the specified sidebar config.
     */
    public function show(SidebarConfig $sidebarConfig): JsonResponse
    {
        $this->authorize('view', $sidebarConfig);

        return response()->json([
            'success' => true,
            'data' => $sidebarConfig->load(['tenant', 'updater']),
        ]);
    }

    /**
     * Update the specified sidebar config.
     */
    public function update(Request $request, SidebarConfig $sidebarConfig): JsonResponse
    {
        $this->authorize('update', $sidebarConfig);

        $validated = $request->validate([
            'config' => 'required|array',
            'config.items' => 'required|array',
            'is_enabled' => 'boolean',
        ]);

        // Security validation
        $user = Auth::user();
        $securityErrors = $this->securityGuardService->validateSidebarConfig($validated['config'], $user);
        
        if (!empty($securityErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'Security validation failed',
                'errors' => $securityErrors,
            ], 422);
        }

        // Sanitize config
        $validated['config'] = $this->securityGuardService->sanitizeConfig($validated['config']);

        // Set updated_by to current user
        $validated['updated_by'] = Auth::id();

        $sidebarConfig->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Sidebar config updated successfully',
            'data' => $sidebarConfig->load(['tenant', 'updater']),
        ]);
    }

    /**
     * Remove the specified sidebar config.
     */
    public function destroy(SidebarConfig $sidebarConfig): JsonResponse
    {
        $this->authorize('delete', $sidebarConfig);

        $sidebarConfig->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sidebar config deleted successfully',
        ]);
    }

    /**
     * Get sidebar config for a specific role.
     */
    public function getForRole(Request $request, string $role): JsonResponse
    {
        $this->authorize('viewAny', SidebarConfig::class);

        $tenantId = $request->get('tenant_id');

        // Try to find tenant-specific config first
        $config = SidebarConfig::forRole($role)
            ->enabled()
            ->when($tenantId, function ($query) use ($tenantId) {
                return $query->forTenant($tenantId);
            }, function ($query) {
                return $query->global();
            })
            ->first();

        // If no config found, return default
        if (!$config) {
            $defaultConfig = SidebarConfig::getDefaultForRole($role);
            return response()->json([
                'success' => true,
                'data' => [
                    'role_name' => $role,
                    'config' => $defaultConfig,
                    'is_default' => true,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $config->load(['tenant', 'updater']),
        ]);
    }

    /**
     * Clone config from one role to another.
     */
    public function clone(Request $request): JsonResponse
    {
        $this->authorize('create', SidebarConfig::class);

        $validated = $request->validate([
            'from_role' => 'required|string|in:' . implode(',', SidebarConfig::VALID_ROLES),
            'to_role' => 'required|string|in:' . implode(',', SidebarConfig::VALID_ROLES),
            'tenant_id' => 'nullable|ulid|exists:tenants,id',
        ]);

        // Get source config
        $sourceConfig = SidebarConfig::forRole($validated['from_role'])
            ->enabled()
            ->when($validated['tenant_id'], function ($query) use ($validated) {
                return $query->forTenant($validated['tenant_id']);
            }, function ($query) {
                return $query->global();
            })
            ->first();

        if (!$sourceConfig) {
            return response()->json([
                'success' => false,
                'message' => 'Source config not found',
            ], 404);
        }

        // Check if target config already exists
        $existingConfig = SidebarConfig::forRole($validated['to_role'])
            ->when($validated['tenant_id'], function ($query) use ($validated) {
                return $query->forTenant($validated['tenant_id']);
            }, function ($query) {
                return $query->global();
            })
            ->first();

        if ($existingConfig) {
            return response()->json([
                'success' => false,
                'message' => 'Target config already exists',
            ], 409);
        }

        // Create new config
        $newConfig = SidebarConfig::create([
            'role_name' => $validated['to_role'],
            'config' => $sourceConfig->config,
            'tenant_id' => $validated['tenant_id'],
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Config cloned successfully',
            'data' => $newConfig->load(['tenant', 'updater']),
        ], 201);
    }

    /**
     * Reset config to default for a role.
     */
    public function reset(Request $request, string $role): JsonResponse
    {
        $this->authorize('update', SidebarConfig::class);

        $tenantId = $request->get('tenant_id');

        // Find existing config
        $config = SidebarConfig::forRole($role)
            ->when($tenantId, function ($query) use ($tenantId) {
                return $query->forTenant($tenantId);
            }, function ($query) {
                return $query->global();
            })
            ->first();

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Config not found',
            ], 404);
        }

        // Update with default config
        $defaultConfig = SidebarConfig::getDefaultForRole($role);
        $config->update([
            'config' => $defaultConfig,
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Config reset to default successfully',
            'data' => $config->load(['tenant', 'updater']),
        ]);
    }

    /**
     * Get default config for a role.
     */
    public function getDefault(string $role): JsonResponse
    {
        $this->authorize('viewAny', SidebarConfig::class);

        $defaultConfig = SidebarConfig::getDefaultForRole($role);

        return response()->json([
            'success' => true,
            'data' => [
                'role_name' => $role,
                'config' => $defaultConfig,
            ],
        ]);
    }
}
