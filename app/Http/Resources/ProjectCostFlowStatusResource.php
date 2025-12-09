<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProjectCostFlowStatusResource
 * 
 * Round 232: Project Cost Flow Status
 * 
 * Formats cost flow status response for API
 */
class ProjectCostFlowStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'project_id' => $this->resource['project_id'],
            'status' => $this->resource['status'],
            'metrics' => [
                'pending_change_orders' => (int) ($this->resource['metrics']['pending_change_orders'] ?? 0),
                'delayed_change_orders' => (int) ($this->resource['metrics']['delayed_change_orders'] ?? 0),
                'rejected_change_orders' => (int) ($this->resource['metrics']['rejected_change_orders'] ?? 0),
                'pending_certificates' => (int) ($this->resource['metrics']['pending_certificates'] ?? 0),
                'delayed_certificates' => (int) ($this->resource['metrics']['delayed_certificates'] ?? 0),
                'rejected_certificates' => (int) ($this->resource['metrics']['rejected_certificates'] ?? 0),
            ],
        ];
    }
}
