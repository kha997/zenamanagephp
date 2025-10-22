@extends('layouts.app')

@section('title', 'Test Task')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1>Test Task Page</h1>
    <p>Task ID: {{ $task->id }}</p>
    <p>Task Name: {{ $task->name }}</p>
    <p>Project: {{ $task->project->name ?? 'No Project' }}</p>
    <p>Assignee: {{ $task->assignee->name ?? 'No Assignee' }}</p>
    <p>Creator: {{ $task->creator->name ?? 'No Creator' }}</p>
</div>
@endsection
