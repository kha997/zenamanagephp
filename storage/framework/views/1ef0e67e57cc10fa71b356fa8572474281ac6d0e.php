


<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title>Projects Demo - ZenaManage</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo e(asset('css/app.css')); ?>">
</head>
<body class="bg-gray-50">
    
    
    <?php
        $mockProjects = collect([
            (object) [
                'id' => '01k5kzpfwd618xmwdwq3rej3jz',
                'name' => 'Website Redesign',
                'description' => 'Complete redesign of the company website with modern UI/UX',
                'status' => 'active',
                'priority' => 'high',
                'owner' => (object) ['id' => '1', 'name' => 'John Doe'],
                'start_date' => now()->subDays(30),
                'end_date' => now()->addDays(60),
                'budget_total' => 50000,
                'progress' => 65,
                'created_at' => now()->subDays(30),
                'updated_at' => now()->subHours(2)
            ],
            (object) [
                'id' => '01k5kzpfwd618xmwdwq3rej3jz2',
                'name' => 'Mobile App Development',
                'description' => 'Native mobile app for iOS and Android platforms',
                'status' => 'active',
                'priority' => 'medium',
                'owner' => (object) ['id' => '2', 'name' => 'Alice Johnson'],
                'start_date' => now()->subDays(15),
                'end_date' => now()->addDays(90),
                'budget_total' => 75000,
                'progress' => 30,
                'created_at' => now()->subDays(15),
                'updated_at' => now()->subHours(1)
            ],
            (object) [
                'id' => '01k5kzpfwd618xmwdwq3rej3jz3',
                'name' => 'API Integration',
                'description' => 'Integrate third-party APIs for payment processing',
                'status' => 'completed',
                'priority' => 'high',
                'owner' => (object) ['id' => '1', 'name' => 'John Doe'],
                'start_date' => now()->subDays(60),
                'end_date' => now()->subDays(10),
                'budget_total' => 30000,
                'progress' => 100,
                'created_at' => now()->subDays(60),
                'updated_at' => now()->subDays(10)
            ],
            (object) [
                'id' => '01k5kzpfwd618xmwdwq3rej3jz4',
                'name' => 'Database Migration',
                'description' => 'Migrate legacy database to new schema',
                'status' => 'on_hold',
                'priority' => 'low',
                'owner' => (object) ['id' => '3', 'name' => 'Bob Smith'],
                'start_date' => now()->subDays(45),
                'end_date' => now()->addDays(30),
                'budget_total' => 20000,
                'progress' => 20,
                'created_at' => now()->subDays(45),
                'updated_at' => now()->subDays(5)
            ],
            (object) [
                'id' => '01k5kzpfwd618xmwdwq3rej3jz5',
                'name' => 'Security Audit',
                'description' => 'Comprehensive security audit and penetration testing',
                'status' => 'active',
                'priority' => 'urgent',
                'owner' => (object) ['id' => '4', 'name' => 'Carol Davis'],
                'start_date' => now()->subDays(7),
                'end_date' => now()->addDays(14),
                'budget_total' => 15000,
                'progress' => 80,
                'created_at' => now()->subDays(7),
                'updated_at' => now()->subHours(3)
            ]
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
        <h1 class="text-3xl font-bold text-center py-8 bg-white shadow-sm">Projects Demo - Phase 2</h1>
        
        
        <div class="projects-demo">
            <?php echo $__env->make('app.projects.index-new', [
                'projects' => $mockProjects,
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
        function refreshProjects() {
            alert('Projects refresh triggered!');
        }
        
        function exportProjects() {
            alert('Export projects functionality would be implemented here!');
        }
        
        function createProject() {
            alert('Create project modal would open here!');
        }
        
        function deleteProject(projectId) {
            alert('Delete project: ' + projectId);
        }
        
        function bulkArchive() {
            alert('Bulk archive functionality would be implemented here!');
        }
        
        function bulkChangeStatus() {
            alert('Bulk change status functionality would be implemented here!');
        }
        
        function bulkExport() {
            alert('Bulk export functionality would be implemented here!');
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
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/_demos/projects-demo.blade.php ENDPATH**/ ?>