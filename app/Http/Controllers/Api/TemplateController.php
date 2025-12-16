<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesTenantContext;
use App\Models\Project;
use App\Models\TemplateSet;
use App\Services\TemplateApplyService;
use App\Http\Requests\TemplatePreviewRequest;
use App\Http\Requests\TemplateApplyRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

/**
 * TemplateController
 * 
 * API controller for WBS template sets.
 * Handles listing, preview, apply, and history operations.
 */
class TemplateController extends Controller
{
    use ResolvesTenantContext;
    public function __construct(
        private TemplateApplyService $applyService
    ) {}

    /**
     * List available template sets
     * 
     * Returns tenant-specific and global template sets with phases, disciplines, and presets.
     */
    public function index(Request $request): JsonResponse
    {
        // Check feature flag
        if (!Config::get('features.tasks.enable_wbs_templates', false)) {
            return ApiResponse::error('Task Templates feature is not enabled', 403);
        }

        try {
            $user = Auth::user();
            if (!$user) {
                return ApiResponse::error('User not authenticated', 401);
            }

            $tenantId = $this->resolveActiveTenantIdFromRequest($request);
            if (!$tenantId) {
                return ApiResponse::error('Tenant not found', 404);
            }
            
            // Get template sets accessible to user (tenant-specific + global)
            $templateSets = TemplateSet::forTenantOrGlobal($tenantId)
                ->active()
                ->with(['phases', 'disciplines', 'presets'])
                ->get()
                ->map(function ($set) {
                    return [
                        'id' => $set->id,
                        'code' => $set->code,
                        'name' => $set->name,
                        'description' => $set->description,
                        'version' => $set->version,
                        'is_global' => $set->is_global,
                        'phases' => $set->phases->map(fn($p) => [
                            'id' => $p->id,
                            'code' => $p->code,
                            'name' => $p->name,
                            'order_index' => $p->order_index,
                        ]),
                        'disciplines' => $set->disciplines->map(fn($d) => [
                            'id' => $d->id,
                            'code' => $d->code,
                            'name' => $d->name,
                            'color_hex' => $d->color_hex,
                            'order_index' => $d->order_index,
                        ]),
                        'presets' => $set->presets->map(fn($p) => [
                            'id' => $p->id,
                            'code' => $p->code,
                            'name' => $p->name,
                            'description' => $p->description,
                        ]),
                    ];
                });

            return ApiResponse::success($templateSets, 'Template sets retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Failed to list template sets', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return ApiResponse::error('Failed to retrieve template sets', 500);
        }
    }

    /**
     * Preview template application
     * 
     * Returns statistics about what would be created if the template is applied.
     */
    public function preview(TemplatePreviewRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            $set = TemplateSet::findOrFail($validated['set_id']);
            $project = Project::findOrFail($validated['project_id']);

            // Check authorization
            $this->authorize('apply', $set);
            $this->authorize('view', $project);

            $preview = $this->applyService->preview(
                $project,
                $set,
                $validated['preset_code'] ?? null,
                $validated['selections'] ?? [],
                $validated['options'] ?? []
            );

            return ApiResponse::success($preview, 'Preview generated successfully');
        } catch (\Exception $e) {
            Log::error('Template preview failed', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return ApiResponse::error('Failed to generate preview: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Apply template to project
     * 
     * Creates tasks, dependencies, and mappings based on the template.
     */
    public function apply(TemplateApplyRequest $request, Project $project): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            $set = TemplateSet::findOrFail($validated['set_id']);

            // Check authorization
            $this->authorize('apply', $set);
            $this->authorize('update', $project);

            $results = $this->applyService->apply(
                $project,
                $set,
                Auth::user(),
                $validated['preset_code'] ?? null,
                $validated['selections'] ?? [],
                $validated['options'] ?? []
            );

            return ApiResponse::success($results, 'Template applied successfully');
        } catch (\Exception $e) {
            Log::error('Template application failed', [
                'error' => $e->getMessage(),
                'project_id' => $project->id,
                'set_id' => $validated['set_id'] ?? null,
            ]);

            return ApiResponse::error('Failed to apply template: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get template application history for a project
     * 
     * Returns list of template applications with details.
     */
    public function history(Project $project): JsonResponse
    {
        try {
            // Check authorization
            $this->authorize('view', $project);

            $history = $project->templateApplyLogs()
                ->with(['set', 'executor'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'set' => [
                            'id' => $log->set->id,
                            'code' => $log->set->code,
                            'name' => $log->set->name,
                        ],
                        'preset_code' => $log->preset_code,
                        'selections' => $log->selections,
                        'counts' => $log->counts,
                        'executor' => [
                            'id' => $log->executor->id,
                            'name' => $log->executor->name,
                            'email' => $log->executor->email,
                        ],
                        'duration_ms' => $log->duration_ms,
                        'created_at' => $log->created_at->toISOString(),
                    ];
                });

            return ApiResponse::success($history, 'History retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Failed to retrieve template history', [
                'error' => $e->getMessage(),
                'project_id' => $project->id,
            ]);

            return ApiResponse::error('Failed to retrieve history', 500);
        }
    }
}

