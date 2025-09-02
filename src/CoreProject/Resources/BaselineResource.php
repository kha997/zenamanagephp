<?php declare(strict_types=1);

namespace Src\CoreProject\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource để transform Baseline model thành JSON response
 * 
 * @package Src\CoreProject\Resources
 */
class BaselineResource extends JsonResource
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
            'id' => $this->id,
            'project_id' => $this->project_id,
            'type' => $this->type,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'cost' => $this->cost,
            'version' => $this->version,
            'note' => $this->note,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email
                ];
            }),
            
            'project' => $this->whenLoaded('project', function () {
                return [
                    'id' => $this->project->id,
                    'name' => $this->project->name
                ];
            })
        ];
    }
}