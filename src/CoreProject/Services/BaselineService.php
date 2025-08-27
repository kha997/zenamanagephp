<?php declare(strict_types=1);

namespace Src\CoreProject\Services;

use Src\CoreProject\Models\Baseline;
use Src\CoreProject\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Carbon\Carbon;

/**
 * Service xử lý business logic cho Baseline Management
 * 
 * @package Src\CoreProject\Services
 */
class BaselineService
{
    /**
     * Tính toán variance giữa baseline và project hiện tại
     *
     * @param string $projectId
     * @param string|null $baselineType
     * @return array
     */
    public function calculateProjectVariance(string $projectId, ?string $baselineType = null): array
    {
        $project = Project::findOrFail($projectId);
        
        // Lấy baseline để so sánh (ưu tiên execution, fallback contract)
        $baseline = null;
        if ($baselineType) {
            $baseline = Baseline::getCurrentBaseline($projectId, $baselineType);
        } else {
            $baseline = Baseline::getCurrentBaseline($projectId, Baseline::TYPE_EXECUTION) 
                     ?? Baseline::getCurrentBaseline($projectId, Baseline::TYPE_CONTRACT);
        }
        
        if (!$baseline) {
            return [
                'project_id' => $projectId,
                'baseline' => null,
                'current_project' => [
                    'start_date' => $project->start_date,
                    'end_date' => $project->end_date,
                    'actual_cost' => $project->actual_cost,
                    'progress' => $project->progress
                ],
                'variance' => null,
                'message' => 'Không có baseline để so sánh'
            ];
        }
        
        // Tính toán variance về thời gian
        $currentDate = Carbon::now();
        $plannedDuration = $baseline->getDurationInDays();
        $actualDuration = $project->start_date ? $project->start_date->diffInDays($currentDate) : 0;
        
        // Tính toán schedule variance
        $scheduleVarianceDays = 0;
        $scheduleVariancePercent = 0;
        
        if ($project->progress > 0) {
            $expectedDaysForProgress = ($plannedDuration * $project->progress) / 100;
            $scheduleVarianceDays = $actualDuration - $expectedDaysForProgress;
            $scheduleVariancePercent = $expectedDaysForProgress > 0 
                ? ($scheduleVarianceDays / $expectedDaysForProgress) * 100 
                : 0;
        }
        
        // Tính toán cost variance
        $plannedCost = $baseline->cost;
        $actualCost = $project->actual_cost ?? 0;
        $costVariance = $actualCost - $plannedCost;
        $costVariancePercent = $plannedCost > 0 
            ? ($costVariance / $plannedCost) * 100 
            : 0;
        
        // Tính toán Earned Value Management metrics
        $plannedValue = ($plannedCost * $project->progress) / 100; // PV
        $earnedValue = $plannedValue; // EV = PV khi progress được tính chính xác
        $actualCostForProgress = $actualCost; // AC
        
        $costPerformanceIndex = $earnedValue > 0 ? $earnedValue / $actualCostForProgress : 0; // CPI
        $schedulePerformanceIndex = $plannedValue > 0 ? $earnedValue / $plannedValue : 0; // SPI
        
        // Dự báo chi phí hoàn thành
        $estimateAtCompletion = $costPerformanceIndex > 0 
            ? $plannedCost / $costPerformanceIndex 
            : $plannedCost;
        
        $estimateToComplete = $estimateAtCompletion - $actualCost;
        
        return [
            'project_id' => $projectId,
            'baseline' => [
                'id' => $baseline->id,
                'type' => $baseline->type,
                'version' => $baseline->version,
                'start_date' => $baseline->start_date,
                'end_date' => $baseline->end_date,
                'cost' => $baseline->cost,
                'duration_days' => $plannedDuration
            ],
            'current_project' => [
                'start_date' => $project->start_date,
                'end_date' => $project->end_date,
                'actual_cost' => $actualCost,
                'progress' => $project->progress,
                'current_duration_days' => $actualDuration
            ],
            'variance' => [
                'schedule' => [
                    'days_variance' => round($scheduleVarianceDays, 1),
                    'percent_variance' => round($scheduleVariancePercent, 2),
                    'status' => $this->getScheduleStatus($scheduleVarianceDays)
                ],
                'cost' => [
                    'amount_variance' => $costVariance,
                    'percent_variance' => round($costVariancePercent, 2),
                    'status' => $this->getCostStatus($costVariancePercent)
                ]
            ],
            'earned_value_metrics' => [
                'planned_value' => round($plannedValue, 2),
                'earned_value' => round($earnedValue, 2),
                'actual_cost' => round($actualCostForProgress, 2),
                'cost_performance_index' => round($costPerformanceIndex, 3),
                'schedule_performance_index' => round($schedulePerformanceIndex, 3),
                'estimate_at_completion' => round($estimateAtCompletion, 2),
                'estimate_to_complete' => round($estimateToComplete, 2)
            ],
            'analysis' => [
                'overall_health' => $this->calculateProjectHealth($costPerformanceIndex, $schedulePerformanceIndex),
                'risk_level' => $this->calculateOverallRisk($scheduleVarianceDays, $costVariancePercent),
                'recommendations' => $this->generateRecommendations($costPerformanceIndex, $schedulePerformanceIndex, $costVariancePercent, $scheduleVarianceDays)
            ],
            'calculated_at' => $currentDate
        ];
    }
    
