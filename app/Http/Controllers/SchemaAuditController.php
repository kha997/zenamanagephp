<?php

namespace App\Http\Controllers;

use App\Services\SchemaAuditService;
use App\Services\StructuredLoggingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SchemaAuditController extends Controller
{
    /**
     * Get comprehensive schema audit
     */
    public function audit(): JsonResponse
    {
        try {
            $audit = SchemaAuditService::auditDocumentsAndHistory();
            
            // Log audit performance
            StructuredLoggingService::logEvent('schema_audit_performed', [
                'tables_audited' => 4,
                'issues_found' => count(array_merge(
                    $audit['documents_table']['issues'] ?? [],
                    $audit['document_versions_table']['issues'] ?? [],
                    $audit['project_activities_table']['issues'] ?? [],
                    $audit['audit_logs_table']['issues'] ?? []
                )),
                'recommendations_count' => count($audit['recommendations']),
            ]);
            
            return response()->json([
                'status' => 'success',
                'data' => $audit,
            ]);
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Schema audit failed', $e);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Schema audit failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get documents table audit
     */
    public function documents(): JsonResponse
    {
        try {
            $audit = SchemaAuditService::auditDocumentsAndHistory();
            
            return response()->json([
                'status' => 'success',
                'data' => $audit['documents_table'],
            ]);
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Documents table audit failed', $e);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Documents table audit failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get document versions table audit
     */
    public function documentVersions(): JsonResponse
    {
        try {
            $audit = SchemaAuditService::auditDocumentsAndHistory();
            
            return response()->json([
                'status' => 'success',
                'data' => $audit['document_versions_table'],
            ]);
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Document versions table audit failed', $e);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Document versions table audit failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get project activities table audit
     */
    public function projectActivities(): JsonResponse
    {
        try {
            $audit = SchemaAuditService::auditDocumentsAndHistory();
            
            return response()->json([
                'status' => 'success',
                'data' => $audit['project_activities_table'],
            ]);
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Project activities table audit failed', $e);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Project activities table audit failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get audit logs table audit
     */
    public function auditLogs(): JsonResponse
    {
        try {
            $audit = SchemaAuditService::auditDocumentsAndHistory();
            
            return response()->json([
                'status' => 'success',
                'data' => $audit['audit_logs_table'],
            ]);
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Audit logs table audit failed', $e);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Audit logs table audit failed',
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
            $audit = SchemaAuditService::auditDocumentsAndHistory();
            
            return response()->json([
                'status' => 'success',
                'data' => $audit['recommendations'],
            ]);
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Schema recommendations failed', $e);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Schema recommendations failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get performance analysis
     */
    public function performance(): JsonResponse
    {
        try {
            $audit = SchemaAuditService::auditDocumentsAndHistory();
            
            return response()->json([
                'status' => 'success',
                'data' => $audit['performance_analysis'],
            ]);
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Schema performance analysis failed', $e);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Schema performance analysis failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
