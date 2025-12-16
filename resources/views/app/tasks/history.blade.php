@extends('layouts.app')

@section('title', 'Task History')

@section('content')
<div class="min-h-screen bg-gray-50" data-testid="task-history-page">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Task History</h1>
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
        <!-- History Timeline -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Activity History</h3>
            </div>

            <div class="p-6">
                @forelse($history as $entry)
                    <div class="flex items-start space-x-4 pb-6 border-l-2 border-gray-200 pl-6 relative" data-testid="history-entry">
                        <div class="absolute left-0 top-0 w-4 h-4 bg-blue-600 rounded-full -ml-2 border-2 border-white"></div>
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <h4 class="text-sm font-medium text-gray-900">{{ $entry['action'] ?? 'Unknown Action' }}</h4>
                                <span class="text-xs text-gray-500">
                                    @if(isset($entry['created_at']))
                                        {{ \Carbon\Carbon::parse($entry['created_at'])->format('M d, Y H:i') }}
                                    @endif
                                </span>
                            </div>
                            @if(isset($entry['description']))
                                <p class="text-sm text-gray-600 mt-1">{{ $entry['description'] }}</p>
                            @endif
                            @if(isset($entry['user']))
                                <p class="text-xs text-gray-500 mt-2">
                                    <i class="fas fa-user mr-1"></i>
                                    {{ $entry['user']['name'] ?? 'Unknown User' }}
                                </p>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12" data-testid="empty-history">
                        <i class="fas fa-history text-4xl text-gray-300 mb-4"></i>
                        <p class="text-lg font-medium text-gray-900">No history available</p>
                        <p class="text-sm text-gray-500 mt-2">Task history will appear here as changes are made.</p>
                    </div>
                @endforelse
                
                <!-- Pagination -->
                @if(isset($pagination) && $pagination->hasPages())
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing {{ $pagination->firstItem() ?? 0 }} to {{ $pagination->lastItem() ?? 0 }} of {{ $pagination->total() }} results
                            </div>
                            <div class="flex items-center space-x-2">
                                @if($pagination->onFirstPage())
                                    <span class="px-3 py-1 text-sm border border-gray-300 rounded-md text-gray-400 cursor-not-allowed">Previous</span>
                                @else
                                    <a href="{{ $pagination->previousPageUrl() }}" 
                                       class="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-50 text-gray-700">
                                        Previous
                                    </a>
                                @endif
                                
                                @php
                                    $currentPage = $pagination->currentPage();
                                    $lastPage = $pagination->lastPage();
                                    $startPage = max(1, $currentPage - 2);
                                    $endPage = min($lastPage, $currentPage + 2);
                                @endphp
                                
                                @if($startPage > 1)
                                    <a href="{{ $pagination->url(1) }}" 
                                       class="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-50 text-gray-700">1</a>
                                    @if($startPage > 2)
                                        <span class="px-3 py-1 text-sm text-gray-500">...</span>
                                    @endif
                                @endif
                                
                                @foreach($pagination->getUrlRange($startPage, $endPage) as $page => $url)
                                    @if($page == $currentPage)
                                        <span class="px-3 py-1 text-sm border border-gray-300 rounded-md bg-blue-600 text-white">{{ $page }}</span>
                                    @else
                                        <a href="{{ $url }}" 
                                           class="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-50 text-gray-700">
                                            {{ $page }}
                                        </a>
                                    @endif
                                @endforeach
                                
                                @if($endPage < $lastPage)
                                    @if($endPage < $lastPage - 1)
                                        <span class="px-3 py-1 text-sm text-gray-500">...</span>
                                    @endif
                                    <a href="{{ $pagination->url($lastPage) }}" 
                                       class="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-50 text-gray-700">{{ $lastPage }}</a>
                                @endif
                                
                                @if($pagination->hasMorePages())
                                    <a href="{{ $pagination->nextPageUrl() }}" 
                                       class="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-50 text-gray-700">
                                        Next
                                    </a>
                                @else
                                    <span class="px-3 py-1 text-sm border border-gray-300 rounded-md text-gray-400 cursor-not-allowed">Next</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

