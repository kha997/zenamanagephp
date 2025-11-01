<?php

namespace App\Http\Controllers;

use App\Services\PerformanceOptimizationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PerformanceOptimizationController extends Controller
{
    protected $performanceService;

    public function __construct(PerformanceOptimizationService $performanceService)
    {
        $this->performanceService = $performanceService;
    }

    public function metrics(): JsonResponse
    {
        $metrics = $this->performanceService->getPerformanceMetrics();
        return response()->json($metrics);
    }

    public function analysis(): JsonResponse
    {
        $analysis = $this->performanceService->runPerformanceAnalysis();
        return response()->json($analysis);
    }

    public function optimizeDatabase(): JsonResponse
    {
        $results = $this->performanceService->optimizeDatabaseQueries();
        return response()->json([
            'status' => 'success',
            'results' => $results
        ]);
    }

    public function implementCaching(): JsonResponse
    {
        $results = $this->performanceService->implementCachingStrategy();
        return response()->json([
            'status' => 'success',
            'results' => $results
        ]);
    }

    public function optimizeApi(): JsonResponse
    {
        $results = $this->performanceService->optimizeApiResponses();
        return response()->json([
            'status' => 'success',
            'results' => $results
        ]);
    }

    public function optimizeAssets(): JsonResponse
    {
        $results = $this->performanceService->optimizeFrontendAssets();
        return response()->json([
            'status' => 'success',
            'results' => $results
        ]);
    }

    public function recommendations(): JsonResponse
    {
        $analysis = $this->performanceService->runPerformanceAnalysis();
        return response()->json($analysis['recommendations']);
    }
}
