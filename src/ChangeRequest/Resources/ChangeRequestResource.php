<?php declare(strict_types=1);

namespace Src\ChangeRequest\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Src\ChangeRequest\Models\ChangeRequest;
use Src\CoreProject\Resources\ProjectResource;
use Src\RBAC\Resources\UserResource;

/**
 * Change Request API Resource
 * Transform ChangeRequest model data for API responses
 */
class ChangeRequestResource extends JsonResource
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
            'ulid' => $this->ulid,
            'project_id' => $this->project_id,
            'code' => $this->code,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'impact_days' => $this->impact_days,
            'impact_cost' => (float) $this->impact_cost,
            'impact_kpi' => $this->impact_kpi,
            'attachments' => $this->attachments,
            'justification' => $this->justification,
            'priority' => $this->priority,
            'priority_label' => $this->getPriorityLabel(),
            'tags' => $this->tags,
            'visibility' => $this->visibility,
            'client_approved' => $this->client_approved,
            'created_by' => $this->created_by,
            'decided_by' => $this->decided_by,
            'decided_at' => $this->decided_at?->toISOString(),
            'decision_note' => $this->decision_note,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Relationships (loaded when available)
            'project' => new ProjectResource($this->whenLoaded('project')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'decider' => new UserResource($this->whenLoaded('decider')),
            
            // Computed properties
            'can_be_edited' => $this->canBeEdited(),
            'can_be_submitted' => $this->canBeSubmitted(),
            'can_be_decided' => $this->canBeDecided(),
            'is_decided' => $this->isDecided(),
            'is_approved' => $this->isApproved(),
            'is_rejected' => $this->isRejected(),
            'days_since_created' => $this->getDaysSinceCreated(),
            'days_since_decided' => $this->getDaysSinceDecided(),
        ];
    }
    
    /**
     * Get status label
     */
    private function getStatusLabel(): string
    {
        $labels = [
            'draft' => 'Bản nháp',
            'awaiting_approval' => 'Chờ phê duyệt',
            'approved' => 'Đã phê duyệt',
            'rejected' => 'Đã từ chối',
        ];
        
        return $labels[$this->status] ?? $this->status;
    }
    
    /**
     * Get priority label
     */
    private function getPriorityLabel(): string
    {
        $labels = [
            'low' => 'Thấp',
            'medium' => 'Trung bình',
            'high' => 'Cao',
            'critical' => 'Khẩn cấp',
        ];
        
        return $labels[$this->priority] ?? $this->priority;
    }
    
    /**
     * Get days since created
     */
    private function getDaysSinceCreated(): int
    {
        return $this->created_at->diffInDays(now());
    }
    
    /**
     * Get days since decided
     */
    private function getDaysSinceDecided(): ?int
    {
        if (!$this->decided_at) {
            return null;
        }
        
        return $this->decided_at->diffInDays(now());
    }

    private function canBeEdited(): bool
    {
        return $this->status === ChangeRequest::STATUS_DRAFT;
    }

    private function canBeSubmitted(): bool
    {
        return $this->status === ChangeRequest::STATUS_DRAFT;
    }

    private function canBeDecided(): bool
    {
        return $this->status === ChangeRequest::STATUS_AWAITING_APPROVAL;
    }

    private function isDecided(): bool
    {
        return in_array(
            $this->status,
            [
                ChangeRequest::STATUS_APPROVED,
                ChangeRequest::STATUS_REJECTED,
            ],
            true
        );
    }

    private function isApproved(): bool
    {
        return $this->status === ChangeRequest::STATUS_APPROVED;
    }

    private function isRejected(): bool
    {
        return $this->status === ChangeRequest::STATUS_REJECTED;
    }
}
