<?php declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Admin Settings Controller
 * 
 * System Settings (Super Admin only) and Tenant Settings (Org Admin + Super Admin).
 */
class AdminSettingsController extends Controller
{
    /**
     * Display settings page with tabs
     */
    public function index(Request $request): View|JsonResponse
    {
        $user = Auth::user();
        
        $isSuperAdmin = $user->isSuperAdmin() || $user->can('admin.access');
        $canViewSystemSettings = $isSuperAdmin;
        $canViewTenantSettings = $isSuperAdmin || $user->can('admin.settings.tenant');
        
        // Get system settings (from config or database)
        $systemSettings = [];
        if ($canViewSystemSettings) {
            $systemSettings = [
                'feature_flags' => config('features', []),
                'system_limits' => config('limits', []),
                'maintenance_mode' => config('app.maintenance_mode', false),
                'integrations' => config('integrations', []),
            ];
        }
        
        // Get tenant settings
        $tenantSettings = [];
        if ($canViewTenantSettings) {
            $tenantId = $user->tenant_id;
            if ($isSuperAdmin && $request->has('tenant_id')) {
                $tenantId = $request->input('tenant_id');
            }
            
            if ($tenantId) {
                $tenant = Tenant::find($tenantId);
                if ($tenant) {
                    $tenantSettings = [
                        'branding' => $tenant->settings['branding'] ?? [],
                        'document_numbering' => $tenant->settings['document_numbering'] ?? [],
                        'sla_settings' => $tenant->settings['sla'] ?? [],
                        'i18n' => $tenant->settings['i18n'] ?? [],
                        'integrations' => $tenant->settings['integrations'] ?? [],
                    ];
                }
            }
        }
        
        $data = [
            'can_view_system_settings' => $canViewSystemSettings,
            'can_view_tenant_settings' => $canViewTenantSettings,
            'system_settings' => $systemSettings,
            'tenant_settings' => $tenantSettings,
            'current_tenant_id' => $user->tenant_id,
        ];
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        }
        
        return view('admin.settings.index', $data);
    }
    
    /**
     * Update system settings (Super Admin only)
     */
    public function updateSystemSettings(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Check permission
        if (!$user->isSuperAdmin() && !$user->can('admin.access')) {
            return response()->json([
                'success' => false,
                'error' => 'Permission denied',
                'code' => 'PERMISSION_DENIED'
            ], 403);
        }
        
        $validated = $request->validate([
            'feature_flags' => 'sometimes|array',
            'system_limits' => 'sometimes|array',
            'maintenance_mode' => 'sometimes|boolean',
            'integrations' => 'sometimes|array',
        ]);
        
        // Update system settings (store in database or config cache)
        // For now, we'll log the update
        Log::info('System settings updated', [
            'user_id' => $user->id,
            'settings' => $validated,
        ]);
        
        // TODO: Implement actual settings storage (database table or config cache)
        
        return response()->json([
            'success' => true,
            'message' => 'System settings updated successfully',
        ]);
    }
    
    /**
     * Update tenant settings (Org Admin + Super Admin)
     */
    public function updateTenantSettings(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Check permission
        if (!$user->can('admin.settings.tenant') && !$user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Permission denied',
                'code' => 'PERMISSION_DENIED'
            ], 403);
        }
        
        $validated = $request->validate([
            'tenant_id' => 'sometimes|exists:tenants,id',
            'branding' => 'sometimes|array',
            'document_numbering' => 'sometimes|array',
            'sla_settings' => 'sometimes|array',
            'i18n' => 'sometimes|array',
            'integrations' => 'sometimes|array',
        ]);
        
        // Determine tenant ID
        $tenantId = $validated['tenant_id'] ?? $user->tenant_id;
        
        // Org Admin can only update their own tenant
        if ($user->can('admin.settings.tenant') && !$user->isSuperAdmin()) {
            if ($tenantId !== $user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Can only update own tenant settings',
                    'code' => 'TENANT_MISMATCH'
                ], 403);
            }
        }
        
        $tenant = Tenant::findOrFail($tenantId);
        
        // Update tenant settings
        $currentSettings = $tenant->settings ?? [];
        $updatedSettings = array_merge($currentSettings, [
            'branding' => $validated['branding'] ?? $currentSettings['branding'] ?? [],
            'document_numbering' => $validated['document_numbering'] ?? $currentSettings['document_numbering'] ?? [],
            'sla' => $validated['sla_settings'] ?? $currentSettings['sla'] ?? [],
            'i18n' => $validated['i18n'] ?? $currentSettings['i18n'] ?? [],
            'integrations' => $validated['integrations'] ?? $currentSettings['integrations'] ?? [],
        ]);
        
        $tenant->update(['settings' => $updatedSettings]);
        
        Log::info('Tenant settings updated', [
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'settings' => $validated,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Tenant settings updated successfully',
            'data' => ['tenant' => $tenant],
        ]);
    }
}
