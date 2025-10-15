


<?php
    $user = Auth::user();
    $tenant = $user->tenant ?? null;
    
    // Prepare KPIs data
    $kpis = [
        [
            'key' => 'projects',
            'label' => 'Total Projects',
            'value' => $totalProjects ?? 0,
            'change' => $projectsChange ?? 0,
            'icon' => 'fas fa-project-diagram',
            'color' => 'blue'
        ],
        [
            'key' => 'tasks',
            'label' => 'Active Tasks',
            'value' => $totalTasks ?? 0,
            'change' => $tasksChange ?? 0,
            'icon' => 'fas fa-tasks',
            'color' => 'green'
        ],
        [
            'key' => 'team',
            'label' => 'Team Members',
            'value' => $totalTeamMembers ?? 0,
            'change' => $teamChange ?? 0,
            'icon' => 'fas fa-users',
            'color' => 'purple'
        ],
        [
            'key' => 'budget',
            'label' => 'Budget Used',
            'value' => $budgetUsed ?? 0,
            'change' => $budgetChange ?? 0,
            'icon' => 'fas fa-dollar-sign',
            'color' => 'yellow'
        ]
    ];
    
    // Prepare charts data
    $charts = [
        [
            'key' => 'project-progress',
            'type' => 'doughnut',
            'title' => 'Project Progress',
            'data' => $projectProgressData ?? []
        ],
        [
            'key' => 'task-distribution',
            'type' => 'line',
            'title' => 'Task Completion Trend',
            'data' => $taskCompletionData ?? []
        ]
    ];
    
    // Prepare recent projects data
    $recentProjectsData = $recentProjects ?? collect([]);
    
    // Prepare recent activity data
    $recentActivityData = $recentActivity ?? collect([]);
    
    // Prepare alerts data
    $alertsData = $alerts ?? collect([]);
    
    // Prepare notifications data
    $notificationsData = $notifications ?? collect([]);
    
    // Breadcrumbs
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => null]
    ];
    
    // Page actions
    $actions = '
        <div class="flex items-center space-x-3">
            <button onclick="refreshDashboard()" class="btn bg-gray-100 text-gray-700 hover:bg-gray-200">
                <i class="fas fa-sync-alt mr-2"></i>Refresh
            </button>
            <a href="' . route('app.projects.create') . '" class="btn bg-blue-600 text-white hover:bg-blue-700">
                <i class="fas fa-plus mr-2"></i>New Project
            </a>
        </div>
    ';
?>

<?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.dashboard-shell','data' => ['variant' => 'app','user' => $user,'tenant' => $tenant,'kpis' => $kpis,'charts' => $charts,'recentProjects' => $recentProjectsData,'recentActivity' => $recentActivityData,'alerts' => $alertsData,'notifications' => $notificationsData,'title' => 'Dashboard','subtitle' => 'Welcome back, ' . ($user->first_name ?? $user->name ?? 'User'),'breadcrumbs' => $breadcrumbs,'actions' => $actions]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.dashboard-shell'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'app','user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($user),'tenant' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($tenant),'kpis' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($kpis),'charts' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($charts),'recent-projects' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($recentProjectsData),'recent-activity' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($recentActivityData),'alerts' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($alertsData),'notifications' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($notificationsData),'title' => 'Dashboard','subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Welcome back, ' . ($user->first_name ?? $user->name ?? 'User')),'breadcrumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($breadcrumbs),'actions' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($actions)]); ?>
    
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8">
        
        <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.card-standardized','data' => ['title' => 'Team Status','subtitle' => 'Current team activity and availability']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.card-standardized'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Team Status','subtitle' => 'Current team activity and availability']); ?>
            
            <div class="space-y-4">
                <?php if(isset($teamMembers) && $teamMembers->count() > 0): ?>
                    <?php $__currentLoopData = $teamMembers->take(5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center">
                                    <span class="text-white text-sm font-medium">
                                        <?php echo e(substr($member->name, 0, 1)); ?>

                                    </span>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900"><?php echo e($member->name); ?></h4>
                                    <p class="text-sm text-gray-500"><?php echo e($member->role ?? 'Member'); ?></p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                                <span class="text-sm text-gray-500">Online</span>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    
                    <?php if($teamMembers->count() > 5): ?>
                        <div class="text-center">
                            <a href="<?php echo e(route('app.team.index')); ?>" class="text-blue-600 hover:text-blue-800 font-medium">
                                View all <?php echo e($teamMembers->count()); ?> team members →
                            </a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.empty-state','data' => ['icon' => 'fas fa-users','title' => 'No team members','description' => 'Invite team members to start collaborating.','actionText' => 'Invite Team Member','actionIcon' => 'fas fa-user-plus','actionHandler' => 'inviteTeamMember']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'fas fa-users','title' => 'No team members','description' => 'Invite team members to start collaborating.','action-text' => 'Invite Team Member','action-icon' => 'fas fa-user-plus','action-handler' => 'inviteTeamMember']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
                <?php endif; ?>
            </div>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
        
        
        <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.card-standardized','data' => ['title' => 'Quick Actions','subtitle' => 'Common tasks and shortcuts']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.card-standardized'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Quick Actions','subtitle' => 'Common tasks and shortcuts']); ?>
            
            <div class="grid grid-cols-2 gap-4">
                <a href="<?php echo e(route('app.projects.create')); ?>" 
                   class="flex flex-col items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                    <i class="fas fa-project-diagram text-2xl text-blue-600 mb-2"></i>
                    <span class="font-medium text-blue-900">New Project</span>
                </a>
                
                <a href="<?php echo e(route('app.tasks.create')); ?>" 
                   class="flex flex-col items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                    <i class="fas fa-tasks text-2xl text-green-600 mb-2"></i>
                    <span class="font-medium text-green-900">New Task</span>
                </a>
                
                <a href="<?php echo e(route('app.clients.create')); ?>" 
                   class="flex flex-col items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                    <i class="fas fa-user-plus text-2xl text-purple-600 mb-2"></i>
                    <span class="font-medium text-purple-900">Add Client</span>
                </a>
                
                <a href="<?php echo e(route('app.documents.index')); ?>" 
                   class="flex flex-col items-center p-4 bg-orange-50 rounded-lg hover:bg-orange-100 transition-colors">
                    <i class="fas fa-file-alt text-2xl text-orange-600 mb-2"></i>
                    <span class="font-medium text-orange-900">Documents</span>
                </a>
            </div>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
    </div>
    
    
    <?php if(isset($systemAlerts) && $systemAlerts->count() > 0): ?>
        <div class="mt-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">System Alerts</h3>
            <div class="space-y-4">
                <?php $__currentLoopData = $systemAlerts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $alert): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.alert-standardized','data' => ['type' => $alert['type'],'title' => $alert['title'],'message' => $alert['message'],'dismissible' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.alert-standardized'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($alert['type']),'title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($alert['title']),'message' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($alert['message']),'dismissible' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>
    <?php endif; ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>

<?php $__env->startPush('scripts'); ?>
<script>
function refreshDashboard() {
    // Refresh dashboard data
    window.location.reload();
}

function inviteTeamMember() {
    // Open invite team member modal or redirect
    window.location.href = '<?php echo e(route("app.team.index")); ?>';
}

// Auto-refresh dashboard every 5 minutes
setInterval(function() {
    // Optionally refresh specific data without full page reload
    console.log('Dashboard auto-refresh triggered');
}, 300000);
</script>
<?php $__env->stopPush(); ?>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/app/dashboard/index.blade.php ENDPATH**/ ?>