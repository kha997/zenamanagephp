<?php $__env->startSection('title', 'Projects Dashboard'); ?>

<?php $__env->startSection('content'); ?>
<div class="min-h-screen bg-gray-50">
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div x-data="projectsDashboard()">
            <!-- Projects Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="dashboard-card metric-card blue p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Total Projects</p>
                            <p class="text-3xl font-bold text-white" x-text="stats?.totalProjects || <?php echo e($mockProjects->count()); ?>"></p>
                            <p class="text-white/80 text-sm">
                                <span x-text="stats?.activeProjects || <?php echo e($mockProjects->where('status', 'in_progress')->count()); ?>"></span> active
                            </p>
                        </div>
                        <i class="fas fa-project-diagram text-4xl text-white/60"></i>
                    </div>
                </div>

                <div class="dashboard-card metric-card green p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">In Progress</p>
                            <p class="text-3xl font-bold text-white" x-text="stats?.inProgress || <?php echo e($mockProjects->where('status', 'in_progress')->count()); ?>"></p>
                            <p class="text-white/80 text-sm">
                                <span x-text="stats?.completedThisWeek || 3"></span> completed this week
                            </p>
                        </div>
                        <i class="fas fa-play text-4xl text-white/60"></i>
                    </div>
                </div>

                <div class="dashboard-card metric-card orange p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">On Hold</p>
                            <p class="text-3xl font-bold text-white" x-text="stats?.onHold || <?php echo e($mockProjects->where('status', 'on_hold')->count()); ?>"></p>
                            <div class="flex space-x-2 mt-1">
                                <p class="text-white/80 text-sm">
                                    <span x-text="stats?.needsReview || 1"></span> needs review
                                </p>
                            </div>
                        </div>
                        <i class="fas fa-pause text-4xl text-white/60"></i>
                    </div>
                </div>

                <div class="dashboard-card metric-card purple p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Completed</p>
                            <p class="text-3xl font-bold text-white" x-text="stats?.completed || <?php echo e($mockProjects->where('status', 'completed')->count()); ?>"></p>
                            <p class="text-white/80 text-sm">
                                <span x-text="stats?.completionRate || 85"></span>% completion rate
                            </p>
                        </div>
                        <i class="fas fa-check-circle text-4xl text-white/60"></i>
                    </div>
                </div>
            </div>

            <!-- Projects Overview Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Recent Projects -->
                <div class="dashboard-card p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Recent Projects</h3>
                        <div class="flex items-center gap-2">
                            <button class="zena-btn zena-btn-primary zena-btn-sm" @click="createProject()">
                                <i class="fas fa-plus mr-2"></i>
                                New Project
                            </button>
                            <a href="/tasks" class="zena-btn zena-btn-outline zena-btn-sm">
                                <i class="fas fa-tasks mr-2"></i>
                                View Tasks
                            </a>
                            <a href="/documents" class="zena-btn zena-btn-outline zena-btn-sm">
                                <i class="fas fa-file-alt mr-2"></i>
                                View Documents
                            </a>
                            <div class="relative group">
                                <button class="zena-btn zena-btn-outline zena-btn-sm">
                                    <i class="fas fa-download mr-2"></i>
                                    Export
                                    <i class="fas fa-chevron-down ml-2 text-xs"></i>
                                </button>
                                
                                <!-- Dropdown Menu -->
                                <div class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-10">
                                    <div class="py-1">
                                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" @click="exportProjects('excel')">
                                            <i class="fas fa-file-excel mr-2 text-green-600"></i>
                                            Excel (.xlsx)
                                        </a>
                                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" @click="exportProjects('pdf')">
                                            <i class="fas fa-file-pdf mr-2 text-red-600"></i>
                                            PDF (.pdf)
                                        </a>
                                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" @click="exportProjects('csv')">
                                            <i class="fas fa-file-csv mr-2 text-blue-600"></i>
                                            CSV (.csv)
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <?php $__empty_1 = true; $__currentLoopData = $mockProjects->take(4); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-project-diagram text-blue-600"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900"><?php echo e($project['name']); ?></h4>
                                    <p class="text-sm text-gray-600"><?php echo e(\Carbon\Carbon::parse($project['start_date'])->format('M d, Y')); ?></p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <?php
                                    $statusConfig = [
                                        'planning' => ['color' => 'bg-blue-100 text-blue-800', 'text' => 'Planning'],
                                        'in_progress' => ['color' => 'bg-green-100 text-green-800', 'text' => 'Active'],
                                        'on_hold' => ['color' => 'bg-orange-100 text-orange-800', 'text' => 'On Hold'],
                                        'completed' => ['color' => 'bg-gray-100 text-gray-800', 'text' => 'Completed'],
                                    ];
                                    $status = $statusConfig[$project['status']] ?? ['color' => 'bg-gray-100 text-gray-800', 'text' => 'Unknown'];
                                ?>
                                <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo e($status['color']); ?>">
                                    <?php echo e($status['text']); ?>

                                </span>
                                <span class="text-sm font-medium text-gray-900"><?php echo e($project['progress'] ?? 0); ?>%</span>
                            </div>
                        </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-project-diagram text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No projects found</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Project Performance -->
                <div class="dashboard-card p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Project Performance</h3>
                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full">
                            <i class="fas fa-arrow-up mr-1"></i>+8.2%
                        </span>
                    </div>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Average Completion Time</span>
                            <span class="text-lg font-semibold text-gray-900">45 days</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Budget Utilization</span>
                            <span class="text-lg font-semibold text-gray-900">78%</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Client Satisfaction</span>
                            <span class="text-lg font-semibold text-gray-900">4.8/5</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-600 h-2 rounded-full" style="width: 78%"></div>
                        </div>
                        <p class="text-xs text-gray-500">78% of projects completed on time</p>
                    </div>
                </div>
            </div>

            <!-- Related Items Section -->
            <div class="dashboard-card p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Quick Access</h3>
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-500">Navigate to related sections</span>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Tasks Card -->
                    <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer" onclick="window.location.href='/tasks'">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-semibold text-blue-900">My Tasks</h4>
                                <p class="text-sm text-blue-700">View assigned tasks</p>
                                <div class="mt-2 flex items-center text-sm text-blue-600">
                                    <i class="fas fa-tasks mr-2"></i>
                                    <span>5 active tasks</span>
                                </div>
                            </div>
                            <i class="fas fa-tasks text-3xl text-blue-500"></i>
                        </div>
                    </div>
                    
                    <!-- Documents Card -->
                    <div class="bg-gradient-to-r from-green-50 to-green-100 rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer" onclick="window.location.href='/documents'">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-semibold text-green-900">Project Documents</h4>
                                <p class="text-sm text-green-700">Manage project files</p>
                                <div class="mt-2 flex items-center text-sm text-green-600">
                                    <i class="fas fa-file-alt mr-2"></i>
                                    <span>12 documents</span>
                                </div>
                            </div>
                            <i class="fas fa-file-alt text-3xl text-green-500"></i>
                        </div>
                    </div>
                    
                    <!-- Team Card -->
                    <div class="bg-gradient-to-r from-purple-50 to-purple-100 rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer" onclick="window.location.href='/team'">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-semibold text-purple-900">Project Team</h4>
                                <p class="text-sm text-purple-700">View team members</p>
                                <div class="mt-2 flex items-center text-sm text-purple-600">
                                    <i class="fas fa-users mr-2"></i>
                                    <span>8 members</span>
                                </div>
                            </div>
                            <i class="fas fa-users text-3xl text-purple-500"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- All Projects Table -->
            <div class="dashboard-card p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">All Projects</h3>
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <input type="text" placeholder="Search projects..." class="zena-input zena-input-sm" x-model="searchQuery">
                            <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                        <select class="zena-select zena-select-sm" x-model="statusFilter">
                            <option value="">All Status</option>
                            <option value="planning">Planning</option>
                            <option value="in_progress">In Progress</option>
                            <option value="on_hold">On Hold</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="zena-table">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Status</th>
                                <th>Progress</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Team</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__empty_1 = true; $__currentLoopData = $mockProjects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr>
                                <td>
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-project-diagram text-blue-600 text-sm"></i>
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900"><?php echo e($project['name']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo e(Str::limit($project['description'], 50)); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                        $statusConfig = [
                                            'planning' => ['color' => 'zena-badge-info', 'text' => 'Planning'],
                                            'in_progress' => ['color' => 'zena-badge-success', 'text' => 'In Progress'],
                                            'on_hold' => ['color' => 'zena-badge-warning', 'text' => 'On Hold'],
                                            'completed' => ['color' => 'zena-badge-neutral', 'text' => 'Completed'],
                                        ];
                                        $status = $statusConfig[$project['status']] ?? ['color' => 'zena-badge-neutral', 'text' => 'Unknown'];
                                    ?>
                                    <span class="zena-badge <?php echo e($status['color']); ?>">
                                        <?php echo e($status['text']); ?>

                                    </span>
                                </td>
                                <td>
                                    <div class="flex items-center space-x-2">
                                        <div class="zena-progress w-16">
                                            <div class="zena-progress-bar zena-progress-bar-success" style="width: <?php echo e($project['progress'] ?? 0); ?>%"></div>
                                        </div>
                                        <span class="text-sm font-medium text-gray-900"><?php echo e($project['progress'] ?? 0); ?>%</span>
                                    </div>
                                </td>
                                <td class="text-sm text-gray-600"><?php echo e(\Carbon\Carbon::parse($project['start_date'])->format('M d, Y')); ?></td>
                                <td class="text-sm text-gray-600"><?php echo e(\Carbon\Carbon::parse($project['end_date'])->format('M d, Y')); ?></td>
                                <td>
                                    <div class="flex -space-x-2">
                                        <div class="w-6 h-6 bg-blue-500 rounded-full flex items-center justify-center text-white text-xs font-medium">A</div>
                                        <div class="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center text-white text-xs font-medium">B</div>
                                        <div class="w-6 h-6 bg-purple-500 rounded-full flex items-center justify-center text-white text-xs font-medium">C</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex items-center space-x-2">
                                        <button class="zena-btn zena-btn-outline zena-btn-sm" title="View">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="zena-btn zena-btn-outline zena-btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="zena-btn zena-btn-outline zena-btn-sm zena-btn-danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="7" class="text-center py-8">
                                    <i class="fas fa-project-diagram text-4xl text-gray-300 mb-4"></i>
                                    <p class="text-gray-500">No projects found</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-6 flex items-center justify-between">
                    <div class="flex items-center text-sm text-gray-700">
                        <span>Showing</span>
                        <select class="mx-2 border border-gray-300 rounded-md px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <span>of <span class="font-medium"><?php echo e($mockProjects->count()); ?></span> projects</span>
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <button class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-700 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                            <i class="fas fa-chevron-left mr-1"></i>
                            Previous
                        </button>
                        
                        <div class="flex items-center space-x-1">
                            <button class="px-3 py-2 text-sm font-medium text-white bg-blue-600 border border-blue-600 rounded-md hover:bg-blue-700">
                                1
                            </button>
                            <button class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-900">
                                2
                            </button>
                            <button class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-900">
                                3
                            </button>
                            <span class="px-2 py-2 text-sm text-gray-500">...</span>
                            <button class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-900">
                                10
                            </button>
                        </div>
                        
                        <button class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-900">
                            Next
                            <i class="fas fa-chevron-right ml-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
function projectsDashboard() {
    return {
        searchQuery: '',
        statusFilter: '',
        
        stats: {
            totalProjects: <?php echo e($mockProjects->count()); ?>,
            activeProjects: <?php echo e($mockProjects->where('status', 'in_progress')->count()); ?>,
            inProgress: <?php echo e($mockProjects->where('status', 'in_progress')->count()); ?>,
            onHold: <?php echo e($mockProjects->where('status', 'on_hold')->count()); ?>,
            completed: <?php echo e($mockProjects->where('status', 'completed')->count()); ?>,
            completedThisWeek: 3,
            needsReview: 1,
            completionRate: 85
        },
        
        exportProjects(format) {
            console.log('Exporting projects in', format, 'format');
            // Implementation for export functionality
        },
        
        createProject() {
            console.log('Creating new project');
            // Implementation for create project functionality
        }
    }
}
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/projects/index.blade.php ENDPATH**/ ?>