    /**
     * Lấy trạng thái schedule dựa trên variance
     *
     * @param float $scheduleVarianceDays
     * @return string
     */
    private function getScheduleStatus(float $scheduleVarianceDays): string
    {
        if ($scheduleVarianceDays <= -7) {
            return 'Ahead of Schedule';
        } elseif ($scheduleVarianceDays <= 7) {
            return 'On Schedule';
        } elseif ($scheduleVarianceDays <= 30) {
            return 'Behind Schedule';
        } else {
            return 'Significantly Behind';
        }
    }
    
    /**
     * Lấy trạng thái cost dựa trên variance
     *
     * @param float $costVariancePercent
     * @return string
     */
    private function getCostStatus(float $costVariancePercent): string
    {
        if ($costVariancePercent <= -10) {
            return 'Under Budget';
        } elseif ($costVariancePercent <= 10) {
            return 'On Budget';
        } elseif ($costVariancePercent <= 25) {
            return 'Over Budget';
        } else {
            return 'Significantly Over Budget';
        }
    }
    
    /**
     * Tính toán sức khỏe tổng thể của project
     *
     * @param float $cpi
     * @param float $spi
     * @return string
     */
    private function calculateProjectHealth(float $cpi, float $spi): string
    {
        $avgIndex = ($cpi + $spi) / 2;
        
        if ($avgIndex >= 1.1) {
            return 'Excellent';
        } elseif ($avgIndex >= 0.95) {
            return 'Good';
        } elseif ($avgIndex >= 0.85) {
            return 'Fair';
        } else {
            return 'Poor';
        }
    }
    
    /**
     * Tạo recommendations dựa trên performance metrics
     *
     * @param float $cpi
     * @param float $spi
     * @param float $costVariancePercent
     * @param float $scheduleVarianceDays
     * @return array
     */
    private function generateRecommendations(float $cpi, float $spi, float $costVariancePercent, float $scheduleVarianceDays): array
    {
        $recommendations = [];
        
        // Cost recommendations
        if ($cpi < 0.9) {
            $recommendations[] = 'Chi phí vượt ngân sách. Cần xem xét tối ưu hóa quy trình và kiểm soát chi phí.';
        } elseif ($cpi > 1.1) {
            $recommendations[] = 'Chi phí thấp hơn dự kiến. Có thể tăng đầu tường để cải thiện chất lượng hoặc tiến độ.';
        }
        
        // Schedule recommendations
        if ($spi < 0.9) {
            $recommendations[] = 'Tiến độ chậm so với kế hoạch. Cần tăng cường nguồn lực hoặc tối ưu hóa quy trình.';
        } elseif ($scheduleVarianceDays > 30) {
            $recommendations[] = 'Dự án trễ hạn nghiêm trọng. Cần re-baseline hoặc điều chỉnh scope.';
        }
        
        // Combined recommendations
        if ($cpi < 0.9 && $spi < 0.9) {
            $recommendations[] = 'Cả chi phí và tiến độ đều có vấn đề. Cần đánh giá lại toàn bộ kế hoạch dự án.';
        }
        
        if (empty($recommendations)) {
            $recommendations[] = 'Dự án đang diễn ra tốt theo kế hoạch.';
        }
        
        return $recommendations;
    }

