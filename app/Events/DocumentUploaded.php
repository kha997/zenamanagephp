<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;

class DocumentUploaded implements ShouldBroadcast
{

    public Document $document;
    public User $user;
    public array $metadata;

    /**
     * Create a new event instance.
     */
    public function __construct(Document $document, User $user, array $metadata = [])
    {
        $this->document = $document;
        $this->user = $user;
        $this->metadata = $metadata;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.' . $this->document->tenant_id),
            new PrivateChannel('project.' . $this->document->project_id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'event_type' => 'document_uploaded',
            'document' => [
                'id' => $this->document->id,
                'name' => $this->document->name,
                'original_name' => $this->document->original_name,
                'type' => $this->document->type,
                'category' => $this->document->category,
                'status' => $this->document->status,
                'file_size' => $this->document->file_size,
                'mime_type' => $this->document->mime_type,
                'version' => $this->document->version,
                'project_id' => $this->document->project_id,
                'task_id' => $this->document->task_id,
                'component_id' => $this->document->component_id,
                'uploaded_at' => $this->document->created_at?->toISOString(),
            ],
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'metadata' => $this->metadata,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get the event name for broadcasting.
     */
    public function broadcastAs(): string
    {
        return 'document.uploaded';
    }
}