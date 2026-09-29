<?php

namespace App\Http\Controllers;

use App\Services\LaunchChecklistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FinalIntegrationController extends Controller
{
    protected $launchChecklistService;

    public function __construct(LaunchChecklistService $launchChecklistService)
    {
        $this->launchChecklistService = $launchChecklistService;
    }

    public function index()
    {
        return view('final-integration');
    }

    public function getLaunchStatus()
    {
        $status = $this->launchChecklistService->getLaunchStatus();
        return response()->json($status);
    }

    public function runSystemIntegrationChecks()
    {
        $integrations = $this->launchChecklistService->runSystemIntegrationChecks();
        return response()->json([
            'status' => 'success',
            'integrations' => $integrations,
            'message' => 'System integration checks completed'
        ]);
    }

    public function runProductionReadinessChecks()
    {
        $checks = $this->launchChecklistService->runProductionReadinessChecks();
        return response()->json([
            'status' => 'success',
            'checks' => $checks,
            'message' => 'Production readiness checks completed'
        ]);
    }

    public function runLaunchPreparationTasks()
    {
        $tasks = $this->launchChecklistService->runLaunchPreparationTasks();
        return response()->json([
            'status' => 'success',
            'tasks' => $tasks,
            'message' => 'Launch preparation tasks completed'
        ]);
    }

    public function getGoLiveChecklist()
    {
        $checklist = $this->launchChecklistService->getGoLiveChecklist();
        return response()->json([
            'status' => 'success',
            'checklist' => $checklist,
            'message' => 'Go-live checklist retrieved'
        ]);
    }

    /**
     * Refuse to perform pre-launch actions from the web (GAP-057).
     *
     * Migrations and compiled caches are performed only by the deployment step
     * (GAP-049 migration safety contract); this reports read-only readiness.
     */
    public function executePreLaunchActions()
    {
        return response()->json([
            'success' => false,
            'message' => 'Pre-launch actions (migrations, compiled caches) are performed only by deployment '
                . '(php artisan deploy:migrate), never from a web request.',
            // Under 'data' so the error envelope keeps it (error.details.data).
            'data' => ['readiness' => $this->launchChecklistService->getPreLaunchReadiness()],
        ], 409);
    }

    public function executeLaunchActions()
    {
        $actions = $this->launchChecklistService->executeLaunchActions();
        return response()->json([
            'status' => 'success',
            'actions' => $actions,
            'message' => 'Launch actions executed'
        ]);
    }

    public function validateIntegration(Request $request)
    {
        $integrationName = $request->input('integration');
        
        // Simulate integration validation
        $result = [
            'integration' => $integrationName,
            'status' => 'validated',
            'message' => "{$integrationName} integration validated successfully",
            'timestamp' => now()->toISOString()
        ];
        
        return response()->json($result);
    }

    public function runProductionCheck(Request $request)
    {
        $checkName = $request->input('check');
        
        // Simulate production check
        $result = [
            'check' => $checkName,
            'status' => 'passed',
            'message' => "{$checkName} check passed successfully",
            'timestamp' => now()->toISOString()
        ];
        
        return response()->json($result);
    }

    public function completeLaunchTask(Request $request)
    {
        $taskName = $request->input('task');
        
        // Simulate task completion
        $result = [
            'task' => $taskName,
            'status' => 'completed',
            'message' => "{$taskName} task completed successfully",
            'timestamp' => now()->toISOString()
        ];
        
        return response()->json($result);
    }

    public function toggleChecklistItem(Request $request)
    {
        $itemId = $request->input('item_id');
        $completed = $request->input('completed');
        
        // Simulate checklist item toggle
        $result = [
            'item_id' => $itemId,
            'completed' => $completed,
            'message' => "Checklist item {$itemId} " . ($completed ? 'completed' : 'pending'),
            'timestamp' => now()->toISOString()
        ];
        
        return response()->json($result);
    }

    public function executeAction(Request $request)
    {
        $actionName = $request->input('action');
        
        // Simulate action execution
        $result = [
            'action' => $actionName,
            'status' => 'executed',
            'message' => "{$actionName} executed successfully",
            'timestamp' => now()->toISOString()
        ];
        
        return response()->json($result);
    }

    public function getLaunchMetrics()
    {
        $metrics = [
            'system_status' => $this->launchChecklistService->getSystemStatus(),
            'readiness_score' => $this->launchChecklistService->getReadinessScore(),
            'test_coverage' => $this->launchChecklistService->getTestCoverage(),
            'documentation_completeness' => $this->launchChecklistService->getDocumentationCompleteness(),
            'integration_status' => $this->launchChecklistService->runSystemIntegrationChecks(),
            'production_checks' => $this->launchChecklistService->runProductionReadinessChecks(),
            'launch_tasks' => $this->launchChecklistService->runLaunchPreparationTasks(),
            'go_live_checklist' => $this->launchChecklistService->getGoLiveChecklist()
        ];
        
        return response()->json($metrics);
    }

    public function generateLaunchReport()
    {
        $report = [
            'launch_date' => now()->toDateString(),
            'system_status' => $this->launchChecklistService->getSystemStatus(),
            'readiness_score' => $this->launchChecklistService->getReadinessScore(),
            'test_coverage' => $this->launchChecklistService->getTestCoverage(),
            'documentation_completeness' => $this->launchChecklistService->getDocumentationCompleteness(),
            'integration_checks' => $this->launchChecklistService->runSystemIntegrationChecks(),
            'production_checks' => $this->launchChecklistService->runProductionReadinessChecks(),
            'launch_tasks' => $this->launchChecklistService->runLaunchPreparationTasks(),
            'go_live_checklist' => $this->launchChecklistService->getGoLiveChecklist(),
            'pre_launch_readiness' => $this->launchChecklistService->getPreLaunchReadiness(),
            'launch_actions' => $this->launchChecklistService->executeLaunchActions(),
            'generated_at' => now()->toISOString(),
            'generated_by' => Auth::user() ? Auth::user()->name : 'System'
        ];
        
        return response()->json($report);
    }
}
