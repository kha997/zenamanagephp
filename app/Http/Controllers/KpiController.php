<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;


use App\Services\KpiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class KpiController extends Controller
{
    protected $kpiService;
    
    public function __construct(KpiService $kpiService)
    {
        $this->kpiService = $kpiService;
    }
    
    /**
     * Get KPI cards for the current user/tenant
     */
    public function index(): JsonResponse
    {
        try {
            $kpiCards = $this->kpiService->getKPICards();
            
            return response()->json([
                'success' => true,
                'data' => $kpiCards
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'kpi_' . uniqid(),
                    'code' => 'E500.KPI_FETCH_ERROR',
                    'message' => 'Failed to fetch KPI data',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Get user's KPI preferences
     */
    public function preferences(): JsonResponse
    {
        try {
            $preferences = $this->kpiService->getUserKPIPreferences();
            
            return response()->json([
                'success' => true,
                'data' => $preferences
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'kpi_prefs_' . uniqid(),
                    'code' => 'E500.KPI_PREFS_ERROR',
                    'message' => 'Failed to fetch KPI preferences',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Save user's KPI preferences
     */
    public function savePreferences(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'kpiRows' => 'required|integer|min:1|max:2',
                'visibleCards' => 'required|array',
                'visibleCards.*' => 'integer|min:1|max:8'
            ]);
            
            $preferences = [
                'kpiRows' => $request->kpiRows,
                'visibleCards' => $request->visibleCards
            ];
            
            $this->kpiService->saveUserKPIPreferences($preferences);
            
            return response()->json([
                'success' => true,
                'message' => 'KPI preferences saved successfully'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'kpi_prefs_validation_' . uniqid(),
                    'code' => 'E422.VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $e->errors()
                ]
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'kpi_prefs_save_' . uniqid(),
                    'code' => 'E500.KPI_PREFS_SAVE_ERROR',
                    'message' => 'Failed to save KPI preferences',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Refresh KPI data
     */
    public function refresh(): JsonResponse
    {
        try {
            // Clear cache to force refresh
            $tenantId = Auth::user()->tenant_id ?? 'default';
            $cacheKey = "kpi_cards_{$tenantId}";
            cache()->forget($cacheKey);
            
            $kpiCards = $this->kpiService->getKPICards();
            
            return response()->json([
                'success' => true,
                'data' => $kpiCards,
                'message' => 'KPI data refreshed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'kpi_refresh_' . uniqid(),
                    'code' => 'E500.KPI_REFRESH_ERROR',
                    'message' => 'Failed to refresh KPI data',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Get KPI statistics
     */
    public function stats(): JsonResponse
    {
        try {
            $kpiCards = $this->kpiService->getKPICards();
            
            $stats = [
                'total_cards' => count($kpiCards),
                'visible_cards' => count(array_filter($kpiCards, fn($card) => $card['visible'])),
                'hidden_cards' => count(array_filter($kpiCards, fn($card) => !$card['visible'])),
                'last_updated' => now()->toISOString()
            ];
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'kpi_stats_' . uniqid(),
                    'code' => 'E500.KPI_STATS_ERROR',
                    'message' => 'Failed to fetch KPI statistics',
                    'details' => []
                ]
            ], 500);
        }
    }
}
