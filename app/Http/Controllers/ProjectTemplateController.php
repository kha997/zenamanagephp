<?php

namespace App\Http\Controllers;

use App\Models\ProjectTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProjectTemplateController extends Controller
{
    /**
     * Get all templates
     */
    public function index(): JsonResponse
    {
        $templates = ProjectTemplate::active()
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'templates' => $templates
        ]);
    }

    /**
     * Get templates by category
     */
    public function getByCategory(string $category): JsonResponse
    {
        $templates = ProjectTemplate::active()
            ->byCategory($category)
            ->get();

        return response()->json([
            'success' => true,
            'templates' => $templates
        ]);
    }

    /**
     * Get template details
     */
    public function show(ProjectTemplate $template): JsonResponse
    {
        return response()->json([
            'success' => true,
            'template' => $template
        ]);
    }

    /**
     * Apply template to project
     */
    public function apply(Request $request): JsonResponse
    {
        $request->validate([
            'template_id' => 'required|exists:project_templates,id',
            'selected_phases' => 'array',
            'project_id' => 'required|integer'
        ]);

        $template = ProjectTemplate::findOrFail($request->template_id);
        $selectedPhases = $request->selected_phases;

        $appliedData = $template->applyToProject($request->project_id, $selectedPhases);

        return response()->json([
            'success' => true,
            'message' => 'Template applied successfully',
            'data' => $appliedData
        ]);
    }

    /**
     * Create new template
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|in:residential,commercial,industrial,mixed-use,custom',
            'description' => 'nullable|string',
            'phases' => 'required|array',
            'default_settings' => 'nullable|array'
        ]);

        $template = ProjectTemplate::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Template created successfully',
            'template' => $template
        ]);
    }

    /**
     * Update template
     */
    public function update(Request $request, ProjectTemplate $template): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'category' => 'sometimes|string|in:residential,commercial,industrial,mixed-use,custom',
            'description' => 'nullable|string',
            'phases' => 'sometimes|array',
            'default_settings' => 'nullable|array'
        ]);

        $template->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Template updated successfully',
            'template' => $template
        ]);
    }

    /**
     * Delete template
     */
    public function destroy(ProjectTemplate $template): JsonResponse
    {
        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template deleted successfully'
        ]);
    }
}
