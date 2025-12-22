{{-- Projects Filters Component --}}
@props(['projects' => [], 'users' => [], 'filters' => []])

<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Search -->
        <div class="relative">
            <input type="text" 
                   placeholder="{{ __('projects.search_placeholder') }}"
                   value="{{ $filters['search'] ?? '' }}"
                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
        </div>

        <!-- Status Filter -->
        <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="">{{ __('projects.all_status') }}</option>
            <option value="planning" {{ ($filters['status'] ?? '') == 'planning' ? 'selected' : '' }}>{{ __('projects.status.planning') }}</option>
            <option value="active" {{ ($filters['status'] ?? '') == 'active' ? 'selected' : '' }}>{{ __('projects.status.active') }}</option>
            <option value="on_hold" {{ ($filters['status'] ?? '') == 'on_hold' ? 'selected' : '' }}>{{ __('projects.status.on_hold') }}</option>
            <option value="completed" {{ ($filters['status'] ?? '') == 'completed' ? 'selected' : '' }}>{{ __('projects.status.completed') }}</option>
            <option value="cancelled" {{ ($filters['status'] ?? '') == 'cancelled' ? 'selected' : '' }}>{{ __('projects.status.cancelled') }}</option>
        </select>

        <!-- Priority Filter -->
        <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="">{{ __('projects.all_priorities') }}</option>
            <option value="high" {{ ($filters['priority'] ?? '') == 'high' ? 'selected' : '' }}>{{ __('projects.priority.high') }}</option>
            <option value="medium" {{ ($filters['priority'] ?? '') == 'medium' ? 'selected' : '' }}>{{ __('projects.priority.medium') }}</option>
            <option value="low" {{ ($filters['priority'] ?? '') == 'low' ? 'selected' : '' }}>{{ __('projects.priority.low') }}</option>
        </select>

        <!-- Sort Options -->
        <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="name" {{ ($filters['sort'] ?? '') == 'name' ? 'selected' : '' }}>{{ __('projects.sort.name') }}</option>
            <option value="created_at" {{ ($filters['sort'] ?? '') == 'created_at' ? 'selected' : '' }}>{{ __('projects.sort.date') }}</option>
            <option value="priority" {{ ($filters['sort'] ?? '') == 'priority' ? 'selected' : '' }}>{{ __('projects.sort.priority') }}</option>
            <option value="progress" {{ ($filters['sort'] ?? '') == 'progress' ? 'selected' : '' }}>{{ __('projects.sort.progress') }}</option>
        </select>
    </div>
    
    <!-- View Mode Toggle -->
    <div class="mt-4 flex items-center justify-between">
        <div class="flex items-center space-x-2">
            <span class="text-sm font-medium text-gray-700">{{ __('projects.view_mode') }}:</span>
            <button class="px-3 py-1 text-sm font-medium rounded-md {{ ($filters['view_mode'] ?? 'table') == 'table' ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:text-gray-700' }}">
                <i class="fas fa-table mr-1"></i>{{ __('projects.table_view') }}
            </button>
            <button class="px-3 py-1 text-sm font-medium rounded-md {{ ($filters['view_mode'] ?? 'table') == 'cards' ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:text-gray-700' }}">
                <i class="fas fa-th-large mr-1"></i>{{ __('projects.card_view') }}
            </button>
        </div>
        
        @if(count($filters) > 0)
        <div class="flex items-center space-x-2">
            <span class="text-sm text-gray-500">{{ __('projects.filters_applied', ['count' => count(array_filter($filters))]) }}</span>
            <button class="text-sm text-blue-600 hover:text-blue-800">{{ __('projects.clear_filters') }}</button>
        </div>
        @endif
    </div>
</div>
