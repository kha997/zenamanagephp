<?php declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TemplateSet;
use App\Services\TemplateImportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Config;

/**
 * TemplateSetController
 * 
 * Admin controller for managing WBS template sets.
 * Handles CRUD operations and import functionality.
 */
class TemplateSetController extends Controller
{
    public function __construct(
        private TemplateImportService $importService
    ) {}

    /**
     * Display a listing of template sets
     */
    public function index(Request $request): View|JsonResponse
    {
        // Check feature flag
        if (!Config::get('features.tasks.enable_wbs_templates', false)) {
            abort(403, 'Task Templates feature is not enabled');
        }

        $this->authorize('viewAny', TemplateSet::class);

        $user = Auth::user();
        
        // Apply tenant scoping based on admin access level
        $query = TemplateSet::query();
        
        // Super Admin sees all (including global templates)
        if ($user->isSuperAdmin() || $user->can('admin.access')) {
            // No filter - see all templates
        } elseif ($user->can('admin.access.tenant')) {
            // Org Admin sees tenant-specific + global templates
            $query->where(function ($q) use ($user) {
                $q->where('tenant_id', $user->tenant_id)
                  ->orWhereNull('tenant_id'); // Global templates
            });
        } else {
            // No access
            abort(403, 'Access denied');
        }

        // Apply filters
        if ($version = $request->input('version')) {
            $query->where('version', $version);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        } else {
            $query->active();
        }

        if ($hasPresets = $request->input('has_presets')) {
            if ($hasPresets === 'true') {
                $query->has('presets');
            } else {
                $query->doesntHave('presets');
            }
        }

        $templateSets = $query->with(['phases', 'disciplines', 'presets'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        if ($request->wantsJson()) {
            return response()->json($templateSets);
        }

        return view('admin.templates.index', compact('templateSets'));
    }

    /**
     * Display the specified template set
     */
    public function show(Request $request, TemplateSet $set): View|JsonResponse
    {
        $this->authorize('view', $set);

        $set->load(['phases.tasks', 'disciplines.tasks', 'tasks.dependencies', 'presets']);

        if ($request->wantsJson()) {
            return response()->json($set);
        }

        return view('admin.templates.show', compact('set'));
    }

    /**
     * Store a newly created template set
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', TemplateSet::class);

        $validated = $request->validate([
            'code' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'version' => 'required|string|max:50',
            'is_active' => 'boolean',
            'tenant_id' => 'nullable|string|exists:tenants,id',
            'metadata' => 'nullable|array',
        ]);

        $user = Auth::user();
        
        // Global templates (tenant_id = null) can only be created by Super Admin
        if (empty($validated['tenant_id'])) {
            if (!$user->isSuperAdmin() && !$user->can('admin.access')) {
                return $request->wantsJson()
                    ? response()->json(['error' => 'Only Super Admin can create global templates'], 403)
                    : redirect()->back()->withErrors(['tenant_id' => 'Only Super Admin can create global templates']);
            }
            
            // Validate uniqueness for global templates
            $existing = TemplateSet::whereNull('tenant_id')
                ->where('code', $validated['code'])
                ->exists();
            
            if ($existing) {
                return $request->wantsJson()
                    ? response()->json(['error' => 'Global template with this code already exists'], 409)
                    : redirect()->back()->withErrors(['code' => 'Global template with this code already exists']);
            }
        } else {
            // Org Admin can only create templates for their own tenant
            if ($user->can('admin.access.tenant') && !$user->isSuperAdmin()) {
                if ($validated['tenant_id'] !== $user->tenant_id) {
                    return $request->wantsJson()
                        ? response()->json(['error' => 'Can only create templates for own tenant'], 403)
                        : redirect()->back()->withErrors(['tenant_id' => 'Can only create templates for own tenant']);
                }
            }
        }

        $validated['created_by'] = Auth::id();
        $templateSet = TemplateSet::create($validated);

        Log::info('Template set created', [
            'set_id' => $templateSet->id,
            'code' => $templateSet->code,
            'user_id' => Auth::id(),
        ]);

        if ($request->wantsJson()) {
            return response()->json($templateSet, 201);
        }

        return redirect()->route('admin.templates.show', $templateSet)
            ->with('success', 'Template set created successfully');
    }

    /**
     * Update the specified template set
     */
    public function update(Request $request, TemplateSet $set): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $set);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'version' => 'sometimes|string|max:50',
            'is_active' => 'boolean',
            'metadata' => 'nullable|array',
        ]);

        $set->update($validated);

        Log::info('Template set updated', [
            'set_id' => $set->id,
            'user_id' => Auth::id(),
        ]);

        if ($request->wantsJson()) {
            return response()->json($set);
        }

        return redirect()->route('admin.templates.show', $set)
            ->with('success', 'Template set updated successfully');
    }

    /**
     * Remove the specified template set
     */
    public function destroy(Request $request, TemplateSet $set): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $set);

        $set->delete();

        Log::info('Template set deleted', [
            'set_id' => $set->id,
            'user_id' => Auth::id(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Template set deleted successfully']);
        }

        return redirect()->route('admin.templates.index')
            ->with('success', 'Template set deleted successfully');
    }

    /**
     * Show import form
     */
    public function importForm()
    {
        $this->authorize('import', TemplateSet::class);

        if (!Config::get('features.tasks.enable_wbs_templates', false)) {
            abort(403, 'Task Templates feature is not enabled');
        }

        return view('admin.templates.import');
    }

    /**
     * Import template set from file
     */
    public function import(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('import', TemplateSet::class);

        if (!Config::get('features.tasks.enable_wbs_templates', false)) {
            abort(403, 'Task Templates feature is not enabled');
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,json|max:10240',
            'tenant_id' => 'nullable|string|exists:tenants,id',
        ]);

        try {
            $file = $request->file('file');
            $tenantId = $request->input('tenant_id');
            $user = Auth::user();

            $templateSet = $this->importService->importFromFile($file, $user, $tenantId);

            Log::info('Template set imported', [
                'set_id' => $templateSet->id,
                'file_name' => $file->getClientOriginalName(),
                'user_id' => $user->id,
            ]);

            if ($request->wantsJson()) {
                return response()->json($templateSet, 201);
            }

            return redirect()->route('admin.templates.show', $templateSet)
                ->with('success', 'Template set imported successfully');
        } catch (\Exception $e) {
            Log::error('Template import failed', [
                'error' => $e->getMessage(),
                'file_name' => $request->file('file')?->getClientOriginalName(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return redirect()->back()
                ->withErrors(['file' => $e->getMessage()])
                ->withInput();
        }
    }
}
