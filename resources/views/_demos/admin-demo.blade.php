{{-- Admin Pages Demo - Week 2 Implementation --}}
{{-- Testing the new standardized admin pages --}}

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Pages Demo - ZenaManage</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="bg-gray-50">
    
    {{-- Mock Admin Data --}}
    @php
        $mockUsers = collect([
            (object) [
                'id' => '01k5kzpfwd618xmwdwq3rej3jz',
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'role' => 'super_admin',
                'is_active' => true,
                'tenant' => (object) ['id' => '1', 'name' => 'Acme Corp'],
                'last_login_at' => now()->subHours(2),
                'created_at' => now()->subDays(30),
                'updated_at' => now()->subDays(1)
            ],
            (object) [
                'id' => '01k5kzpfwd618xmwdwq3rej3jz',
                'name' => 'Alice Johnson',
                'email' => 'alice@example.com',
                'role' => 'project_manager',
                'is_active' => true,
                'tenant' => (object) ['id' => '1', 'name' => 'Acme Corp'],
                'last_login_at' => now()->subHours(5),
                'created_at' => now()->subDays(25),
                'updated_at' => now()->subDays(2)
            ],
            (object) [
                'id' => '01k5kzpfwd618xmwdwq3rej3jz',
                'name' => 'Bob Smith',
                'email' => 'bob@example.com',
                'role' => 'member',
                'is_active' => false,
                'tenant' => (object) ['id' => '2', 'name' => 'TechStart Inc'],
                'last_login_at' => now()->subDays(7),
                'created_at' => now()->subDays(20),
                'updated_at' => now()->subDays(3)
            ]
        ]);
        
        $mockTenants = collect([
            (object) [
                'id' => '01k5kzpfwd618xmwdwq3rej3jz',
                'name' => 'Acme Corp',
                'slug' => 'acme-corp',
                'domain' => 'acme.example.com',
                'status' => 'active',
                'plan' => 'premium',
                'trial_ends_at' => null,
                'created_at' => now()->subDays(30),
                'updated_at' => now()->subDays(1)
            ],
            (object) [
                'id' => '01k5kzpfwd618xmwdwq3rej3jz',
                'name' => 'TechStart Inc',
                'slug' => 'techstart-inc',
                'domain' => 'techstart.example.com',
                'status' => 'trial',
                'plan' => 'basic',
                'trial_ends_at' => now()->addDays(14),
                'created_at' => now()->subDays(15),
                'updated_at' => now()->subDays(2)
            ],
            (object) [
                'id' => '01k5kzpfwd618xmwdwq3rej3jz',
                'name' => 'Global Solutions',
                'slug' => 'global-solutions',
                'domain' => 'global.example.com',
                'status' => 'suspended',
                'plan' => 'enterprise',
                'trial_ends_at' => null,
                'created_at' => now()->subDays(45),
                'updated_at' => now()->subDays(5)
            ]
        ]);
        
        $mockAdminUser = (object) [
            'id' => '01k5kzpfwd618xmwdwq3rej3jz',
            'name' => 'Super Admin',
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin@example.com',
            'tenant_id' => null,
            'role' => 'super_admin'
        ];
        
        $mockSystemMetrics = [
            'database_performance' => 98.5,
            'cache_hit_rate' => 94.2,
            'queue_processing' => 99.1,
            'api_response_time' => 245,
            'error_rate' => 0.02,
            'active_sessions' => 15
        ];
        
        $mockRecentActivities = collect([
            [
                'id' => '1',
                'type' => 'user',
                'title' => 'New user registered',
                'description' => 'User john@example.com registered',
                'timestamp' => now()->subMinutes(2),
                'user' => 'System',
                'tenant' => 'Acme Corp',
                'severity' => 'info'
            ],
            [
                'id' => '2',
                'type' => 'tenant',
                'title' => 'New tenant created',
                'description' => 'Tenant "TechStart Inc" created',
                'timestamp' => now()->subMinutes(5),
                'user' => 'Admin',
                'tenant' => 'TechStart Inc',
                'severity' => 'info'
            ],
            [
                'id' => '3',
                'type' => 'security',
                'title' => 'Security alert resolved',
                'description' => 'Failed login attempt blocked',
                'timestamp' => now()->subMinutes(10),
                'user' => 'Security System',
                'tenant' => 'N/A',
                'severity' => 'warning'
            ]
        ]);
    @endphp
    
    {{-- Mock Auth for demo --}}
    <script>
        window.mockAuth = {
            user: @json($mockAdminUser),
            tenant: null
        };
    </script>
    
    {{-- Admin Demo Navigation --}}
    <div class="min-h-screen">
        <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <h1 class="text-2xl font-bold text-gray-900">Admin Pages Demo - Week 2</h1>
                    <div class="flex items-center space-x-4">
                        <button onclick="toggleTheme()" class="btn bg-gray-800 text-white px-4 py-2 rounded-lg shadow-sm hover:bg-gray-700">
                            <i class="fas fa-moon mr-2"></i>Toggle Theme
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        {{-- Demo Navigation Tabs --}}
        <div class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <nav class="flex space-x-8">
                    <button onclick="showDemo('dashboard')" 
                            class="demo-tab py-4 px-1 border-b-2 border-blue-500 text-blue-600 font-medium">
                        <i class="fas fa-tachometer-alt mr-2"></i>Admin Dashboard
                    </button>
                    <button onclick="showDemo('users')" 
                            class="demo-tab py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fas fa-users mr-2"></i>User Management
                    </button>
                    <button onclick="showDemo('tenants')" 
                            class="demo-tab py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fas fa-building mr-2"></i>Tenant Management
                    </button>
                </nav>
            </div>
        </div>
        
        {{-- Demo Content --}}
        <div class="demo-content">
            {{-- Admin Dashboard Demo --}}
            <div id="dashboard-demo" class="demo-panel">
                @include('admin.dashboard-new', [
                    'totalUsers' => $mockUsers->count(),
                    'activeTenants' => $mockTenants->where('status', 'active')->count(),
                    'totalProjects' => 25,
                    'activeAlerts' => 2,
                    'activeSessions' => $mockSystemMetrics['active_sessions'],
                    'recentActivities' => $mockRecentActivities
                ])
            </div>
            
            {{-- Admin Users Demo --}}
            <div id="users-demo" class="demo-panel hidden">
                @include('admin.users.index-new', [
                    'users' => $mockUsers,
                    'tenants' => $mockTenants
                ])
            </div>
            
            {{-- Admin Tenants Demo --}}
            <div id="tenants-demo" class="demo-panel hidden">
                @include('admin.tenants.index-new', [
                    'tenants' => $mockTenants
                ])
            </div>
        </div>
    </div>
    
    <script>
        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', newTheme);
        }
        
        function showDemo(demoType) {
            // Hide all demo panels
            document.querySelectorAll('.demo-panel').forEach(panel => {
                panel.classList.add('hidden');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.demo-tab').forEach(tab => {
                tab.classList.remove('border-blue-500', 'text-blue-600');
                tab.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Show selected demo panel
            document.getElementById(demoType + '-demo').classList.remove('hidden');
            
            // Add active class to selected tab
            const activeTab = event.target;
            activeTab.classList.remove('border-transparent', 'text-gray-500');
            activeTab.classList.add('border-blue-500', 'text-blue-600');
        }
        
        // Mock functions for demo
        function refreshSystemData() {
            alert('System data refresh triggered!');
        }
        
        function exportSystemReport() {
            alert('Export system report functionality would be implemented here!');
        }
        
        function viewAllActivities() {
            alert('View all activities functionality would be implemented here!');
        }
        
        function refreshUsers() {
            alert('Users refresh triggered!');
        }
        
        function exportUsers() {
            alert('Export users functionality would be implemented here!');
        }
        
        function createUser() {
            alert('Create user functionality would be implemented here!');
        }
        
        function viewUser(userId) {
            alert('View user: ' + userId);
        }
        
        function editUser(userId) {
            alert('Edit user: ' + userId);
        }
        
        function resetPassword(userId) {
            alert('Reset password: ' + userId);
        }
        
        function suspendUser(userId) {
            alert('Suspend user: ' + userId);
        }
        
        function deleteUser(userId) {
            alert('Delete user: ' + userId);
        }
        
        function refreshTenants() {
            alert('Tenants refresh triggered!');
        }
        
        function exportTenants() {
            alert('Export tenants functionality would be implemented here!');
        }
        
        function createTenant() {
            alert('Create tenant functionality would be implemented here!');
        }
        
        function viewTenant(tenantId) {
            alert('View tenant: ' + tenantId);
        }
        
        function editTenant(tenantId) {
            alert('Edit tenant: ' + tenantId);
        }
        
        function manageUsers(tenantId) {
            alert('Manage users for tenant: ' + tenantId);
        }
        
        function suspendTenant(tenantId) {
            alert('Suspend tenant: ' + tenantId);
        }
        
        function deleteTenant(tenantId) {
            alert('Delete tenant: ' + tenantId);
        }
        
        function bulkActivate() {
            alert('Bulk activate functionality would be implemented here!');
        }
        
        function bulkSuspend() {
            alert('Bulk suspend functionality would be implemented here!');
        }
        
        function bulkChangeRole() {
            alert('Bulk change role functionality would be implemented here!');
        }
        
        function bulkExport() {
            alert('Bulk export functionality would be implemented here!');
        }
        
        function bulkDelete() {
            alert('Bulk delete functionality would be implemented here!');
        }
        
        function bulkUpgrade() {
            alert('Bulk upgrade functionality would be implemented here!');
        }
        
        function openModal(modalId) {
            alert('Open modal: ' + modalId);
        }
        
        function closeModal(modalId) {
            alert('Close modal: ' + modalId);
        }
        
        function getAuthToken() {
            return 'mock-token';
        }
        
        // Initialize with dashboard demo
        document.addEventListener('DOMContentLoaded', function() {
            showDemo('dashboard');
        });
    </script>
</body>
</html>
