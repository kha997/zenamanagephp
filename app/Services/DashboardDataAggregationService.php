<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\ChangeRequest;
use App\Models\DashboardMetric;
use App\Models\DashboardMetricValue;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Dashboard Data Aggregation Service
 * 
 * Service tổng hợp dữ liệu cho dashboard theo từng role
 */
class DashboardDataAggregationService
{
    /**
     * Lấy dữ liệu tổng quan cho System Admin
     */
    public function getSystemAdminData(User $user): array
    {
        return [
            'total_users' => $this->getTotalUsers($user->tenant_id),
            'active_projects' => $this->getActiveProjects($user->tenant_id),
            'system_load' => $this->getSystemLoad(),
            'alerts_count' => $this->getSystemAlertsCount($user->tenant_id),
            'user_growth' => $this->getUserGrowthData($user->tenant_id),
            'project_completion_rate' => $this->getProjectCompletionRate($user->tenant_id),
            'system_performance' => $this->getSystemPerformanceMetrics()
        ];
    }

    /**
     * Lấy dữ liệu tổng quan cho Project Manager
     */
    public function getProjectManagerData(User $user, ?string $projectId = null): array
    {
        $projects = $projectId ? [$projectId] : $this->getUserProjects($user->id);
        
        return [
            'total_tasks' => $this->getTotalTasks($projects),
            'completed_tasks' => $this->getCompletedTasks($projects),
            'budget_used' => $this->getBudgetUsed($projects),
            'timeline_status' => $this->getTimelineStatus($projects),
            'project_progress' => $this->getProjectProgressData($projects),
            'budget_vs_actual' => $this->getBudgetVsActualData($projects),
            'task_completion_status' => $this->getTaskCompletionStatus($projects),
            'team_productivity' => $this->getTeamProductivity($projects),
            'quality_metrics' => $this->getQualityMetrics($projects),
            'risk_level' => $this->getRiskLevel($projects)
        ];
    }

    /**
     * Lấy dữ liệu tổng quan cho Design Lead
     */
    public function getDesignLeadData(User $user, ?string $projectId = null): array
    {
        $projects = $projectId ? [$projectId] : $this->getUserProjects($user->id);
        
        return [
            'total_drawings' => $this->getTotalDrawings($projects),
            'pending_rfis' => $this->getPendingRFIs($projects),
            'submittals_count' => $this->getSubmittalsCount($projects),
            'reviews_pending' => $this->getReviewsPending($projects),
            'drawing_release_schedule' => $this->getDrawingReleaseSchedule($projects),
            'rfi_response_time' => $this->getRFIResponseTime($projects),
            'submittal_approval_status' => $this->getSubmittalApprovalStatus($projects),
            'design_quality_score' => $this->getDesignQualityScore($projects),
            'revision_frequency' => $this->getRevisionFrequency($projects),
            'client_satisfaction' => $this->getClientSatisfaction($projects)
        ];
    }

    /**
     * Lấy dữ liệu tổng quan cho Site Engineer
     */
    public function getSiteEngineerData(User $user, ?string $projectId = null): array
    {
        $projects = $projectId ? [$projectId] : $this->getUserProjects($user->id);
        
        return [
            'daily_reports' => $this->getDailyReportsCount($projects),
            'photos_uploaded' => $this->getPhotosUploadedCount($projects),
            'inspections_completed' => $this->getInspectionsCompleted($projects),
            'weather_status' => $this->getWeatherStatus($projects),
            'daily_progress' => $this->getDailyProgressData($projects),
            'photo_gallery' => $this->getLatestSitePhotos($projects),
            'weather_impact' => $this->getWeatherImpactAnalysis($projects),
            'manpower_utilization' => $this->getManpowerUtilization($projects),
            'safety_incident_rate' => $this->getSafetyIncidentRate($projects),
            'quality_score' => $this->getSiteQualityScore($projects)
        ];
    }

