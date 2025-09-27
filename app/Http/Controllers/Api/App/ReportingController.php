<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\ReportingService;
use App\Services\ExportService;

class ReportingController extends Controller
{
    protected $reportingService;
    protected $exportService;

    public function __construct(ReportingService $reportingService, ExportService $exportService)
    {
        $this->reportingService = $reportingService;
        $this->exportService = $exportService;
    }

    /**
     * Get comprehensive analytics dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'period' => 'sometimes|string|in:7d,30d,90d,1y,custom',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from',
                'filters' => 'sometimes|array'
            ]);

            $user = Auth::user();
            $tenantId = $user->tenant_id;
            $period = $request->input('period', '30d');
            $filters = $request->input('filters', []);

            $analytics = $this->reportingService->getDashboardAnalytics($tenantId, $period, $filters);

            return response()->json([
                'success' => true,
                'data' => $analytics,
                'meta' => [
                    'period' => $period,
                    'generated_at' => now()->toISOString(),
                    'user_id' => $user->id
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'validation_error',
                    'message' => 'Invalid request parameters',
                    'details' => $e->errors()
                ]
            ], 422);

        } catch (\Exception $e) {
            \Log::error('Reporting dashboard error', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'reporting_error',
                    'message' => 'Failed to generate analytics dashboard'
                ]
            ], 500);
        }
    }

    /**
     * Get project analytics
     */
    public function projects(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'period' => 'sometimes|string|in:7d,30d,90d,1y,custom',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from',
                'project_id' => 'sometimes|integer|exists:projects,id',
                'filters' => 'sometimes|array'
            ]);

            $user = Auth::user();
            $tenantId = $user->tenant_id;
            $period = $request->input('period', '30d');
            $projectId = $request->input('project_id');
            $filters = $request->input('filters', []);

            $analytics = $this->reportingService->getProjectAnalytics($tenantId, $period, $projectId, $filters);

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            \Log::error('Project analytics error', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'project_analytics_error',
                    'message' => 'Failed to generate project analytics'
                ]
            ], 500);
        }
    }

    /**
     * Get task analytics
     */
    public function tasks(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'period' => 'sometimes|string|in:7d,30d,90d,1y,custom',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from',
                'project_id' => 'sometimes|integer|exists:projects,id',
                'assignee_id' => 'sometimes|integer|exists:users,id',
                'filters' => 'sometimes|array'
            ]);

            $user = Auth::user();
            $tenantId = $user->tenant_id;
            $period = $request->input('period', '30d');
            $filters = $request->input('filters', []);

            $analytics = $this->reportingService->getTaskAnalytics($tenantId, $period, $filters);

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            \Log::error('Task analytics error', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'task_analytics_error',
                    'message' => 'Failed to generate task analytics'
                ]
            ], 500);
        }
    }

    /**
     * Get team performance analytics
     */
    public function team(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'period' => 'sometimes|string|in:7d,30d,90d,1y,custom',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from',
                'user_id' => 'sometimes|integer|exists:users,id',
                'filters' => 'sometimes|array'
            ]);

            $user = Auth::user();
            $tenantId = $user->tenant_id;
            $period = $request->input('period', '30d');
            $filters = $request->input('filters', []);

            $analytics = $this->reportingService->getTeamAnalytics($tenantId, $period, $filters);

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            \Log::error('Team analytics error', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'team_analytics_error',
                    'message' => 'Failed to generate team analytics'
                ]
            ], 500);
        }
    }

    /**
     * Get financial analytics
     */
    public function financial(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'period' => 'sometimes|string|in:7d,30d,90d,1y,custom',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from',
                'project_id' => 'sometimes|integer|exists:projects,id',
                'filters' => 'sometimes|array'
            ]);

            $user = Auth::user();
            $tenantId = $user->tenant_id;
            $period = $request->input('period', '30d');
            $filters = $request->input('filters', []);

            $analytics = $this->reportingService->getFinancialAnalytics($tenantId, $period, $filters);

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            \Log::error('Financial analytics error', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'financial_analytics_error',
                    'message' => 'Failed to generate financial analytics'
                ]
            ], 500);
        }
    }

    /**
     * Export report in various formats
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'type' => 'required|string|in:dashboard,projects,tasks,team,financial',
                'format' => 'required|string|in:pdf,excel,csv,json',
                'period' => 'sometimes|string|in:7d,30d,90d,1y,custom',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from',
                'filters' => 'sometimes|array',
                'options' => 'sometimes|array'
            ]);

            $user = Auth::user();
            $tenantId = $user->tenant_id;
            $type = $request->input('type');
            $format = $request->input('format');
            $period = $request->input('period', '30d');
            $filters = $request->input('filters', []);
            $options = $request->input('options', []);

            $exportData = $this->reportingService->getExportData($tenantId, $type, $period, $filters);
            $filePath = $this->exportService->export($exportData, $format, $options);

            return response()->json([
                'success' => true,
                'data' => [
                    'download_url' => url('storage/exports/' . basename($filePath)),
                    'filename' => basename($filePath),
                    'size' => filesize($filePath),
                    'expires_at' => now()->addHours(24)->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Export error', [
                'user_id' => Auth::id(),
                'type' => $request->input('type'),
                'format' => $request->input('format'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'export_error',
                    'message' => 'Failed to export report'
                ]
            ], 500);
        }
    }

    /**
     * Get available report templates
     */
    public function templates(Request $request): JsonResponse
    {
        try {
            $templates = $this->reportingService->getReportTemplates();

            return response()->json([
                'success' => true,
                'data' => $templates
            ]);

        } catch (\Exception $e) {
            \Log::error('Report templates error', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'templates_error',
                    'message' => 'Failed to get report templates'
                ]
            ], 500);
        }
    }

    /**
     * Schedule automated reports
     */
    public function schedule(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'type' => 'required|string|in:dashboard,projects,tasks,team,financial',
                'format' => 'required|string|in:pdf,excel,csv',
                'frequency' => 'required|string|in:daily,weekly,monthly',
                'recipients' => 'required|array',
                'recipients.*' => 'email',
                'filters' => 'sometimes|array',
                'options' => 'sometimes|array'
            ]);

            $user = Auth::user();
            $tenantId = $user->tenant_id;

            $schedule = $this->reportingService->scheduleReport($tenantId, $request->all());

            return response()->json([
                'success' => true,
                'data' => $schedule
            ]);

        } catch (\Exception $e) {
            \Log::error('Schedule report error', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'schedule_error',
                    'message' => 'Failed to schedule report'
                ]
            ], 500);
        }
    }
}
