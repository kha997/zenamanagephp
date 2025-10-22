{{-- Projects Table --}}
<div class="mt-6">
    @if($tableData->count() > 0)
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <!-- Table Header -->
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900">
                        Projects
                        <span class="text-sm font-normal text-gray-500">({{ $tableData->count() }})</span>
                    </h3>
                </div>
            </div>
            
            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <!-- Table Header -->
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <input type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            </th>
                            @foreach($columns as $column)
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ $column['label'] }}
                                </th>
                            @endforeach
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    
                    <!-- Table Body -->
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($tableData as $item)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="checkbox" value="{{ $item['id'] }}" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                </td>
                                
                                @foreach($columns as $column)
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        @if($column['key'] === 'status')
                                            @php
                                                $status = $item['status'] ?? 'unknown';
                                                $statusClasses = [
                                                    'active' => 'bg-green-100 text-green-800',
                                                    'completed' => 'bg-blue-100 text-blue-800',
                                                    'on_hold' => 'bg-yellow-100 text-yellow-800',
                                                    'cancelled' => 'bg-red-100 text-red-800',
                                                    'unknown' => 'bg-gray-100 text-gray-800'
                                                ];
                                                $statusClass = $statusClasses[$status] ?? $statusClasses['unknown'];
                                            @endphp
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                                                {{ ucfirst($status) }}
                                            </span>
                                        @elseif($column['key'] === 'priority')
                                            @php
                                                $priority = $item['priority'] ?? 'medium';
                                                $priorityClasses = [
                                                    'low' => 'bg-gray-100 text-gray-800',
                                                    'medium' => 'bg-blue-100 text-blue-800',
                                                    'high' => 'bg-orange-100 text-orange-800',
                                                    'urgent' => 'bg-red-100 text-red-800'
                                                ];
                                                $priorityClass = $priorityClasses[$priority] ?? $priorityClasses['medium'];
                                            @endphp
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $priorityClass }}">
                                                {{ ucfirst($priority) }}
                                            </span>
                                        @elseif($column['key'] === 'progress')
                                            @php
                                                $progress = $item['progress'] ?? 0;
                                                $progressColor = $progress >= 80 ? 'bg-green-500' : ($progress >= 50 ? 'bg-yellow-500' : 'bg-red-500');
                                            @endphp
                                            <div class="flex items-center">
                                                <div class="w-full bg-gray-200 rounded-full h-2 mr-2">
                                                    <div class="h-2 rounded-full {{ $progressColor }}" style="width: {{ $progress }}%"></div>
                                                </div>
                                                <span class="text-sm text-gray-600">{{ $progress }}%</span>
                                            </div>
                                        @elseif($column['key'] === 'budget')
                                            <span class="text-sm font-medium text-gray-900">
                                                {{ number_format($item['budget'], 0) }} VND
                                            </span>
                                        @else
                                            {{ $item[$column['key']] ?? '' }}
                                        @endif
                                    </td>
                                @endforeach
                                
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-2">
                                        <a href="{{ route('app.projects.show', $item['id']) }}" 
                                           class="text-blue-600 hover:text-blue-800">
                                            View
                                        </a>
                                        <a href="{{ route('app.projects.edit', $item['id']) }}" 
                                           class="text-gray-600 hover:text-gray-800">
                                            Edit
                                        </a>
                                        <button onclick="deleteProject('{{ $item['id'] }}')" 
                                                class="text-red-600 hover:text-red-800">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        {{-- Empty State --}}
        <x-shared.empty-state 
            icon="fas fa-project-diagram"
            title="No projects found"
            description="Create your first project to get started"
            action-text="Create Project"
            action-handler="openModal('create-project-modal')" />
    @endif
</div>