    /**
     * Lấy dữ liệu tổng quan cho QC Inspector
     */
    public function getQCInspectorData(User $user, ?string $projectId = null): array
    {
        $projects = $projectId ? [$projectId] : $this->getUserProjects($user->id);
        
        return [
            'total_inspections' => $this->getTotalInspections($projects),
            'ncrs_count' => $this->getNCRsCount($projects),
            'observations_count' => $this->getObservationsCount($projects),
            'quality_score' => $this->getOverallQualityScore($projects),
            'quality_trend' => $this->getQualityTrendData($projects),
            'ncr_status_distribution' => $this->getNCRStatusDistribution($projects),
            'inspection_results' => $this->getInspectionResultsSummary($projects),
            'corrective_action_effectiveness' => $this->getCorrectiveActionEffectiveness($projects),
            'compliance_rate' => $this->getComplianceRate($projects),
            'quality_trend_analysis' => $this->getQualityTrendAnalysis($projects)
        ];
    }

    /**
     * Lấy dữ liệu tổng quan cho Client Rep
     */
    public function getClientRepData(User $user, ?string $projectId = null): array
    {
        $projects = $projectId ? [$projectId] : $this->getUserProjects($user->id);
        
        return [
            'pending_crs' => $this->getPendingCRs($projects),
            'approvals_required' => $this->getApprovalsRequired($projects),
            'budget_status' => $this->getBudgetStatus($projects),
            'timeline_status' => $this->getClientTimelineStatus($projects),
            'project_progress_summary' => $this->getProjectProgressSummary($projects),
            'budget_vs_actual_chart' => $this->getBudgetVsActualChart($projects),
            'timeline_milestone_status' => $this->getTimelineMilestoneStatus($projects),
            'communication_effectiveness' => $this->getCommunicationEffectiveness($projects),
            'decision_time' => $this->getDecisionTime($projects),
            'satisfaction_score' => $this->getClientSatisfactionScore($projects)
        ];
    }

    /**
     * Lấy dữ liệu tổng quan cho Subcontractor Lead
     */
    public function getSubcontractorLeadData(User $user, ?string $projectId = null): array
    {
        $projects = $projectId ? [$projectId] : $this->getUserProjects($user->id);
        
        return [
            'tasks_assigned' => $this->getTasksAssigned($projects, $user->id),
            'materials_submitted' => $this->getMaterialsSubmitted($projects, $user->id),
            'progress_updates' => $this->getProgressUpdates($projects, $user->id),
            'quality_score' => $this->getSubcontractorQualityScore($projects, $user->id),
            'task_completion_chart' => $this->getTaskCompletionChart($projects, $user->id),
            'material_submission_status' => $this->getMaterialSubmissionStatus($projects, $user->id),
            'quality_performance_trend' => $this->getQualityPerformanceTrend($projects, $user->id),
            'resource_utilization' => $this->getResourceUtilization($projects, $user->id),
            'communication_effectiveness' => $this->getSubcontractorCommunication($projects, $user->id),
            'performance_rating' => $this->getPerformanceRating($projects, $user->id)
        ];
    }

    // ==================== HELPER METHODS ====================

    /**
     * Lấy tổng số users
     */
    private function getTotalUsers(string $tenantId): int
    {
        return DB::table('users')
            ->where('tenant_id', $tenantId)
            ->count();
    }

