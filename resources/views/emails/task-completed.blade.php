@extends('emails.layout')

@section('title', __('notifications.task_completed_title'))

@section('content')
<div class="email-container">
    <div class="email-header">
        <h1>{{ __('notifications.task_completed_title') }}</h1>
    </div>
    
    <div class="email-body">
        <p>{{ __('notifications.task_completed_greeting', ['name' => $user->name]) }}</p>
        
        <div class="task-info">
            <h2>{{ __('notifications.task_details') }}</h2>
            <ul>
                <li><strong>{{ __('notifications.task_title') }}:</strong> {{ $task->title }}</li>
                <li><strong>{{ __('notifications.project') }}:</strong> {{ $task->project->name }}</li>
                <li><strong>{{ __('notifications.completed_by') }}:</strong> {{ $task->completedBy->name }}</li>
                <li><strong>{{ __('notifications.completed_at') }}:</strong> {{ $task->completed_at->format('d/m/Y H:i') }}</li>
            </ul>
        </div>
        
        <div class="email-actions">
            <a href="{{ route('app.tasks.show', $task->id) }}" class="btn btn-primary">
                {{ __('notifications.view_task') }}
            </a>
            <a href="{{ route('app.projects.show', $task->project->id) }}" class="btn btn-secondary">
                {{ __('notifications.view_project') }}
            </a>
        </div>
        
        <p class="email-footer">
            {{ __('notifications.email_footer') }}
        </p>
    </div>
</div>
@endsection
