<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Project;
use App\Models\Task;
use App\Models\Document;
use App\Models\User;
use App\Models\Team;
use App\Services\SearchService;

class SearchController extends Controller
{
    protected $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * Perform global search across all entities
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'query' => 'required|string|min:2|max:255',
                'context' => 'sometimes|string|in:all,projects,tasks,documents,team,users',
                'filters' => 'sometimes|array',
                'limit' => 'sometimes|integer|min:1|max:100'
            ]);

            $user = Auth::user();
            $tenantId = $user->tenant_id;
            $query = $request->input('query');
            $context = $request->input('context', 'all');
            $filters = $request->input('filters', []);
            $limit = $request->input('limit', 20);

            $results = $this->searchService->performSearch($tenantId, $query, $context, $filters, $limit);

            return response()->json([
                'success' => true,
                'data' => $results,
                'meta' => [
                    'query' => $query,
                    'context' => $context,
                    'total_results' => array_sum(array_map('count', $results)),
                    'timestamp' => now()->toISOString()
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'validation_error',
                    'message' => 'Invalid search parameters',
                    'details' => $e->errors()
                ]
            ], 422);

        } catch (\Exception $e) {
            \Log::error('Search error', [
                'user_id' => Auth::id(),
                'query' => $request->input('query'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'search_error',
                    'message' => 'Search failed. Please try again.'
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
                'query' => 'required|string|min:1|max:255',
                'context' => 'sometimes|string|in:all,projects,tasks,documents,team,users'
            ]);

            $user = Auth::user();
            $tenantId = $user->tenant_id;
            $query = $request->input('query');
            $context = $request->input('context', 'all');

            $suggestions = $this->searchService->getSuggestions($tenantId, $query, $context);

            return response()->json([
                'success' => true,
                'data' => $suggestions
            ]);

        } catch (\Exception $e) {
            \Log::error('Search suggestions error', [
                'user_id' => Auth::id(),
                'query' => $request->input('query'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'suggestions_error',
                    'message' => 'Failed to get suggestions'
                ]
            ], 500);
        }
    }

    /**
     * Get recent searches
     */
    public function recent(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $recentSearches = $this->searchService->getRecentSearches($user->id);

            return response()->json([
                'success' => true,
                'data' => $recentSearches
            ]);

        } catch (\Exception $e) {
            \Log::error('Recent searches error', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'recent_searches_error',
                    'message' => 'Failed to get recent searches'
                ]
            ], 500);
        }
    }

    /**
     * Save search query
     */
    public function save(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'query' => 'required|string|min:2|max:255',
                'context' => 'sometimes|string|in:all,projects,tasks,documents,team,users'
            ]);

            $user = Auth::user();
            $query = $request->input('query');
            $context = $request->input('context', 'all');

            $this->searchService->saveSearch($user->id, $query, $context);

            return response()->json([
                'success' => true,
                'message' => 'Search saved successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Save search error', [
                'user_id' => Auth::id(),
                'query' => $request->input('query'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'save_search_error',
                    'message' => 'Failed to save search'
                ]
            ], 500);
        }
    }
}
