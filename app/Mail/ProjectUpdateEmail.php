<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use App\Models\User;
use App\Models\Project;

class ProjectUpdateEmail extends Mailable
{
    use Queueable;

    public $user;
    public $project;
    public $projectUrl;
    public $updateType;
    public $updateData;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, Project $project, string $updateType, array $updateData = [])
    {
        $this->user = $user;
        $this->project = $project;
        $this->projectUrl = config('app.url') . "/app/projects/{$project->id}";
        $this->updateType = $updateType;
        $this->updateData = $updateData;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Project Update: {$this->project->name}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.project-update',
            with: [
                'user' => $this->user,
                'project' => $this->project,
                'projectUrl' => $this->projectUrl,
                'updateType' => $this->updateType,
                'updateData' => $this->updateData,
                'projectStatus' => ucfirst($this->project->status),
                'projectProgress' => $this->project->progress . '%',
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
