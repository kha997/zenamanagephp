<?php

namespace App\Http\Controllers;

use App\Services\AnalysisService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AnalysisController extends Controller
{
    protected $analysisService;
    
    public function __construct(AnalysisService $analysisService)
    {
        $this->analysisService = $analysisService;
    }
    
    /**
     * Get analysis data
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'context' => 'required|string|in:projects,tasks,documents,users,tenants,overview',
                'filters' => 'nullable|array'
            ]);
            
            $context = $request->context;
            $filters = $request->filters ?? [];
            
            $analysisData = $this->analysisService->getAnalysis($context, $filters);
            
            return response()->json([
                'success' => true,
                'data' => $analysisData
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'analysis_validation_' . uniqid(),
                    'code' => 'E422.VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $e->errors()
                ]
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'analysis_' . uniqid(),
                    'code' => 'E500.ANALYSIS_ERROR',
                    'message' => 'Failed to get analysis data',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Get analysis for specific context
     */
    public function context(string $context, Request $request): JsonResponse
    {
        try {
            $request->validate([
                'filters' => 'nullable|array'
            ]);
            
            $filters = $request->filters ?? [];
            $analysisData = $this->analysisService->getAnalysis($context, $filters);
            
            return response()->json([
                'success' => true,
                'data' => $analysisData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'analysis_context_' . uniqid(),
                    'code' => 'E500.ANALYSIS_CONTEXT_ERROR',
                    'message' => 'Failed to get analysis for context',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Get analysis metrics
     */
    public function metrics(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'context' => 'required|string|in:projects,tasks,documents,users,tenants,overview',
                'filters' => 'nullable|array'
            ]);
            
            $context = $request->context;
            $filters = $request->filters ?? [];
            
            $analysisData = $this->analysisService->getAnalysis($context, $filters);
            $metrics = $analysisData['metrics'] ?? [];
            
            return response()->json([
                'success' => true,
                'data' => $metrics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'analysis_metrics_' . uniqid(),
                    'code' => 'E500.ANALYSIS_METRICS_ERROR',
                    'message' => 'Failed to get analysis metrics',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Get analysis charts
     */
    public function charts(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'context' => 'required|string|in:projects,tasks,documents,users,tenants,overview',
                'filters' => 'nullable|array'
            ]);
            
            $context = $request->context;
            $filters = $request->filters ?? [];
            
            $analysisData = $this->analysisService->getAnalysis($context, $filters);
            $charts = $analysisData['charts'] ?? [];
            
            return response()->json([
                'success' => true,
                'data' => $charts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'analysis_charts_' . uniqid(),
                    'code' => 'E500.ANALYSIS_CHARTS_ERROR',
                    'message' => 'Failed to get analysis charts',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Get analysis insights
     */
    public function insights(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'context' => 'required|string|in:projects,tasks,documents,users,tenants,overview',
                'filters' => 'nullable|array'
            ]);
            
            $context = $request->context;
            $filters = $request->filters ?? [];
            
            $analysisData = $this->analysisService->getAnalysis($context, $filters);
            $insights = $analysisData['insights'] ?? [];
            
            return response()->json([
                'success' => true,
                'data' => $insights
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'analysis_insights_' . uniqid(),
                    'code' => 'E500.ANALYSIS_INSIGHTS_ERROR',
                    'message' => 'Failed to get analysis insights',
                    'details' => []
                ]
            ], 500);
        }
    }
}
