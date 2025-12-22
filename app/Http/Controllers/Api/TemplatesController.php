<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TemplatesController extends Controller
{
    /**
     * Display a listing of templates
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $query = Template::where('tenant_id', $user->tenant_id);

            // Apply filters
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if ($request->filled('category')) {
                $query->where('category', $request->category);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('is_public')) {
                $query->where('is_public', $request->boolean('is_public'));
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            $templates = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $templates->items(),
                'meta' => [
                    'total' => $templates->total(),
                    'per_page' => $templates->perPage(),
                    'current_page' => $templates->currentPage(),
                    'last_page' => $templates->lastPage()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created template
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'category' => 'required|string|max:100',
                'template_data' => 'required|array',
                'is_public' => 'nullable|boolean',
                'tags' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $user = Auth::user();
            
            DB::beginTransaction();

            $template = Template::create([
                ...$request->validated(),
                'template_data' => json_encode($request->template_data),
                'tags' => $request->tags ? json_encode($request->tags) : null,
                'status' => 'draft',
                'version' => 1,
                'is_public' => $request->boolean('is_public', false),
                'usage_count' => 0,
                'tenant_id' => $user->tenant_id,
                'created_by' => $user->id,
                'updated_by' => $user->id
            ]);

            DB::commit();

            Log::info('Template created via API', [
                'template_id' => $template->id,
                'name' => $template->name,
                'tenant_id' => $template->tenant_id,
                'created_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $template,
                'message' => 'Template created successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Template creation failed via API', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'created_by' => Auth::id()
            ]);

            return $this->errorResponse('Failed to create template', 500);
        }
    }

    /**
     * Display the specified template
     */
    public function show(Template $template): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify tenant isolation or public access
            if ($template->tenant_id !== $user->tenant_id && !$template->is_public) {
                return $this->errorResponse('Access denied: Template belongs to different tenant', 403);
            }

            $template->template_data = json_decode($template->template_data, true);
            $template->tags = json_decode($template->tags, true);

            return response()->json([
                'success' => true,
                'data' => $template
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Update the specified template
     */
    public function update(Request $request, Template $template): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify tenant isolation
            if ($template->tenant_id !== $user->tenant_id) {
                return $this->errorResponse('Access denied: Template belongs to different tenant', 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'category' => 'sometimes|required|string|max:100',
                'template_data' => 'sometimes|required|array',
                'is_public' => 'nullable|boolean',
                'tags' => 'nullable|array',
                'status' => 'nullable|in:draft,published,archived'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            DB::beginTransaction();

            $updateData = $request->validated();
            if (isset($updateData['template_data'])) {
                $updateData['template_data'] = json_encode($updateData['template_data']);
            }
            if (isset($updateData['tags'])) {
                $updateData['tags'] = json_encode($updateData['tags']);
            }
            $updateData['updated_by'] = $user->id;

            $template->update($updateData);

            DB::commit();

            Log::info('Template updated via API', [
                'template_id' => $template->id,
                'name' => $template->name,
                'tenant_id' => $template->tenant_id,
                'updated_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $template,
                'message' => 'Template updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Template update failed via API', [
                'error' => $e->getMessage(),
                'template_id' => $template->id,
                'data' => $request->all(),
                'updated_by' => Auth::id()
            ]);

            return $this->errorResponse('Failed to update template', 500);
        }
    }

    /**
     * Remove the specified template
     */
    public function destroy(Template $template): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify tenant isolation
            if ($template->tenant_id !== $user->tenant_id) {
                return $this->errorResponse('Access denied: Template belongs to different tenant', 403);
            }

            DB::beginTransaction();

            $templateName = $template->name;
            $template->delete();

            DB::commit();

            Log::info('Template deleted via API', [
                'template_id' => $template->id,
                'name' => $templateName,
                'tenant_id' => $template->tenant_id,
                'deleted_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Template deletion failed via API', [
                'error' => $e->getMessage(),
                'template_id' => $template->id,
                'deleted_by' => Auth::id()
            ]);

            return $this->errorResponse('Failed to delete template', 500);
        }
    }

    /**
     * Get template library (public templates)
     */
    public function library(Request $request): JsonResponse
    {
        try {
            $query = Template::where('is_public', true)
                           ->where('status', 'published');

            // Apply filters
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if ($request->filled('category')) {
                $query->where('category', $request->category);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'usage_count');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            $templates = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $templates->items(),
                'meta' => [
                    'total' => $templates->total(),
                    'per_page' => $templates->perPage(),
                    'current_page' => $templates->currentPage(),
                    'last_page' => $templates->lastPage()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Get template builder data
     */
    public function builder(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Get user's templates for builder
            $templates = Template::where('tenant_id', $user->tenant_id)
                               ->where('status', 'draft')
                               ->latest()
                               ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'templates' => $templates,
                    'categories' => Template::where('tenant_id', $user->tenant_id)
                                          ->distinct()
                                          ->pluck('category')
                ]
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Standardized error response with error envelope
     */
    private function errorResponse(string $message, int $status = 500, $errors = null): JsonResponse
    {
        $errorId = uniqid('err_', true);
        
        $response = [
            'success' => false,
            'error' => [
                'id' => $errorId,
                'message' => $message,
                'status' => $status,
                'timestamp' => now()->toISOString()
            ]
        ];

        if ($errors) {
            $response['error']['details'] = $errors;
        }

        return response()->json($response, $status);
    }
}
