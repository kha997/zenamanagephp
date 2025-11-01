<?php

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    protected $searchService;
    
    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }
    
    /**
     * Perform intelligent search
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'query' => 'required|string|max:255',
                'context' => 'string|in:all,projects,tasks,documents,users'
            ]);
            
            $query = $request->query;
            $context = $request->context ?? 'all';
            
            $results = $this->searchService->search($query, $context);
            
            return response()->json([
                'success' => true,
                'data' => $results
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'search_validation_' . uniqid(),
                    'code' => 'E422.VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $e->errors()
                ]
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'search_' . uniqid(),
                    'code' => 'E500.SEARCH_ERROR',
                    'message' => 'Search failed',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Get search suggestions
     */
    public function suggestions(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'q' => 'required|string|min:2|max:255'
            ]);
            
            $query = $request->q;
            $suggestions = $this->searchService->getSuggestions($query);
            
            return response()->json([
                'success' => true,
                'data' => $suggestions
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'suggestions_validation_' . uniqid(),
                    'code' => 'E422.VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $e->errors()
                ]
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'suggestions_' . uniqid(),
                    'code' => 'E500.SUGGESTIONS_ERROR',
                    'message' => 'Failed to get suggestions',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Get recent searches
     */
    public function recent(): JsonResponse
    {
        try {
            $recentSearches = $this->searchService->getRecentSearches();
            
            return response()->json([
                'success' => true,
                'data' => $recentSearches
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'recent_searches_' . uniqid(),
                    'code' => 'E500.RECENT_SEARCHES_ERROR',
                    'message' => 'Failed to get recent searches',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Save recent search
     */
    public function saveRecent(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'query' => 'required|string|max:255'
            ]);
            
            $this->searchService->saveRecentSearch($request->query);
            
            return response()->json([
                'success' => true,
                'message' => 'Search saved successfully'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'save_recent_validation_' . uniqid(),
                    'code' => 'E422.VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $e->errors()
                ]
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'save_recent_' . uniqid(),
                    'code' => 'E500.SAVE_RECENT_ERROR',
                    'message' => 'Failed to save recent search',
                    'details' => []
                ]
            ], 500);
        }
    }
}
