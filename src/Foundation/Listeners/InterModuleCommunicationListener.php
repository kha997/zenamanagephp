<?php declare(strict_types=1);

namespace Src\Foundation\Listeners;

use Illuminate\Support\Facades\Log;
use Src\CoreProject\Events\ComponentProgressUpdated;
use Src\CoreProject\Events\ProjectCreated;
use Src\ChangeRequest\Events\ChangeRequestApproved;

/**
 * Listener xử lý communication giữa các modules
 * Đảm bảo loose coupling và data consistency
 */
class InterModuleCommunicationListener
{
    /**
     * Handle incoming events và route tới method tương ứng
     */
    public function handle($event): void
    {
        $eventClass = get_class($event);
        
        switch ($eventClass) {
            case 'Src\\CoreProject\\Events\\ProjectCreated':
                $this->handleProjectCreated($event);
                break;
                
            case 'Src\\CoreProject\\Events\\ComponentProgressUpdated':
                $this->handleComponentProgressUpdated($event);
                break;
                
            case 'Src\\ChangeRequest\\Events\\ChangeRequestApproved':
                $this->handleChangeRequestApproved($event);
                break;
                
            default:
                Log::warning('InterModuleCommunicationListener: Unhandled event type', [
                    'event_class' => $eventClass
                ]);
        }
    }

    /**
     * Xử lý khi project được tạo
     * Trigger các modules khác setup initial data
     */
    public function handleProjectCreated(ProjectCreated $event): void
    {
        try {
            // Tạo baseline mặc định cho project
            $this->createDefaultBaseline($event);
            
            // Setup default notification rules cho project owner
            $this->setupDefaultNotificationRules($event);
            
            // Initialize document structure
            $this->initializeDocumentStructure($event);
            
        } catch (\Exception $e) {
            Log::error('Failed to handle project creation inter-module communication', [
                'project_id' => $event->projectId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Xử lý khi component progress được update
     * Trigger recalculation ở các modules liên quan
     */
    public function handleComponentProgressUpdated(ComponentProgressUpdated $event): void
    {
        try {
            // Update project rollup calculations
            $this->updateProjectRollup($event);
            
            // Check và update baseline variance
            $this->updateBaselineVariance($event);
            
            // Trigger KPI recalculation
            $this->recalculateProjectKPIs($event);
            
        } catch (\Exception $e) {
            Log::error('Failed to handle component progress inter-module communication', [
                'component_id' => $event->componentId,
                'project_id' => $event->projectId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Xử lý khi change request được approve
     * Apply changes tới các modules liên quan
     */
    public function handleChangeRequestApproved(ChangeRequestApproved $event): void
    {
        try {
            // Apply schedule changes
            if ($event->impactDays > 0) {
                $this->applyScheduleChanges($event);
            }
            
            // Apply cost changes
            if ($event->impactCost != 0) {
                $this->applyCostChanges($event);
            }
            
            // Update KPIs based on impact
            if (!empty($event->impactKpi)) {
                $this->applyKpiChanges($event);
            }
            
            // Create new baseline if significant impact
            if ($this->isSignificantImpact($event)) {
                $this->createImpactBaseline($event);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to handle change request approval inter-module communication', [
                'change_request_id' => $event->changeRequestId,
                'project_id' => $event->projectId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Tạo baseline mặc định cho project mới
     */
    private function createDefaultBaseline($event): void
    {
        // Logic tạo baseline - sẽ được implement trong BaselineService
        Log::info('Creating default baseline for new project', [
            'project_id' => $event->projectId
        ]);
    }

    /**
     * Setup notification rules mặc định
     */
    private function setupDefaultNotificationRules($event): void
    {
        // Logic setup notification rules - sẽ được implement trong NotificationService
        Log::info('Setting up default notification rules', [
            'project_id' => $event->projectId,
            'owner_id' => $event->ownerId
        ]);
    }

    /**
     * Initialize document structure cho project
     */
    private function initializeDocumentStructure($event): void
    {
        // Logic initialize documents - sẽ được implement trong DocumentService
        Log::info('Initializing document structure', [
            'project_id' => $event->projectId
        ]);
    }

    /**
     * Update project rollup calculations
     */
    private function updateProjectRollup($event): void
    {
        // Logic update rollup - sẽ được implement trong ProjectService
        Log::info('Updating project rollup calculations', [
            'project_id' => $event->projectId,
            'component_id' => $event->componentId
        ]);
    }

    /**
     * Update baseline variance
     */
    private function updateBaselineVariance($event): void
    {
        // Logic update variance - sẽ được implement trong BaselineService
        Log::info('Updating baseline variance', [
            'project_id' => $event->projectId,
            'component_id' => $event->componentId
        ]);
    }

    /**
     * Recalculate project KPIs
     */
    private function recalculateProjectKPIs($event): void
    {
        // Logic recalculate KPIs - sẽ được implement trong KPIService
        Log::info('Recalculating project KPIs', [
            'project_id' => $event->projectId
        ]);
    }

    /**
     * Apply schedule changes từ change request
     */
    private function applyScheduleChanges($event): void
    {
        // Logic apply schedule changes - sẽ được implement trong ScheduleService
        Log::info('Applying schedule changes from change request', [
            'change_request_id' => $event->changeRequestId,
            'impact_days' => $event->impactDays
        ]);
    }

    /**
     * Apply cost changes từ change request
     */
    private function applyCostChanges($event): void
    {
        // Logic apply cost changes - sẽ được implement trong CostService
        Log::info('Applying cost changes from change request', [
            'change_request_id' => $event->changeRequestId,
            'impact_cost' => $event->impactCost
        ]);
    }

    /**
     * Apply KPI changes từ change request
     */
    private function applyKpiChanges($event): void
    {
        // Logic apply KPI changes - sẽ được implement trong KPIService
        Log::info('Applying KPI changes from change request', [
            'change_request_id' => $event->changeRequestId,
            'impact_kpi' => $event->impactKpi
        ]);
    }

    /**
     * Kiểm tra impact có significant không
     */
    private function isSignificantImpact($event): bool
    {
        // Logic kiểm tra significant impact
        return $event->impactDays > 7 || abs($event->impactCost) > 10000;
    }

    /**
     * Tạo baseline mới sau impact
     */
    private function createImpactBaseline($event): void
    {
        // Logic tạo impact baseline - sẽ được implement trong BaselineService
        Log::info('Creating impact baseline', [
            'change_request_id' => $event->changeRequestId,
            'project_id' => $event->projectId
        ]);
    }
}