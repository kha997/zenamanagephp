<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform InteractionLog model into JSON response
 * 
 * @property \App\Models\InteractionLog $resource
 */
class InteractionLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'linked_task_id' => $this->linked_task_id,
            'type' => $this->type,
            'description' => $this->description,
            'tag_path' => $this->tag_path,
            'visibility' => $this->visibility,
            'client_approved' => $this->client_approved,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Conditional relationships - chỉ load khi cần thiết
            'project' => $this->whenLoaded('project', function () {
                return new ProjectResource($this->project);
            }),
            
            'linked_task' => $this->whenLoaded('linkedTask', function () {
                return [
                    'id' => $this->linkedTask->id,
                    'name' => $this->linkedTask->name,
                    'status' => $this->linkedTask->status,
                ];
            }),
            
            'created_by_user' => $this->whenLoaded('createdByUser', function () {
                return new UserResource($this->createdByUser);
            }),
            
            // Attachments nếu có
            'attachments' => $this->whenLoaded('attachments', function () {
                return $this->attachments->map(function ($attachment) {
                    return [
                        'id' => $attachment->id,
                        'filename' => $attachment->filename,
                        'file_path' => $attachment->file_path,
                        'file_size' => $attachment->file_size,
                        'mime_type' => $attachment->mime_type,
                    ];
                });
            }),
            
            // Security & Privacy - ẩn thông tin nhạy cảm với client
            $this->mergeWhen($request->user()?->can('view-internal-logs'), [
                'internal_notes' => $this->internal_notes,
                'created_by' => $this->created_by,
            ]),
        ];
    }
}