


<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title>Tasks Demo - ZenaManage</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo e(asset('css/app.css')); ?>">
</head>
<body class="bg-gray-50">
    
    
    <?php
        $mockTasks = collect([
            (object) [
                'id' => '01k5kzpfwd618xmwdwq3rej3jz',
                'title' => 'Design Homepage Mockup',
                'description' => 'Create wireframes and mockups for the new homepage design',
                'status' => 'in_progress',
                'priority' => 'high',
                'project' => (object) ['id' => '1', 'name' => 'Website Redesign'],
                'assignee' => (object) ['id' => '1', 'name' => 'Alice Johnson'],
                'due_date' => now()->addDays(3),
                'estimated_hours' => 8,
                'actual_hours' => 5,
                'progress' => 60,
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subHours(2)
            ],
            (object) [
                'id' => '01k5kzpfwd618xmwdwq3rej3jz2',
                'title' => 'Setup Database Schema',
                'description' => 'Create database tables and relationships for user management',
                'status' => 'completed',
                'priority' => 'medium',
                'project' => (object) ['id' => '2', 'name' => 'Mobile App Development'],
                'assignee' => (object) ['id' => '2', 'name' => 'Bob Smith'],
                'due_date' => now()->subDays(2),
                'estimated_hours' => 12,
                'actual_hours' => 10,
                'progress' => 100,
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(2)
            ],
            (object) [
                'id' => '01k5kzpfwd618xmwdwq3rej3jz3',
                'title' => 'Write API Documentation',
                'description' => 'Document all REST API endpoints with examples',
                'status' => 'pending',
                'priority' => 'low',
                'project' => (object) ['id' => '1', 'name' => 'Website Redesign'],
                'assignee' => (object) ['id' => '3', 'name' => 'Carol Davis'],
                'due_date' => now()->addDays(7),
                'estimated_hours' => 6,
                'actual_hours' => 0,
                'progress' => 0,
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(1)
            ],
            (object) [
                'id' => '01k5kzpfwd618xmwdwq3rej3jz4',
                'title' => 'Fix Login Bug',
                'description' => 'Resolve authentication issue on mobile devices',
                'status' => 'urgent',
                'priority' => 'urgent',
                'project' => (object) ['id' => '2', 'name' => 'Mobile App Development'],
                'assignee' => (object) ['id' => '4', 'name' => 'David Wilson'],
                'due_date' => now()->subDays(1), // Overdue
                'estimated_hours' => 4,
                'actual_hours' => 2,
                'progress' => 50,
                'created_at' => now()->subDays(7),
                'updated_at' => now()->subHours(1)
            ],
            (object) [
                'id' => '01k5kzpfwd618xmwdwq3rej3jz5',
                'title' => 'User Testing Session',
                'description' => 'Conduct usability testing with 5 users',
                'status' => 'on_hold',
                'priority' => 'medium',
                'project' => (object) ['id' => '1', 'name' => 'Website Redesign'],
                'assignee' => (object) ['id' => '5', 'name' => 'Eva Brown'],
                'due_date' => now()->addDays(14),
                'estimated_hours' => 10,
                'actual_hours' => 0,
                'progress' => 0,
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subHours(4)
            ]
        ]);
        
        $mockProjects = collect([
            (object) ['id' => '1', 'name' => 'Website Redesign'],
            (object) ['id' => '2', 'name' => 'Mobile App Development'],
            (object) ['id' => '3', 'name' => 'API Integration']
        ]);
        
        $mockUsers = collect([
            (object) ['id' => '1', 'name' => 'Alice Johnson'],
            (object) ['id' => '2', 'name' => 'Bob Smith'],
            (object) ['id' => '3', 'name' => 'Carol Davis'],
            (object) ['id' => '4', 'name' => 'David Wilson'],
            (object) ['id' => '5', 'name' => 'Eva Brown']
        ]);
        
        $mockUser = (object) [
            'id' => '01k5kzpfwd618xmwdwq3rej3jz',
            'name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'tenant_id' => '01k5kzpfwd618xmwdwq3rej3jz',
            'role' => 'project_manager'
        ];
        
        $mockTenant = (object) [
            'id' => '01k5kzpfwd618xmwdwq3rej3jz',
            'name' => 'Acme Corp',
            'slug' => 'acme-corp'
        ];
    ?>
    
    
    <script>
        window.mockAuth = {
            user: <?php echo json_encode($mockUser, 15, 512) ?>,
            tenant: <?php echo json_encode($mockTenant, 15, 512) ?>
        };
    </script>
    
    
    <div class="min-h-screen">
        <h1 class="text-3xl font-bold text-center py-8 bg-white shadow-sm">Tasks Demo - Phase 2</h1>
        
        
        <div class="tasks-demo">
            <?php echo $__env->make('app.tasks.index-new', [
                'tasks' => $mockTasks,
                'projects' => $mockProjects,
                'users' => $mockUsers,
                'user' => $mockUser,
                'tenant' => $mockTenant
            ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </div>
    </div>
    
    
    <div class="fixed bottom-4 left-4 z-50">
        <button onclick="toggleTheme()" 
                class="btn bg-gray-800 text-white px-4 py-2 rounded-full shadow-lg hover:bg-gray-700">
            <i class="fas fa-moon mr-2"></i>
            Toggle Theme
        </button>
    </div>
    
    <script>
        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', newTheme);
        }
        
        // Mock functions for demo
        function refreshTasks() {
            alert('Tasks refresh triggered!');
        }
        
        function exportTasks() {
            alert('Export tasks functionality would be implemented here!');
        }
        
        function createTask() {
            alert('Create task modal would open here!');
        }
        
        function deleteTask(taskId) {
            alert('Delete task: ' + taskId);
        }
        
        function bulkChangeStatus() {
            alert('Bulk change status functionality would be implemented here!');
        }
        
        function bulkAssign() {
            alert('Bulk assign functionality would be implemented here!');
        }
        
        function bulkExport() {
            alert('Bulk export functionality would be implemented here!');
        }
        
        function bulkArchive() {
            alert('Bulk archive functionality would be implemented here!');
        }
        
        function openModal(modalId) {
            alert('Open modal: ' + modalId);
        }
        
        function closeModal(modalId) {
            alert('Close modal: ' + modalId);
        }
        
        // Mock auth token
        function getAuthToken() {
            return 'mock-token';
        }
    </script>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/_demos/tasks-demo.blade.php ENDPATH**/ ?>