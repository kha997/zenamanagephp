@extends('layouts.app')

@section('title', 'Task Documents')

@section('content')
<div class="min-h-screen bg-gray-50" data-testid="task-documents-page">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Task Documents</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        <a href="{{ route('app.tasks.show', $task->id) }}" class="text-blue-600 hover:text-blue-800">
                            {{ $task->name }}
                        </a>
                    </p>
                </div>
                <div class="flex space-x-3">
                    <a href="{{ route('app.tasks.show', $task->id) }}" 
                       class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Task
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Documents List -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Documents</h3>
            </div>

            <div class="p-6">
                @forelse($documents as $document)
                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg mb-4 hover:bg-gray-50" data-testid="document-item">
                        <div class="flex items-center space-x-4">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-file text-blue-600 text-xl"></i>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-900">{{ $document->name ?? 'Untitled Document' }}</h4>
                                <p class="text-sm text-gray-500 mt-1">
                                    @if(isset($document->type))
                                        Type: {{ $document->type }}
                                    @endif
                                    @if(isset($document->size))
                                        | Size: {{ number_format($document->size / 1024, 2) }} KB
                                    @endif
                                    @if(isset($document->created_at))
                                        | Uploaded: {{ \Carbon\Carbon::parse($document->created_at)->format('M d, Y') }}
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            @if(isset($document->file_path))
                                <a href="{{ asset($document->file_path) }}" 
                                   target="_blank"
                                   class="px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   data-testid="view-document-button">
                                    <i class="fas fa-eye mr-1"></i> View
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12" data-testid="empty-documents">
                        <i class="fas fa-file text-4xl text-gray-300 mb-4"></i>
                        <p class="text-lg font-medium text-gray-900">No documents found</p>
                        <p class="text-sm text-gray-500 mt-2">This task doesn't have any documents yet.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

