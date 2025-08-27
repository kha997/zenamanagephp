<?php declare(strict_types=1);

namespace Src\Compensation\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compensation Preview API Resource
 * Transform compensation preview data for API responses
 */
class CompensationPreviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'contract' => [
                'id' => $this->resource['contract']['id'],
                'title' => $this->resource['contract']['title'],
                'total_value' => (float) $this->resource['contract']['total_value'],
                'status' => $this->resource['contract']['status'],
            ],
            'compensations' => $this->resource['compensations'],
            'summary' => [
                'total_value' => (float) $this->resource['total_value'],
                'total_tasks' => count($this->resource['compensations']),
                'average_percent' => $this->calculateAveragePercent(),
                'value_distribution' => $this->getValueDistribution(),
            ],
            'preview_metadata' => [
                'generated_at' => now()->toISOString(),
                'can_apply' => $this->canApplyContract(),
                'warnings' => $this->getWarnings(),
            ]
        ];
    }
    
    /**
     * Calculate average compensation percentage
     * 
     * @return float
     */
    private function calculateAveragePercent(): float
    {
        $compensations = $this->resource['compensations'];
        
        if (empty($compensations)) {
            return 0.0;
        }
        
        $totalPercent = array_sum(array_column($compensations, 'effective_percent'));
        return round($totalPercent / count($compensations), 2);
    }
    
    /**
     * Get value distribution by ranges
     * 
     * @return array
     */
    private function getValueDistribution(): array
    {
        $compensations = $this->resource['compensations'];
        $distribution = [
            'low' => 0,    // < 1M
            'medium' => 0, // 1M - 5M
            'high' => 0,   // > 5M
        ];
        
        foreach ($compensations as $comp) {
            $value = $comp['current_value'];
            
            if ($value < 1000000) {
                $distribution['low']++;
            } elseif ($value <= 5000000) {
                $distribution['medium']++;
            } else {
                $distribution['high']++;
            }
        }
        
        return $distribution;
    }
    
    /**
     * Check if contract can be applied
     * 
     * @return bool
     */
    private function canApplyContract(): bool
    {
        return $this->resource['contract']['status'] === 'active' && 
               !empty($this->resource['compensations']);
    }
    
    /**