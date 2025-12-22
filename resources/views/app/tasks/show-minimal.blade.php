@extends('layouts.app')

@section('title', 'Task Details')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">{{ $task->name }}</h1>
                <p class="text-gray-600 mt-2">{{ $task->project->name ?? 'No Project' }}</p>
            </div>
        </div>

        <!-- Comments Section -->
        <div class="bg-white rounded-lg shadow-md p-6" data-testid="comments-section">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-gray-800">Comments</h2>
            </div>
            
            <!-- Comments List -->
            <div class="space-y-4" data-testid="comments-container">
                <div class="text-center py-8">
                    <i class="fas fa-comments text-gray-300 text-3xl mb-2"></i>
                    <p class="text-gray-500">No comments yet. Be the first to comment!</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
