<?php declare(strict_types=1);

namespace App\InteractionLogs\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Src\CoreProject\Resources\ProjectResource;
use Src\CoreProject\Resources\TaskResource;

class InteractionLogResource extends JsonResource
{
    /**
     * Transform resource thành array
     * 
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        // Tạo mapping cho type labels
        $typeLabels = [
            'call' => 'Cuộc gọi',
            'email' => 'Email', 
            'meeting' => 'Cuộc họp',
            'note' => 'Ghi chú',
            'feedback' => 'Phản hồi'
        ];
        
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'project_id' => $this->project_id,
            'linked_task_id' => $this->linked_task_id,
            'type' => $this->type,
            'type_label' => $typeLabels[$this->type] ?? $this->type,
            'description' => $this->description,
            'tag_path' => $this->tag_path,
            'visibility' => $this->visibility,
            'client_approved' => $this->client_approved,
            'is_visible_to_client' => $this->isClientVisible(),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'project' => new ProjectResource($this->whenLoaded('project')),
            'linked_task' => new TaskResource($this->whenLoaded('linkedTask')),
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email
                ];
            })
        ];
    }
}