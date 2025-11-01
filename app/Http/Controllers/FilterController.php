<?php

namespace App\Http\Controllers;

use App\Services\FilterService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FilterController extends Controller
{
    protected $filterService;
    
    public function __construct(FilterService $filterService)
    {
        $this->filterService = $filterService;
    }
    
    /**
     * Get filter presets
     */
    public function presets(): JsonResponse
    {
        try {
            $presets = $this->filterService->getFilterPresets();
            
            return response()->json([
                'success' => true,
                'data' => $presets
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'filter_presets_' . uniqid(),
                    'code' => 'E500.FILTER_PRESETS_ERROR',
                    'message' => 'Failed to get filter presets',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Get deep filters for context
     */
    public function deepFilters(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'context' => 'required|string|in:projects,tasks,documents,users,tenants'
            ]);
            
            $context = $request->context;
            $filters = $this->filterService->getDeepFilters($context);
            
            return response()->json([
                'success' => true,
                'data' => $filters
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'deep_filters_validation_' . uniqid(),
                    'code' => 'E422.VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $e->errors()
                ]
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'deep_filters_' . uniqid(),
                    'code' => 'E500.DEEP_FILTERS_ERROR',
                    'message' => 'Failed to get deep filters',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Get saved views
     */
    public function savedViews(): JsonResponse
    {
        try {
            $savedViews = $this->filterService->getSavedViews();
            
            return response()->json([
                'success' => true,
                'data' => $savedViews
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'saved_views_' . uniqid(),
                    'code' => 'E500.SAVED_VIEWS_ERROR',
                    'message' => 'Failed to get saved views',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Save a view
     */
    public function saveView(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:500',
                'filters' => 'required|array'
            ]);
            
            $viewData = [
                'name' => $request->name,
                'description' => $request->description,
                'filters' => $request->filters
            ];
            
            $success = $this->filterService->saveView($viewData);
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'View saved successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'id' => 'save_view_' . uniqid(),
                        'code' => 'E500.SAVE_VIEW_ERROR',
                        'message' => 'Failed to save view',
                        'details' => []
                    ]
                ], 500);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'save_view_validation_' . uniqid(),
                    'code' => 'E422.VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $e->errors()
                ]
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'save_view_' . uniqid(),
                    'code' => 'E500.SAVE_VIEW_ERROR',
                    'message' => 'Failed to save view',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Delete a saved view
     */
    public function deleteView(string $viewId): JsonResponse
    {
        try {
            $success = $this->filterService->deleteView($viewId);
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'View deleted successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'id' => 'delete_view_' . uniqid(),
                        'code' => 'E404.VIEW_NOT_FOUND',
                        'message' => 'View not found',
                        'details' => []
                    ]
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'delete_view_' . uniqid(),
                    'code' => 'E500.DELETE_VIEW_ERROR',
                    'message' => 'Failed to delete view',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Apply filters to data
     */
    public function applyFilters(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'data' => 'required|array',
                'filters' => 'required|array'
            ]);
            
            $data = $request->data;
            $filters = $request->filters;
            
            $filteredData = $this->filterService->applyFilters($data, $filters);
            
            return response()->json([
                'success' => true,
                'data' => $filteredData,
                'count' => count($filteredData)
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'apply_filters_validation_' . uniqid(),
                    'code' => 'E422.VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $e->errors()
                ]
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'apply_filters_' . uniqid(),
                    'code' => 'E500.APPLY_FILTERS_ERROR',
                    'message' => 'Failed to apply filters',
                    'details' => []
                ]
            ], 500);
        }
    }
}
