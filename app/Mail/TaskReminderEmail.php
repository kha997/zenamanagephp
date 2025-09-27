<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use App\Models\User;
use App\Models\Task;

class TaskReminderEmail extends Mailable
{
    use Queueable;

    public $user;
    public $task;
    public $taskUrl;
    public $projectName;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, Task $task)
    {
        $this->user = $user;
        $this->task = $task;
        $this->taskUrl = config('app.url') . "/app/tasks/{$task->id}";
        $this->projectName = $task->project->name ?? 'No Project';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Task Reminder: {$this->task->title}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.task-reminder',
            with: [
                'user' => $this->user,
                'task' => $this->task,
                'taskUrl' => $this->taskUrl,
                'projectName' => $this->projectName,
                'dueDate' => $this->task->due_date?->format('F d, Y \a\t g:i A'),
                'priority' => ucfirst($this->task->priority),
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