    /**
     * So sánh hai baseline để phân tích variance
     *
     * @param Baseline $baseline1
     * @param Baseline $baseline2
     * @return array
     */
    public function compareBaselines(Baseline $baseline1, Baseline $baseline2): array
    {
        // Tính toán variance về thời gian
        $durationVariance = $baseline2->getDurationInDays() - $baseline1->getDurationInDays();
        $startDateVariance = $baseline1->start_date->diffInDays($baseline2->start_date, false);
        $endDateVariance = $baseline1->end_date->diffInDays($baseline2->end_date, false);
        
        // Tính toán variance về chi phí
        $costVariance = $baseline2->cost - $baseline1->cost;
        $costVariancePercent = $baseline1->cost > 0 
            ? ($costVariance / $baseline1->cost) * 100 
            : 0;
        
        return [
            'baseline_1' => [
                'id' => $baseline1->id,
                'type' => $baseline1->type,
                'version' => $baseline1->version,
                'start_date' => $baseline1->start_date,
                'end_date' => $baseline1->end_date,
                'duration_days' => $baseline1->getDurationInDays(),
                'cost' => $baseline1->cost
            ],
            'baseline_2' => [
                'id' => $baseline2->id,
                'type' => $baseline2->type,
                'version' => $baseline2->version,
                'start_date' => $baseline2->start_date,
                'end_date' => $baseline2->end_date,
                'duration_days' => $baseline2->getDurationInDays(),
                'cost' => $baseline2->cost
            ],
            'variance' => [
                'duration_days' => $durationVariance,
                'start_date_days' => $startDateVariance,
                'end_date_days' => $endDateVariance,
                'cost_amount' => $costVariance,
                'cost_percent' => round($costVariancePercent, 2)
            ],
            'analysis' => [
                'schedule_impact' => $this->analyzeScheduleImpact($durationVariance, $startDateVariance, $endDateVariance),
                'cost_impact' => $this->analyzeCostImpact($costVariancePercent),
                'overall_risk' => $this->calculateOverallRisk($durationVariance, $costVariancePercent)
            ]
        ];
    }
    
    /**
     * Tạo baseline mới từ dữ liệu project hiện tại
     *
     * @param string $projectId
     * @param string $type
     * @param string $userId
     * @param string|null $note
     * @return Baseline
     */
    public function createBaselineFromProject(string $projectId, string $type, string $userId, ?string $note = null): Baseline
    {
        $project = Project::findOrFail($projectId);
        
        // Tính toán dữ liệu từ project hiện tại
        $startDate = $project->start_date ?? Carbon::now();
        $endDate = $project->end_date ?? Carbon::now()->addDays(30);
        $cost = $project->actual_cost ?? 0;
        
        // Tính version tiếp theo
        $latestVersion = Baseline::where('project_id', $projectId)
                                ->where('type', $type)
                                ->max('version') ?? 0;
        
        $baseline = Baseline::create([
            'project_id' => $projectId,
            'type' => $type,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'cost' => $cost,
            'version' => $latestVersion + 1,
            'note' => $note ?? "Baseline được tạo từ dữ liệu project hiện tại",
            'created_by' => $userId
        ]);
        
        // Dispatch event
        Event::dispatch('Project.Baseline.Created', [
            'baseline_id' => $baseline->id,
            'project_id' => $projectId,
            'type' => $type,
            'version' => $baseline->version,
            'created_by' => $userId,
            'timestamp' => now()
        ]);
        
        return $baseline;
    }
    
