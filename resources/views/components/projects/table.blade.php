{{-- Projects Table Component --}}
@props(['projects' => []])

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('projects.table.name') }}
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('projects.table.status') }}
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('projects.table.priority') }}
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('projects.table.progress') }}
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('projects.table.team') }}
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('projects.table.created') }}
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('projects.table.actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($projects as $project)
                <tr class="hover:bg-gray-50 transition duration-150 ease-in-out">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-project-diagram text-blue-600"></i>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-900">{{ $project->name }}</div>
                                <div class="text-sm text-gray-500">{{ Str::limit($project->description ?? '', 50) }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs font-medium rounded-full {{ $project->status == 'active' ? 'bg-green-100 text-green-800' : ($project->status == 'completed' ? 'bg-blue-100 text-blue-800' : ($project->status == 'on_hold' ? 'bg-yellow-100 text-yellow-800' : ($project->status == 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'))) }}">
                            {{ __('projects.status.' . ($project->status ?? 'active')) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs font-medium rounded-full {{ $project->priority == 'high' ? 'bg-red-100 text-red-800' : ($project->priority == 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') }}">
                            {{ __('projects.priority.' . ($project->priority ?? 'medium')) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $project->progress ?? 0 }}%"></div>
                            </div>
                            <span class="text-sm text-gray-900">{{ $project->progress ?? 0 }}%</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <div class="flex items-center">
                            <i class="fas fa-users mr-1 text-gray-400"></i>
                            {{ $project->team_size ?? 1 }} {{ __('projects.members') }}
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $project->created_at ? $project->created_at->diffForHumans() : __('projects.unknown') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div class="flex items-center justify-end space-x-2">
                            <button class="text-blue-600 hover:text-blue-900" aria-label="{{ __('projects.view_details') }}">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="text-gray-600 hover:text-gray-900" aria-label="{{ __('projects.edit_project') }}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="text-red-600 hover:text-red-900" aria-label="{{ __('projects.delete_project') }}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center">
                        <x-shared.empty-state 
                            :icon="'fas fa-project-diagram'"
                            :title="__('projects.empty.title')"
                            :description="__('projects.empty.description')"
                            :action-text="__('projects.empty.action')"
                            :action-url="route('app.projects.create')" />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
