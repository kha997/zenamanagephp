<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Component;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ComponentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $query = Component::with([
                'project:id,name,status',
                'parent:id,name,type',
                'children:id,name,type,status',
                'createdBy:id,name,email',
                'tenant:id,name'
            ]);

            // Apply tenant filter
            if ($user->tenant_id) {
                $query->where('tenant_id', $user->tenant_id);
            }

            // Apply filters
            if ($request->filled('project_id')) {
                $query->where('project_id', $request->input('project_id'));
            }

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->filled('type')) {
                $query->where('type', $request->input('type'));
            }

            if ($request->filled('parent_id')) {
                $query->where('parent_id', $request->input('parent_id'));
            }

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Pagination
            $perPage = min($request->input('per_page', 15), 100);
            $components = $query->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $components,
                'message' => 'Components retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Component index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve components',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:zena_projects,id',
            'parent_id' => 'nullable|exists:zena_components,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|string|max:100',
            'status' => 'required|in:planning,active,completed,on_hold,cancelled',
            'priority' => 'required|in:low,medium,high,urgent',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'estimated_cost' => 'nullable|numeric|min:0',
            'actual_cost' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        try {
            $component = ZenaComponent::create([
                'project_id' => $request->input('project_id'),
                'parent_id' => $request->input('parent_id'),
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'type' => $request->input('type'),
                'status' => $request->input('status'),
                'priority' => $request->input('priority'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'estimated_cost' => $request->input('estimated_cost'),
                'actual_cost' => $request->input('actual_cost'),
                'created_by' => $user->id,
            ]);

            return $this->successResponse($component->load(['project', 'parent', 'children', 'createdBy']), 'Component created successfully', 201);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create component: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $component = ZenaComponent::with(['project', 'parent', 'children', 'createdBy', 'tasks'])
            ->find($id);

        if (!$component) {
            return $this->errorResponse('Component not found', 404);
        }

        return $this->successResponse($component);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $component = ZenaComponent::find($id);

        if (!$component) {
            return $this->errorResponse('Component not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|required|string|max:100',
            'status' => 'sometimes|required|in:planning,active,completed,on_hold,cancelled',
            'priority' => 'sometimes|required|in:low,medium,high,urgent',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'estimated_cost' => 'nullable|numeric|min:0',
            'actual_cost' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        try {
            $component->update($request->only([
                'name', 'description', 'type', 'status', 'priority', 'start_date', 'end_date',
                'estimated_cost', 'actual_cost'
            ]));

            return $this->successResponse($component->load(['project', 'parent', 'children', 'createdBy']), 'Component updated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update component: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $component = ZenaComponent::find($id);

        if (!$component) {
            return $this->errorResponse('Component not found', 404);
        }

        // Check if component has children
        if ($component->children()->exists()) {
            return $this->errorResponse('Cannot delete component with child components', 400);
        }

        // Check if component has tasks
        if ($component->tasks()->exists()) {
            return $this->errorResponse('Cannot delete component with tasks', 400);
        }

        try {
            $component->delete();

            return $this->successResponse(null, 'Component deleted successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete component: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get component hierarchy
     */
    public function getHierarchy(string $id): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $component = ZenaComponent::with(['children.children.children'])->find($id);

        if (!$component) {
            return $this->errorResponse('Component not found', 404);
        }

        return $this->successResponse($component);
    }

    /**
     * Get root components for a project
     */
    public function getRootComponents(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:zena_projects,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $components = ZenaComponent::with(['children.children'])
            ->where('project_id', $request->input('project_id'))
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return $this->successResponse($components);
    }
}