    /**
     * Lấy số dự án đang hoạt động
     */
    private function getActiveProjects(string $tenantId): int
    {
        return DB::table('projects')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'in_progress'])
            ->count();
    }

    /**
     * Lấy system load
     */
    private function getSystemLoad(): float
    {
        // Mock data - trong thực tế sẽ lấy từ system monitoring
        return rand(60, 90) / 100;
    }

    /**
     * Lấy số alerts hệ thống
     */
    private function getSystemAlertsCount(string $tenantId): int
    {
        return DB::table('dashboard_alerts')
            ->where('tenant_id', $tenantId)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Lấy dữ liệu tăng trưởng user
     */
    private function getUserGrowthData(string $tenantId): array
    {
        $data = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $count = DB::table('users')
                ->where('tenant_id', $tenantId)
                ->where('created_at', '<=', $date->endOfMonth())
                ->count();
            
            $data[] = [
                'month' => $date->format('Y-m'),
                'count' => $count
            ];
        }
        
        return $data;
    }

    /**
     * Lấy tỷ lệ hoàn thành dự án
     */
    private function getProjectCompletionRate(string $tenantId): float
    {
        $total = DB::table('projects')
            ->where('tenant_id', $tenantId)
            ->count();
            
        $completed = DB::table('projects')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->count();
            
        return $total > 0 ? ($completed / $total) * 100 : 0;
    }

    /**
     * Lấy metrics hiệu suất hệ thống
     */
    private function getSystemPerformanceMetrics(): array
    {
        return [
            'response_time' => rand(100, 300), // ms
            'uptime' => 99.9,
            'error_rate' => 0.1,
            'throughput' => rand(1000, 5000) // requests/min
        ];
    }

    /**
     * Lấy danh sách dự án của user
     */
    private function getUserProjects(string $userId): array
    {
        return DB::table('project_user_roles')
            ->where('user_id', $userId)
            ->pluck('project_id')
            ->toArray();
    }

    /**
     * Lấy tổng số tasks
     */
    private function getTotalTasks(array $projectIds): int
    {
        if (empty($projectIds)) return 0;
        
        return DB::table('tasks')
            ->whereIn('project_id', $projectIds)
            ->count();
    }

    /**
     * Lấy số tasks đã hoàn thành
     */
    private function getCompletedTasks(array $projectIds): int
    {
        if (empty($projectIds)) return 0;
        
        return DB::table('tasks')
            ->whereIn('project_id', $projectIds)
            ->where('status', 'completed')
            ->count();
    }

    /**
     * Lấy ngân sách đã sử dụng
     */
    private function getBudgetUsed(array $projectIds): float
    {
        if (empty($projectIds)) return 0;
        
        $totalBudget = DB::table('projects')
            ->whereIn('id', $projectIds)
            ->sum('budget');
            
        $actualCost = DB::table('projects')
            ->whereIn('id', $projectIds)
            ->sum('actual_cost');
            
        return $totalBudget > 0 ? ($actualCost / $totalBudget) * 100 : 0;
    }

    /**
     * Lấy trạng thái timeline
     */
    private function getTimelineStatus(array $projectIds): string
    {
        if (empty($projectIds)) return 'unknown';
        
        $overdueCount = DB::table('projects')
            ->whereIn('id', $projectIds)
            ->where('end_date', '<', now())
            ->where('status', '!=', 'completed')
            ->count();
            
        if ($overdueCount > 0) return 'overdue';
        
        $nearDeadlineCount = DB::table('projects')
            ->whereIn('id', $projectIds)
            ->where('end_date', '<=', now()->addDays(7))
            ->where('status', '!=', 'completed')
            ->count();
            
        return $nearDeadlineCount > 0 ? 'at_risk' : 'on_track';
    }

    /**
     * Lấy dữ liệu tiến độ dự án
     */
    private function getProjectProgressData(array $projectIds): array
    {
        if (empty($projectIds)) return [];
        
        return DB::table('projects')
            ->whereIn('id', $projectIds)
            ->select('id', 'name', 'progress_percentage', 'status')
            ->get()
            ->toArray();
    }

    /**
     * Lấy dữ liệu ngân sách vs thực tế
     */
    private function getBudgetVsActualData(array $projectIds): array
    {
        if (empty($projectIds)) return [];
        
        return DB::table('projects')
            ->whereIn('id', $projectIds)
            ->select('id', 'name', 'budget', 'actual_cost')
            ->get()
            ->toArray();
    }

    /**
     * Lấy trạng thái hoàn thành tasks
     */
    private function getTaskCompletionStatus(array $projectIds): array
    {
        if (empty($projectIds)) return [];
        
        return DB::table('tasks')
            ->whereIn('project_id', $projectIds)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->toArray();
    }

    /**
     * Lấy năng suất team
     */
    private function getTeamProductivity(array $projectIds): array
    {
        // Mock data - trong thực tế sẽ tính từ task completion rate
        return [
            'average_completion_time' => rand(2, 5), // days
            'tasks_per_day' => rand(5, 15),
            'quality_score' => rand(80, 95)
        ];
    }

    /**
     * Lấy metrics chất lượng
     */
    private function getQualityMetrics(array $projectIds): array
    {
        // Mock data
        return [
            'defect_rate' => rand(1, 5) / 100,
            'rework_percentage' => rand(5, 15),
            'client_satisfaction' => rand(80, 95)
        ];
    }

    /**
     * Lấy mức độ rủi ro
     */
    private function getRiskLevel(array $projectIds): string
    {
        // Mock logic
        $riskFactors = rand(0, 10);
        
        if ($riskFactors <= 3) return 'low';
        if ($riskFactors <= 7) return 'medium';
        return 'high';
    }

    // ==================== DESIGN LEAD METHODS ====================

    /**
     * Lấy tổng số bản vẽ
     */
    private function getTotalDrawings(array $projectIds): int
    {
        // Mock data - trong thực tế sẽ query từ documents table
        return rand(20, 50);
    }

    /**
     * Lấy số RFI đang chờ
     */
    private function getPendingRFIs(array $projectIds): int
    {
        // Mock data
        return rand(5, 15);
    }

    /**
     * Lấy số submittals
     */
    private function getSubmittalsCount(array $projectIds): int
    {
        // Mock data
        return rand(8, 20);
    }

    /**
     * Lấy số reviews đang chờ
     */
    private function getReviewsPending(array $projectIds): int
    {
        // Mock data
        return rand(3, 8);
    }

    /**
     * Lấy lịch phát hành bản vẽ
     */
    private function getDrawingReleaseSchedule(array $projectIds): array
    {
        // Mock data
        $data = [];
        for ($i = 0; $i < 7; $i++) {
            $data[] = [
                'date' => Carbon::now()->addDays($i)->format('Y-m-d'),
                'drawings' => rand(1, 5)
            ];
        }
        return $data;
    }

    /**
     * Lấy thời gian phản hồi RFI
     */
    private function getRFIResponseTime(array $projectIds): array
    {
        // Mock data
        return [
            'average_hours' => rand(24, 72),
            'sla_compliance' => rand(80, 95)
        ];
    }

    /**
     * Lấy trạng thái phê duyệt submittal
     */
    private function getSubmittalApprovalStatus(array $projectIds): array
    {
        // Mock data
        return [
            'pending' => rand(2, 5),
            'approved' => rand(10, 15),
            'rejected' => rand(1, 3)
        ];
    }

    /**
     * Lấy điểm chất lượng thiết kế
     */
    private function getDesignQualityScore(array $projectIds): float
    {
        return rand(85, 95);
    }

    /**
     * Lấy tần suất revision
     */
    private function getRevisionFrequency(array $projectIds): array
    {
        // Mock data
        return [
            'average_per_drawing' => rand(1, 3),
            'trend' => 'decreasing'
        ];
    }

    /**
     * Lấy mức độ hài lòng khách hàng
     */
    private function getClientSatisfaction(array $projectIds): float
    {
        return rand(80, 95);
    }

    // ==================== SITE ENGINEER METHODS ====================

    /**
     * Lấy số báo cáo hàng ngày
     */
    private function getDailyReportsCount(array $projectIds): int
    {
        // Mock data
        return rand(10, 20);
    }

    /**
     * Lấy số ảnh đã upload
     */
    private function getPhotosUploadedCount(array $projectIds): int
    {
        // Mock data
        return rand(20, 50);
    }

    /**
     * Lấy số nghiệm thu đã hoàn thành
     */
    private function getInspectionsCompleted(array $projectIds): int
    {
        // Mock data
        return rand(5, 15);
    }

    /**
     * Lấy trạng thái thời tiết
     */
    private function getWeatherStatus(array $projectIds): string
    {
        $weathers = ['sunny', 'cloudy', 'rainy', 'stormy'];
        return $weathers[array_rand($weathers)];
    }

    /**
     * Lấy dữ liệu tiến độ hàng ngày
     */
    private function getDailyProgressData(array $projectIds): array
    {
        // Mock data
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $data[] = [
                'date' => Carbon::now()->subDays($i)->format('Y-m-d'),
                'progress' => rand(10, 30)
            ];
        }
        return $data;
    }

    /**
     * Lấy ảnh hiện trường mới nhất
     */
    private function getLatestSitePhotos(array $projectIds): array
    {
        // Mock data
        return [
            [
                'id' => '1',
                'url' => '/images/site-photo-1.jpg',
                'caption' => 'Foundation work progress',
                'uploaded_at' => Carbon::now()->subHours(2)->toISOString()
            ],
            [
                'id' => '2',
                'url' => '/images/site-photo-2.jpg',
                'caption' => 'Steel structure installation',
                'uploaded_at' => Carbon::now()->subHours(4)->toISOString()
            ]
        ];
    }

    /**
     * Lấy phân tích tác động thời tiết
     */
    private function getWeatherImpactAnalysis(array $projectIds): array
    {
        // Mock data
        return [
            'rain_delay_days' => rand(0, 3),
            'productivity_impact' => rand(-20, 0), // percentage
            'weather_forecast' => 'sunny_next_3_days'
        ];
    }

    /**
     * Lấy tỷ lệ sử dụng nhân lực
     */
    private function getManpowerUtilization(array $projectIds): float
    {
        return rand(70, 95);
    }

    /**
     * Lấy tỷ lệ tai nạn an toàn
     */
    private function getSafetyIncidentRate(array $projectIds): float
    {
        return rand(0, 5) / 100;
    }

    /**
     * Lấy điểm chất lượng hiện trường
     */
    private function getSiteQualityScore(array $projectIds): float
    {
        return rand(80, 95);
    }

    // ==================== QC INSPECTOR METHODS ====================

    /**
     * Lấy tổng số kiểm tra
     */
    private function getTotalInspections(array $projectIds): int
    {
        return rand(20, 40);
    }

    /**
     * Lấy số NCR
     */
    private function getNCRsCount(array $projectIds): int
    {
        return rand(2, 8);
    }

    /**
     * Lấy số quan sát
     */
    private function getObservationsCount(array $projectIds): int
    {
        return rand(5, 15);
    }

    /**
     * Lấy điểm chất lượng tổng thể
     */
    private function getOverallQualityScore(array $projectIds): float
    {
        return rand(85, 95);
    }

    /**
     * Lấy dữ liệu xu hướng chất lượng
     */
    private function getQualityTrendData(array $projectIds): array
    {
        // Mock data
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $data[] = [
                'date' => Carbon::now()->subDays($i)->format('Y-m-d'),
                'score' => rand(80, 95)
            ];
        }
        return $data;
    }

    /**
     * Lấy phân bố trạng thái NCR
     */
    private function getNCRStatusDistribution(array $projectIds): array
    {
        // Mock data
        return [
            'open' => rand(1, 3),
            'in_progress' => rand(1, 2),
            'closed' => rand(2, 5)
        ];
    }

    /**
     * Lấy tóm tắt kết quả kiểm tra
     */
    private function getInspectionResultsSummary(array $projectIds): array
    {
        // Mock data
        return [
            'passed' => rand(15, 25),
            'failed' => rand(2, 5),
            'conditional' => rand(1, 3)
        ];
    }

    /**
     * Lấy hiệu quả hành động khắc phục
     */
    private function getCorrectiveActionEffectiveness(array $projectIds): float
    {
        return rand(80, 95);
    }

    /**
     * Lấy tỷ lệ tuân thủ
     */
    private function getComplianceRate(array $projectIds): float
    {
        return rand(90, 98);
    }

    /**
     * Lấy phân tích xu hướng chất lượng
     */
    private function getQualityTrendAnalysis(array $projectIds): array
    {
        // Mock data
        return [
            'trend' => 'improving',
            'improvement_rate' => rand(5, 15),
            'key_areas' => ['safety', 'workmanship', 'materials']
        ];
    }

    // ==================== CLIENT REP METHODS ====================

    /**
     * Lấy số CR đang chờ
     */
    private function getPendingCRs(array $projectIds): int
    {
        return rand(3, 8);
    }

    /**
     * Lấy số phê duyệt cần thiết
     */
    private function getApprovalsRequired(array $projectIds): int
    {
        return rand(2, 5);
    }

    /**
     * Lấy trạng thái ngân sách
     */
    private function getBudgetStatus(array $projectIds): string
    {
        $statuses = ['on_track', 'over_budget', 'under_budget'];
        return $statuses[array_rand($statuses)];
    }

    /**
     * Lấy trạng thái timeline cho client
     */
    private function getClientTimelineStatus(array $projectIds): string
    {
        $statuses = ['on_track', 'at_risk', 'delayed'];
        return $statuses[array_rand($statuses)];
    }

    /**
     * Lấy tóm tắt tiến độ dự án
     */
    private function getProjectProgressSummary(array $projectIds): array
    {
        // Mock data
        return [
            'overall_progress' => rand(60, 90),
            'milestones_completed' => rand(5, 10),
            'milestones_total' => rand(10, 15)
        ];
    }

    /**
     * Lấy biểu đồ ngân sách vs thực tế
     */
    private function getBudgetVsActualChart(array $projectIds): array
    {
        // Mock data
        return [
            'budget' => rand(1000000, 5000000),
            'actual' => rand(800000, 4500000),
            'variance' => rand(-10, 10)
        ];
    }

    /**
     * Lấy trạng thái milestone timeline
     */
    private function getTimelineMilestoneStatus(array $projectIds): array
    {
        // Mock data
        return [
            'completed' => rand(3, 7),
            'in_progress' => rand(1, 3),
            'upcoming' => rand(2, 5)
        ];
    }

    /**
     * Lấy hiệu quả giao tiếp
     */
    private function getCommunicationEffectiveness(array $projectIds): float
    {
        return rand(80, 95);
    }

    /**
     * Lấy thời gian quyết định
     */
    private function getDecisionTime(array $projectIds): array
    {
        // Mock data
        return [
            'average_days' => rand(1, 5),
            'sla_compliance' => rand(85, 95)
        ];
    }

    /**
     * Lấy điểm hài lòng khách hàng
     */
    private function getClientSatisfactionScore(array $projectIds): float
    {
        return rand(80, 95);
    }

    // ==================== SUBCONTRACTOR LEAD METHODS ====================

    /**
     * Lấy số tasks được assign
     */
    private function getTasksAssigned(array $projectIds, string $userId): int
    {
        return rand(8, 20);
    }

    /**
     * Lấy số vật tư đã submit
     */
    private function getMaterialsSubmitted(array $projectIds, string $userId): int
    {
        return rand(5, 12);
    }

    /**
     * Lấy số cập nhật tiến độ
     */
    private function getProgressUpdates(array $projectIds, string $userId): int
    {
        return rand(10, 25);
    }

    /**
     * Lấy điểm chất lượng subcontractor
     */
    private function getSubcontractorQualityScore(array $projectIds, string $userId): float
    {
        return rand(80, 95);
    }

    /**
     * Lấy biểu đồ hoàn thành tasks
     */
    private function getTaskCompletionChart(array $projectIds, string $userId): array
    {
        // Mock data
        return [
            'completed' => rand(5, 15),
            'in_progress' => rand(2, 5),
            'pending' => rand(1, 3)
        ];
    }

    /**
     * Lấy trạng thái submit vật tư
     */
    private function getMaterialSubmissionStatus(array $projectIds, string $userId): array
    {
        // Mock data
        return [
            'pending_review' => rand(1, 3),
            'approved' => rand(3, 8),
            'rejected' => rand(0, 2)
        ];
    }

    /**
     * Lấy xu hướng hiệu suất chất lượng
     */
    private function getQualityPerformanceTrend(array $projectIds, string $userId): array
    {
        // Mock data
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $data[] = [
                'date' => Carbon::now()->subDays($i)->format('Y-m-d'),
                'score' => rand(80, 95)
            ];
        }
        return $data;
    }

    /**
     * Lấy tỷ lệ sử dụng tài nguyên
     */
    private function getResourceUtilization(array $projectIds, string $userId): float
    {
        return rand(75, 95);
    }

    /**
     * Lấy hiệu quả giao tiếp subcontractor
     */
    private function getSubcontractorCommunication(array $projectIds, string $userId): float
    {
        return rand(80, 95);
    }

    /**
     * Lấy đánh giá hiệu suất
     */
    private function getPerformanceRating(array $projectIds, string $userId): float
    {
        return rand(80, 95);
    }
}
