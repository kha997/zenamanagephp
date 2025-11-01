{{-- Projects Card Grid Component --}}
@props(['projects' => []])

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @forelse($projects as $project)
    <!-- Project Card -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition duration-200">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-project-diagram text-blue-600"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900">{{ $project->name }}</h3>
                        <p class="text-sm text-gray-500">{{ __('projects.status.' . ($project->status ?? 'active')) }}</p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <span class="px-2 py-1 {{ $project->status == 'active' ? 'bg-green-100 text-green-800' : ($project->status == 'completed' ? 'bg-blue-100 text-blue-800' : ($project->status == 'on_hold' ? 'bg-yellow-100 text-yellow-800' : ($project->status == 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'))) }} text-xs font-medium rounded-full">
                        {{ __('projects.status.' . ($project->status ?? 'active')) }}
                    </span>
                    <span class="px-2 py-1 {{ $project->priority == 'high' ? 'bg-red-100 text-red-800' : ($project->priority == 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') }} text-xs font-medium rounded-full">
                        {{ __('projects.priority.' . ($project->priority ?? 'medium')) }}
                    </span>
                </div>
            </div>

            <p class="text-gray-600 text-sm mb-4">{{ Str::limit($project->description ?? __('projects.no_description'), 100) }}</p>

            <div class="mb-4">
                <div class="flex items-center justify-between text-sm text-gray-600 mb-2">
                    <span>{{ __('projects.progress') }}</span>
                    <span>{{ $project->progress ?? 0 }}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $project->progress ?? 0 }}%"></div>
                </div>
            </div>

            <div class="flex items-center justify-between text-sm text-gray-500 mb-4">
                <span><i class="fas fa-users mr-1"></i>{{ $project->team_size ?? 1 }} {{ __('projects.members') }}</span>
                <span><i class="fas fa-calendar mr-1"></i>{{ $project->created_at ? $project->created_at->diffForHumans() : __('projects.unknown') }}</span>
            </div>

            <div class="flex space-x-2">
                <button class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                    {{ __('projects.view_details') }}
                </button>
                <button class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-md transition duration-200" aria-label="{{ __('projects.edit_project') }}">
                    <i class="fas fa-edit"></i>
                </button>
            </div>
        </div>
    </div>
    @empty
    <!-- Empty State -->
    <div class="col-span-full flex flex-col items-center justify-center py-12">
        <x-shared.empty-state 
            :icon="'fas fa-project-diagram'"
            :title="__('projects.empty.title')"
            :description="__('projects.empty.description')"
            :action-text="__('projects.empty.action')"
            :action-url="/frontend/app/projects/create" />
    </div>
    @endforelse
</div>