    /**
     * Lấy baseline comparison report cho project
     *
     * @param string $projectId
     * @return array
     */
    public function getProjectBaselineReport(string $projectId): array
    {
        $contractBaseline = Baseline::getCurrentBaseline($projectId, Baseline::TYPE_CONTRACT);
        $executionBaseline = Baseline::getCurrentBaseline($projectId, Baseline::TYPE_EXECUTION);
        
        $report = [
            'project_id' => $projectId,
            'contract_baseline' => $contractBaseline ? [
                'id' => $contractBaseline->id,
                'version' => $contractBaseline->version,
                'start_date' => $contractBaseline->start_date,
                'end_date' => $contractBaseline->end_date,
                'cost' => $contractBaseline->cost,
                'duration_days' => $contractBaseline->getDurationInDays()
            ] : null,
            'execution_baseline' => $executionBaseline ? [
                'id' => $executionBaseline->id,
                'version' => $executionBaseline->version,
                'start_date' => $executionBaseline->start_date,
                'end_date' => $executionBaseline->end_date,
                'cost' => $executionBaseline->cost,
                'duration_days' => $executionBaseline->getDurationInDays()
            ] : null,
            'comparison' => null
        ];
        
        // Nếu có cả hai baseline, thực hiện comparison
        if ($contractBaseline && $executionBaseline) {
            $report['comparison'] = $this->compareBaselines($contractBaseline, $executionBaseline);
        }
        
        return $report;
    }
    
    /**
     * Validate baseline data trước khi tạo/cập nhật
     *
     * @param array $data
     * @param string $projectId
     * @return array
     */
    public function validateBaselineData(array $data, string $projectId): array
    {
        $errors = [];
        
        // Kiểm tra project tồn tại
        if (!Project::find($projectId)) {
            $errors[] = 'Project không tồn tại';
        }
        
        // Kiểm tra logic ngày tháng
        if (isset($data['start_date']) && isset($data['end_date'])) {
            $startDate = Carbon::parse($data['start_date']);
            $endDate = Carbon::parse($data['end_date']);
            
            if ($endDate->lte($startDate)) {
                $errors[] = 'Ngày kết thúc phải sau ngày bắt đầu';
            }
            
            // Kiểm tra thời gian hợp lý (không quá 10 năm)
            if ($startDate->diffInYears($endDate) > 10) {
                $errors[] = 'Thời gian dự án không được vượt quá 10 năm';
            }
        }
        
        // Kiểm tra chi phí hợp lý
        if (isset($data['cost']) && $data['cost'] < 0) {
            $errors[] = 'Chi phí không được âm';
        }
        
        if (isset($data['cost']) && $data['cost'] > 999999999999.99) {
            $errors[] = 'Chi phí vượt quá giới hạn cho phép';
        }
        
        return $errors;
    }
    
    /**
     * Phân tích tác động lịch trình
     *
     * @param int $durationVariance
     * @param int $startDateVariance
     * @param int $endDateVariance
     * @return string
     */
    private function analyzeScheduleImpact(int $durationVariance, int $startDateVariance, int $endDateVariance): string
    {
        if (abs($durationVariance) <= 7) {
            return 'Minimal';
        } elseif (abs($durationVariance) <= 30) {
            return 'Moderate';
        } else {
            return 'Significant';
        }
    }
    
    /**
     * Phân tích tác động chi phí
     *
     * @param float $costVariancePercent
     * @return string
     */
    private function analyzeCostImpact(float $costVariancePercent): string
    {
        $absVariance = abs($costVariancePercent);
        
        if ($absVariance <= 5) {
            return 'Minimal';
        } elseif ($absVariance <= 15) {
            return 'Moderate';
        } else {
            return 'Significant';
        }
    }
    
    /**
     * Tính toán risk tổng thể
     *
     * @param int $durationVariance
     * @param float $costVariancePercent
     * @return string
     */
    private function calculateOverallRisk(int $durationVariance, float $costVariancePercent): string
    {
        $scheduleRisk = abs($durationVariance) > 30 ? 2 : (abs($durationVariance) > 7 ? 1 : 0);
        $costRisk = abs($costVariancePercent) > 15 ? 2 : (abs($costVariancePercent) > 5 ? 1 : 0);
        
        $totalRisk = $scheduleRisk + $costRisk;
        
        if ($totalRisk >= 3) {
            return 'High';
        } elseif ($totalRisk >= 1) {
            return 'Medium';
        } else {
            return 'Low';
        }
    }
}