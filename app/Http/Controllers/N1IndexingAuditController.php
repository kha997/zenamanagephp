<?php

namespace App\Http\Controllers;

use App\Services\N1IndexingAuditService;
use App\Services\StructuredLoggingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class N1IndexingAuditController extends Controller
{
    /**
     * Get comprehensive N+1 and indexing audit
     */
    public function audit(): JsonResponse
    {
        try {
            $audit = N1IndexingAuditService::auditN1AndIndexing();
            
            // Log audit performance
            StructuredLoggingService::logEvent('n1_indexing_audit_performed', [
                'n1_patterns_analyzed' => count($audit['n1_analysis']),
                'tables_analyzed' => count($audit['indexing_analysis']),
                'recommendations_count' => count($audit['recommendations']),
            ]);
            
            return response()->json([
                'status' => 'success',
                'data' => $audit,
            ]);
        } catch (\Exception $e) {
            StructuredLoggingService::logError('N+1 and indexing audit failed', $e);
            
            return response()->json([
                'status' => 'error',
                'message' => 'N+1 and indexing audit failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get N+1 analysis
     */
    public function n1Analysis(): JsonResponse
    {
        try {
            $audit = N1IndexingAuditService::auditN1AndIndexing();
            
            return response()->json([
                'status' => 'success',
                'data' => $audit['n1_analysis'],
            ]);
        } catch (\Exception $e) {
            StructuredLoggingService::logError('N+1 analysis failed', $e);
            
            return response()->json([
                'status' => 'error',
                'message' => 'N+1 analysis failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get indexing analysis
     */
    public function indexingAnalysis(): JsonResponse
    {
        try {
            $audit = N1IndexingAuditService::auditN1AndIndexing();
            
            return response()->json([
                'status' => 'success',
                'data' => $audit['indexing_analysis'],
            ]);
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Indexing analysis failed', $e);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Indexing analysis failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get query performance analysis
     */
    public function queryPerformance(): JsonResponse
    {
        try {
            $audit = N1IndexingAuditService::auditN1AndIndexing();
            
            return response()->json([
                'status' => 'success',
                'data' => $audit['query_performance'],
            ]);
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Query performance analysis failed', $e);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Query performance analysis failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get recommendations
     */
    public function recommendations(): JsonResponse
    {
        try {
            $audit = N1IndexingAuditService::auditN1AndIndexing();
            
            return response()->json([
                'status' => 'success',
                'data' => $audit['recommendations'],
            ]);
        } catch (\Exception $e) {
            StructuredLoggingService::logError('N+1 and indexing recommendations failed', $e);
            
            return response()->json([
                'status' => 'error',
                'message' => 'N+1 and indexing recommendations failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get optimization plan
     */
    public function optimizationPlan(): JsonResponse
    {
        try {
            $audit = N1IndexingAuditService::auditN1AndIndexing();
            
            return response()->json([
                'status' => 'success',
                'data' => $audit['optimization_plan'],
            ]);
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Optimization plan failed', $e);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Optimization plan failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
