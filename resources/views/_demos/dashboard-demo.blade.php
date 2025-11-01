{{-- Dashboard Demo Page --}}
{{-- Testing the new standardized dashboard --}}

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard Demo - ZenaManage</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="bg-gray-50">
    
    {{-- Mock User Data --}}
    @php
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
        
        // Mock dashboard data
        $totalProjects = 12;
        $totalTasks = 45;
        $totalTeamMembers = 8;
        $budgetUsed = 125000;
        
        $projectsChange = '+2';
        $tasksChange = '+5';
        $teamChange = '+1';
        $budgetChange = '+15000';
        
        $recentProjects = collect([
            (object) ['id' => 1, 'name' => 'Website Redesign', 'status' => 'active', 'budget_total' => 50000, 'updated_at' => now()],
            (object) ['id' => 2, 'name' => 'Mobile App', 'status' => 'active', 'budget_total' => 75000, 'updated_at' => now()->subHours(2)],
            (object) ['id' => 3, 'name' => 'API Integration', 'status' => 'completed', 'budget_total' => 30000, 'updated_at' => now()->subDays(1)]
        ]);
        
        $recentTasks = collect([
            (object) ['id' => 1, 'title' => 'Design mockups', 'status' => 'in_progress', 'priority' => 'high', 'updated_at' => now()],
            (object) ['id' => 2, 'title' => 'Setup database', 'status' => 'completed', 'priority' => 'medium', 'updated_at' => now()->subHours(1)],
            (object) ['id' => 3, 'title' => 'Write documentation', 'status' => 'pending', 'priority' => 'low', 'updated_at' => now()->subDays(1)]
        ]);
        
        $teamMembers = collect([
            (object) ['id' => 1, 'name' => 'Alice Johnson', 'role' => 'developer', 'last_login_at' => now()],
            (object) ['id' => 2, 'name' => 'Bob Smith', 'role' => 'designer', 'last_login_at' => now()->subHours(1)],
            (object) ['id' => 3, 'name' => 'Carol Davis', 'role' => 'manager', 'last_login_at' => now()->subHours(2)]
        ]);
        
        $recentActivity = collect([
            (object) ['description' => 'John created a new project "Website Redesign"', 'created_at' => now()],
            (object) ['description' => 'Alice completed task "Design mockups"', 'created_at' => now()->subHours(1)],
            (object) ['description' => 'Bob uploaded document "Wireframes.pdf"', 'created_at' => now()->subHours(2)]
        ]);
        
        $systemAlerts = collect([
            ['type' => 'warning', 'title' => 'Approaching Deadline', 'message' => '2 project deadlines approaching'],
            ['type' => 'info', 'title' => 'System Update', 'message' => 'Scheduled maintenance on Sunday 2AM']
        ]);
        
        $projectProgressData = [
            ['label' => 'Completed', 'value' => 60],
            ['label' => 'In Progress', 'value' => 30],
            ['label' => 'Not Started', 'value' => 10]
        ];
        
        $taskCompletionData = [
            ['date' => '2024-01-01', 'completed' => 5, 'total' => 10],
            ['date' => '2024-01-02', 'completed' => 8, 'total' => 12],
            ['date' => '2024-01-03', 'completed' => 12, 'total' => 15],
            ['date' => '2024-01-04', 'completed' => 15, 'total' => 18],
            ['date' => '2024-01-05', 'completed' => 18, 'total' => 20]
        ];
    @endphp
    
    {{-- Mock Auth for demo --}}
    <script>
        // Mock authentication for demo
        window.mockAuth = {
            user: @json($mockUser),
            tenant: @json($mockTenant)
        };
    </script>
    
    {{-- Dashboard Demo --}}
    <div class="min-h-screen">
        <h1 class="text-3xl font-bold text-center py-8 bg-white shadow-sm">Dashboard Demo - Phase 2</h1>
        
        {{-- Render the new dashboard --}}
        <div class="dashboard-demo">
            @include('app.dashboard.index-new', [
                'totalProjects' => $totalProjects,
                'totalTasks' => $totalTasks,
                'totalTeamMembers' => $totalTeamMembers,
                'budgetUsed' => $budgetUsed,
                'projectsChange' => $projectsChange,
                'tasksChange' => $tasksChange,
                'teamChange' => $teamChange,
                'budgetChange' => $budgetChange,
                'recentProjects' => $recentProjects,
                'recentTasks' => $recentTasks,
                'teamMembers' => $teamMembers,
                'recentActivity' => $recentActivity,
                'systemAlerts' => $systemAlerts,
                'projectProgressData' => $projectProgressData,
                'taskCompletionData' => $taskCompletionData
            ])
        </div>
    </div>
    
    {{-- Theme Toggle --}}
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
        
        // Mock refresh function
        function refreshDashboard() {
            alert('Dashboard refresh triggered!');
        }
        
        // Mock invite function
        function inviteTeamMember() {
            alert('Invite team member modal would open here!');
        }
    </script>
</body>
</html>
