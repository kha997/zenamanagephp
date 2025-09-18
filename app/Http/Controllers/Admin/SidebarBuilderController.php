<?php declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SidebarConfig;
use App\Services\PresetService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Http;

class SidebarBuilderController extends Controller
{
    protected PresetService $presetService;

    public function __construct(PresetService $presetService)
    {
        $this->presetService = $presetService;
    }
    /**
     * Display the sidebar builder interface.
     */
    public function index(): View
    {
        $this->authorize('manage', SidebarConfig::class);

        // Get all available roles
        $roles = SidebarConfig::VALID_ROLES;
        
        // Get existing configs
        $configs = SidebarConfig::with(['tenant', 'updater'])
            ->enabled()
            ->orderBy('role_name')
            ->get();

        // Group configs by role_name safely
        $groupedConfigs = [];
        foreach ($configs as $config) {
            $groupedConfigs[$config->role_name][] = $config;
        }

        return view('admin.sidebar-builder', compact('roles', 'configs', 'groupedConfigs'));
    }

    /**
     * Show sidebar builder for a specific role.
     */
    public function show(string $role): View
    {
        $this->authorize('manage', SidebarConfig::class);

        // Get config for role (or default)
        $config = SidebarConfig::forRole($role)->global()->first();
        
        if (!$config) {
            // Use default config
            $configData = SidebarConfig::getDefaultForRole($role);
            $config = (object) [
                'role_name' => $role,
                'config' => $configData,
                'is_default' => true,
            ];
        }

        return view('admin.sidebar-builder-edit', compact('role', 'config'));
    }

    /**
     * Preview sidebar for a specific role.
     */
    public function preview(string $role): View
    {
        $this->authorize('viewAny', SidebarConfig::class);

        // Get config for role (or default)
        $config = SidebarConfig::forRole($role)->global()->first();
        
        if (!$config) {
            $configData = SidebarConfig::getDefaultForRole($role);
        } else {
            $configData = $config->config;
        }

        return view('admin.sidebar-preview', compact('role', 'configData'));
    }

    /**
     * Clone config from one role to another.
     */
    public function clone(Request $request): JsonResponse
    {
        $this->authorize('manage', SidebarConfig::class);

        $validated = $request->validate([
            'from_role' => 'required|string|in:' . implode(',', SidebarConfig::VALID_ROLES),
            'to_role' => 'required|string|in:' . implode(',', SidebarConfig::VALID_ROLES),
            'tenant_id' => 'nullable|ulid|exists:tenants,id',
        ]);

        try {
            // Make API call to clone endpoint
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post(url('/api/admin/sidebar-configs/clone'), $validated);

            if ($response->successful()) {
                $data = $response->json();
                
                return response()->json([
                    'success' => true,
                    'message' => "Configuration cloned from {$validated['from_role']} to {$validated['to_role']} successfully",
                    'data' => $data['data'] ?? null,
                ]);
            } else {
                $errorData = $response->json();
                
                return response()->json([
                    'success' => false,
                    'message' => $errorData['message'] ?? 'Failed to clone configuration',
                ], $response->status());
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while cloning configuration: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset config to default for a role.
     */
    public function reset(Request $request, string $role): JsonResponse
    {
        $this->authorize('manage', SidebarConfig::class);

        $validated = $request->validate([
            'tenant_id' => 'nullable|ulid|exists:tenants,id',
        ]);

        try {
            // Make API call to reset endpoint
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->put(url("/api/admin/sidebar-configs/role/{$role}/reset"), $validated);

            if ($response->successful()) {
                $data = $response->json();
                
                return response()->json([
                    'success' => true,
                    'message' => "Configuration reset to default for {$role} successfully",
                    'data' => $data['data'] ?? null,
                ]);
            } else {
                $errorData = $response->json();
                
                return response()->json([
                    'success' => false,
                    'message' => $errorData['message'] ?? 'Failed to reset configuration',
                ], $response->status());
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while resetting configuration: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export configuration for a role.
     */
    public function export(string $role): JsonResponse
    {
        $this->authorize('viewAny', SidebarConfig::class);

        try {
            // Get config for role
            $config = SidebarConfig::forRole($role)->global()->first();
            
            if (!$config) {
                $configData = SidebarConfig::getDefaultForRole($role);
                $isDefault = true;
            } else {
                $configData = $config->config;
                $isDefault = false;
            }

            $exportData = [
                'role_name' => $role,
                'config' => $configData,
                'is_default' => $isDefault,
                'exported_at' => now()->toISOString(),
                'version' => '1.0',
            ];

            return response()->json([
                'success' => true,
                'data' => $exportData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while exporting configuration: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import configuration for a role.
     */
    public function import(Request $request, string $role): JsonResponse
    {
        $this->authorize('manage', SidebarConfig::class);

        $validated = $request->validate([
            'config' => 'required|array',
            'config.items' => 'required|array',
            'overwrite' => 'boolean',
        ]);

        try {
            // Check if config already exists
            $existingConfig = SidebarConfig::forRole($role)->global()->first();
            
            if ($existingConfig && !($validated['overwrite'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration already exists. Use overwrite=true to replace it.',
                ], 409);
            }

            // Create or update config
            if ($existingConfig) {
                $existingConfig->update([
                    'config' => $validated['config'],
                    'updated_by' => auth()->id(),
                ]);
                $config = $existingConfig;
            } else {
                $config = SidebarConfig::create([
                    'role_name' => $role,
                    'config' => $validated['config'],
                    'tenant_id' => null,
                    'is_enabled' => true,
                    'version' => 1,
                    'updated_by' => auth()->id(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => "Configuration imported successfully for {$role}",
                'data' => $config->load(['tenant', 'updater']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while importing configuration: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available presets.
     */
    public function getPresets(): JsonResponse
    {
        $this->authorize('viewAny', SidebarConfig::class);

        $presets = $this->presetService->getAvailablePresets();

        return response()->json([
            'success' => true,
            'data' => $presets,
        ]);
    }

    /**
     * Apply a preset to a role.
     */
    public function applyPreset(Request $request, string $role): JsonResponse
    {
        $this->authorize('manage', SidebarConfig::class);

        $validated = $request->validate([
            'preset_name' => 'required|string|in:' . implode(',', array_keys(PresetService::PRESETS)),
            'tenant_id' => 'nullable|ulid|exists:tenants,id',
        ]);

        try {
            $result = $this->presetService->applyPreset(
                $validated['preset_name'],
                $role,
                $validated['tenant_id'] ?? null
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while applying preset: ' . $e->getMessage(),
            ], 500);
        }
    }
}
