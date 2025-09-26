{{-- Admin Users Management với Layout System --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Users Management - ZenaManage</title>
    
    {{-- ZenaManage CSS Framework --}}
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #3b82f6; --secondary: #10b981; --accent: #8b5cf6; --warning: #f59e0b; --danger: #ef4444;
            --gray-50: #f9fafb; --gray-100: #f3f4f6; --gray-200: #e5e7eb; --gray-300: #d1d5db; --gray-400: #9ca3af; --gray-500: #6b7280; --gray-600: #4b5563; --gray-700: #374151; --gray-800: #1f2937; --gray-900: #111827;
            --space-1: 0.25rem; --space-2: 0.5rem; --space-3: 0.75rem; --space-4: 1rem; --space-6: 1.5rem; --space-8: 2rem;
            --font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --radius-sm: 0.375rem; --radius-md: 0.5rem; --radius-lg: 0.75rem; --radius-xl: 1rem; --radius-2xl: 1.5rem;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05); --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1); --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1); --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1); --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        body { font-family: var(--font-family); background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; color: var(--gray-800); line-height: 1.6; }
        .flex { display: flex; } .items-center { align-items: center; } .justify-between { justify-content: space-between; } .justify-center { justify-content: center; }
        .grid { display: grid; } .grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); } .grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); } .grid-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .p-2 { padding: var(--space-2); } .p-3 { padding: var(--space-3); } .p-4 { padding: var(--space-4); } .p-6 { padding: var(--space-6); } .p-8 { padding: var(--space-8); }
        .px-4 { padding-left: var(--space-4); padding-right: var(--space-4); } .py-2 { padding-top: var(--space-2); padding-bottom: var(--space-2); } .py-4 { padding-top: var(--space-4); padding-bottom: var(--space-4); } .py-8 { padding-top: var(--space-8); padding-bottom: var(--space-8); }
        .mb-4 { margin-bottom: var(--space-4); } .mb-6 { margin-bottom: var(--space-6); } .mb-8 { margin-bottom: var(--space-8); }
        .gap-2 { gap: var(--space-2); } .gap-4 { gap: var(--space-4); } .gap-6 { gap: var(--space-6); } .gap-8 { gap: var(--space-8); }
        .text-sm { font-size: 0.875rem; } .text-base { font-size: 1rem; } .text-lg { font-size: 1.125rem; } .text-xl { font-size: 1.25rem; } .text-2xl { font-size: 1.5rem; } .text-3xl { font-size: 1.875rem; } .text-4xl { font-size: 2.25rem; }
        .font-medium { font-weight: 500; } .font-semibold { font-weight: 600; } .font-bold { font-weight: 700; }
        .text-center { text-align: center; } .text-left { text-align: left; } .text-right { text-align: right; }
        .text-primary { color: var(--primary); } .text-secondary { color: var(--secondary); } .text-accent { color: var(--accent); } .text-gray-500 { color: var(--gray-500); } .text-gray-600 { color: var(--gray-600); } .text-gray-700 { color: var(--gray-700); } .text-gray-800 { color: var(--gray-800); } .text-white { color: white; }
        .bg-primary { background-color: var(--primary); } .bg-secondary { background-color: var(--secondary); } .bg-accent { background-color: var(--accent); } .bg-white { background-color: white; } .bg-gray-50 { background-color: var(--gray-50); } .bg-gray-100 { background-color: var(--gray-100); }
        .rounded { border-radius: var(--radius-md); } .rounded-lg { border-radius: var(--radius-lg); } .rounded-xl { border-radius: var(--radius-xl); } .rounded-2xl { border-radius: var(--radius-2xl); } .rounded-full { border-radius: 9999px; }
        .shadow-sm { box-shadow: var(--shadow-sm); } .shadow-md { box-shadow: var(--shadow-md); } .shadow-lg { box-shadow: var(--shadow-lg); } .shadow-xl { box-shadow: var(--shadow-xl); } .shadow-2xl { box-shadow: var(--shadow-2xl); }
        .w-full { width: 100%; } .w-auto { width: auto; } .h-full { height: 100%; } .h-auto { height: auto; } .min-h-screen { min-height: 100vh; }
        .relative { position: relative; } .absolute { position: absolute; } .fixed { position: fixed; } .sticky { position: sticky; }
        .top-0 { top: 0; } .right-0 { right: 0; } .left-0 { left: 0; } .bottom-0 { bottom: 0; }
        .z-10 { z-index: 10; } .z-20 { z-index: 20; } .z-50 { z-index: 50; } .z-100 { z-index: 100; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 var(--space-8); }
        .card { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(20px); border-radius: var(--radius-2xl); padding: var(--space-8); box-shadow: var(--shadow-xl); border: 1px solid rgba(255, 255, 255, 0.2); transition: all 0.3s ease; }
        .card:hover { transform: translateY(-4px); box-shadow: var(--shadow-2xl); }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: var(--space-2); padding: var(--space-3) var(--space-6); border-radius: var(--radius-xl); font-weight: 500; text-decoration: none; transition: all 0.3s ease; border: none; cursor: pointer; font-size: var(--font-size-base); }
        .btn-primary { background: linear-gradient(135deg, var(--primary), #1d4ed8); color: white; }
        .btn-primary:hover { background: linear-gradient(135deg, #1d4ed8, #1e40af); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(59, 130, 246, 0.3); }
        .btn-secondary { background: linear-gradient(135deg, var(--secondary), #059669); color: white; }
        .btn-secondary:hover { background: linear-gradient(135deg, #059669, #047857); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3); }
        .btn-accent { background: linear-gradient(135deg, var(--accent), #7c3aed); color: white; }
        .btn-accent:hover { background: linear-gradient(135deg, #7c3aed, #6d28d9); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(139, 92, 246, 0.3); }
        .input { width: 100%; padding: var(--space-3) var(--space-4); border: 1px solid var(--gray-300); border-radius: var(--radius-xl); background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(10px); font-size: var(--font-size-base); transition: all 0.3s ease; }
        .input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .header { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); box-shadow: var(--shadow-lg); border-bottom: 1px solid rgba(255, 255, 255, 0.2); position: sticky; top: 0; z-index: 50; }
        .header-content { display: flex; justify-content: space-between; align-items: center; height: 80px; }
        .nav { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255, 255, 255, 0.2); }
        .nav-content { display: flex; justify-content: space-between; align-items: center; height: 60px; }
        .nav-links { display: flex; align-items: center; gap: var(--space-8); }
        .nav-link { display: flex; align-items: center; gap: var(--space-2); padding: var(--space-2) var(--space-4); border-radius: var(--radius-xl); text-decoration: none; font-weight: 500; transition: all 0.3s ease; color: var(--gray-600); }
        .nav-link:hover { background: rgba(0, 0, 0, 0.05); color: var(--gray-800); }
        .nav-link.active { background: var(--primary); color: white; border-bottom: 2px solid #1d4ed8; }
        .kpi-card { border-radius: var(--radius-2xl); padding: var(--space-6); color: white; box-shadow: var(--shadow-xl); transition: all 0.3s ease; animation: fadeInUp 0.6s ease-out; }
        .kpi-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-2xl); }
        .kpi-card.blue { background: linear-gradient(135deg, var(--primary), #1d4ed8); }
        .kpi-card.green { background: linear-gradient(135deg, var(--secondary), #059669); }
        .kpi-card.purple { background: linear-gradient(135deg, var(--accent), #7c3aed); }
        .kpi-card.orange { background: linear-gradient(135deg, var(--warning), #d97706); }
        .kpi-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: var(--space-4); }
        .kpi-title { font-size: var(--font-size-sm); font-weight: 500; opacity: 0.9; margin-bottom: var(--space-1); }
        .kpi-value { font-size: var(--font-size-4xl); font-weight: 700; margin-bottom: var(--space-2); }
        .kpi-change { display: flex; align-items: center; gap: var(--space-1); font-size: var(--font-size-sm); font-weight: 500; opacity: 0.9; }
        .kpi-icon { width: 60px; height: 60px; border-radius: var(--radius-xl); background: rgba(255, 255, 255, 0.2); display: flex; align-items: center; justify-content: center; font-size: var(--font-size-2xl); }
        .table { width: 100%; border-collapse: collapse; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(20px); border-radius: var(--radius-2xl); overflow: hidden; box-shadow: var(--shadow-xl); }
        .table th { background: var(--gray-50); padding: var(--space-4); text-align: left; font-weight: 600; color: var(--gray-700); border-bottom: 1px solid var(--gray-200); }
        .table td { padding: var(--space-4); border-bottom: 1px solid var(--gray-200); color: var(--gray-600); }
        .table tr:hover { background: var(--gray-50); }
        .status-badge { display: inline-flex; align-items: center; gap: var(--space-1); padding: var(--space-1) var(--space-3); border-radius: var(--radius-xl); font-size: var(--font-size-sm); font-weight: 500; }
        .status-badge.active { background: rgba(16, 185, 129, 0.1); color: #059669; }
        .status-badge.inactive { background: rgba(239, 68, 68, 0.1); color: #dc2626; }
        .status-badge.pending { background: rgba(245, 158, 11, 0.1); color: #d97706; }
        .role-badge { display: inline-flex; align-items: center; gap: var(--space-1); padding: var(--space-1) var(--space-3); border-radius: var(--radius-xl); font-size: var(--font-size-sm); font-weight: 500; }
        .role-badge.super-admin { background: rgba(139, 92, 246, 0.1); color: #7c3aed; }
        .role-badge.admin { background: rgba(59, 130, 246, 0.1); color: #1d4ed8; }
        .role-badge.pm { background: rgba(16, 185, 129, 0.1); color: #059669; }
        .role-badge.member { background: rgba(107, 114, 128, 0.1); color: var(--gray-600); }
        .role-badge.client { background: rgba(245, 158, 11, 0.1); color: #d97706; }
        .modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(10px); display: flex; align-items: center; justify-content: center; z-index: 100; opacity: 0; visibility: hidden; transition: all 0.3s ease; }
        .modal.show { opacity: 1; visibility: visible; }
        .modal-content { background: white; border-radius: var(--radius-2xl); padding: var(--space-8); max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: var(--shadow-2xl); transform: scale(0.9); transition: all 0.3s ease; }
        .modal.show .modal-content { transform: scale(1); }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .animate-fade-in { animation: fadeInUp 0.6s ease-out; }
        .animate-pulse { animation: pulse 2s infinite; }
        @media (max-width: 768px) { .container { padding: 0 var(--space-4); } .header-content { height: 60px; } .nav-content { height: 50px; } .nav-links { gap: var(--space-4); } .grid-cols-2 { grid-template-columns: 1fr; } .grid-cols-4 { grid-template-columns: 1fr; } .table { font-size: var(--font-size-sm); } .table th, .table td { padding: var(--space-2); } }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    {{-- Header --}}
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-3">
                        <div class="bg-primary p-3 rounded-xl text-white text-2xl shadow-lg">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-800">Users Management</h1>
                            <p class="text-gray-600 text-sm">Manage system users and permissions</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2 bg-green-100 px-4 py-2 rounded-full">
                            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                            <span class="text-green-800 text-sm font-medium">System Online</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    {{-- Navigation --}}
    <nav class="nav">
        <div class="container">
            <div class="nav-content">
                <div class="nav-links">
                    <a href="/admin" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="/admin/users" class="nav-link active">
                        <i class="fas fa-users"></i>
                        <span>Users</span>
                    </a>
                    <a href="/admin/tenants" class="nav-link">
                        <i class="fas fa-building"></i>
                        <span>Tenants</span>
                    </a>
                    <a href="/admin/projects" class="nav-link">
                        <i class="fas fa-project-diagram"></i>
                        <span>Projects</span>
                    </a>
                    <a href="/admin/analytics" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        <span>Analytics</span>
                    </a>
                    <a href="/admin/security" class="nav-link">
                        <i class="fas fa-shield-alt"></i>
                        <span>Security</span>
                    </a>
                    <a href="/admin/settings" class="nav-link">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </div>
                <div class="flex items-center gap-4">
                    <div class="relative">
                        <input type="text" placeholder="Search users..." class="input w-64 pl-10">
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    {{-- KPI Strip --}}
    <section class="bg-white/80 backdrop-blur-sm border-b border-gray-200/50">
        <div class="container py-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="kpi-card blue">
                    <div class="kpi-header">
                        <div>
                            <div class="kpi-title">Total Users</div>
                            <div class="kpi-value">1,247</div>
                            <div class="kpi-change">
                                <i class="fas fa-arrow-up"></i>
                                <span>+12%</span>
                                <span style="opacity: 0.7; margin-left: 8px;">from last month</span>
                            </div>
                        </div>
                        <div class="kpi-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>

                <div class="kpi-card green">
                    <div class="kpi-header">
                        <div>
                            <div class="kpi-title">Active Users</div>
                            <div class="kpi-value">1,156</div>
                            <div class="kpi-change">
                                <i class="fas fa-arrow-up"></i>
                                <span>+8%</span>
                                <span style="opacity: 0.7; margin-left: 8px;">from last month</span>
                            </div>
                        </div>
                        <div class="kpi-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                </div>

                <div class="kpi-card purple">
                    <div class="kpi-header">
                        <div>
                            <div class="kpi-title">New This Month</div>
                            <div class="kpi-value">89</div>
                            <div class="kpi-change">
                                <i class="fas fa-user-plus"></i>
                                <span>New registrations</span>
                            </div>
                        </div>
                        <div class="kpi-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                    </div>
                </div>

                <div class="kpi-card orange">
                    <div class="kpi-header">
                        <div>
                            <div class="kpi-title">Pending Approval</div>
                            <div class="kpi-value">23</div>
                            <div class="kpi-change">
                                <i class="fas fa-clock"></i>
                                <span>Awaiting review</span>
                            </div>
                        </div>
                        <div class="kpi-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Main Content --}}
    <main class="container py-8">
        <div class="card">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-2xl font-bold text-gray-800">Users List</h2>
                <button class="btn btn-primary" onclick="openCreateUserModal()">
                    <i class="fas fa-plus"></i>
                    <span>Add New User</span>
                </button>
            </div>

            {{-- Filters --}}
            <div class="flex gap-4 mb-6">
                <select class="input w-48">
                    <option>All Roles</option>
                    <option>Super Admin</option>
                    <option>Admin</option>
                    <option>Project Manager</option>
                    <option>Member</option>
                    <option>Client</option>
                </select>
                <select class="input w-48">
                    <option>All Status</option>
                    <option>Active</option>
                    <option>Inactive</option>
                    <option>Pending</option>
                </select>
                <select class="input w-48">
                    <option>All Tenants</option>
                    <option>TechCorp</option>
                    <option>ABC Corp</option>
                    <option>XYZ Ltd</option>
                </select>
            </div>

            {{-- Users Table --}}
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Tenant</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-primary rounded-full flex items-center justify-center text-white font-semibold">
                                        JS
                                    </div>
                                    <div>
                                        <div class="font-semibold text-gray-800">John Smith</div>
                                        <div class="text-sm text-gray-500">ID: USR001</div>
                                    </div>
                                </div>
                            </td>
                            <td>john.smith@techcorp.com</td>
                            <td><span class="role-badge super-admin">Super Admin</span></td>
                            <td>TechCorp</td>
                            <td><span class="status-badge active">Active</span></td>
                            <td>2 hours ago</td>
                            <td>
                                <div class="flex gap-2">
                                    <button class="btn btn-secondary text-sm px-3 py-1">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-accent text-sm px-3 py-1">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn bg-red-500 hover:bg-red-600 text-white text-sm px-3 py-1">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-secondary rounded-full flex items-center justify-center text-white font-semibold">
                                        MJ
                                    </div>
                                    <div>
                                        <div class="font-semibold text-gray-800">Mary Johnson</div>
                                        <div class="text-sm text-gray-500">ID: USR002</div>
                                    </div>
                                </div>
                            </td>
                            <td>mary.johnson@abccorp.com</td>
                            <td><span class="role-badge pm">Project Manager</span></td>
                            <td>ABC Corp</td>
                            <td><span class="status-badge active">Active</span></td>
                            <td>1 day ago</td>
                            <td>
                                <div class="flex gap-2">
                                    <button class="btn btn-secondary text-sm px-3 py-1">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-accent text-sm px-3 py-1">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn bg-red-500 hover:bg-red-600 text-white text-sm px-3 py-1">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-accent rounded-full flex items-center justify-center text-white font-semibold">
                                        DW
                                    </div>
                                    <div>
                                        <div class="font-semibold text-gray-800">David Wilson</div>
                                        <div class="text-sm text-gray-500">ID: USR003</div>
                                    </div>
                                </div>
                            </td>
                            <td>david.wilson@xyzltd.com</td>
                            <td><span class="role-badge member">Member</span></td>
                            <td>XYZ Ltd</td>
                            <td><span class="status-badge inactive">Inactive</span></td>
                            <td>1 week ago</td>
                            <td>
                                <div class="flex gap-2">
                                    <button class="btn btn-secondary text-sm px-3 py-1">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-accent text-sm px-3 py-1">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn bg-red-500 hover:bg-red-600 text-white text-sm px-3 py-1">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-warning rounded-full flex items-center justify-center text-white font-semibold">
                                        SB
                                    </div>
                                    <div>
                                        <div class="font-semibold text-gray-800">Sarah Brown</div>
                                        <div class="text-sm text-gray-500">ID: USR004</div>
                                    </div>
                                </div>
                            </td>
                            <td>sarah.brown@techcorp.com</td>
                            <td><span class="role-badge client">Client</span></td>
                            <td>TechCorp</td>
                            <td><span class="status-badge pending">Pending</span></td>
                            <td>Never</td>
                            <td>
                                <div class="flex gap-2">
                                    <button class="btn btn-secondary text-sm px-3 py-1">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-accent text-sm px-3 py-1">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn bg-red-500 hover:bg-red-600 text-white text-sm px-3 py-1">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="flex justify-between items-center mt-8">
                <div class="text-gray-600">
                    Showing 1-4 of 1,247 users
                </div>
                <div class="flex gap-2">
                    <button class="btn bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="btn bg-primary text-white px-4 py-2">1</button>
                    <button class="btn bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2">2</button>
                    <button class="btn bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2">3</button>
                    <span class="px-4 py-2 text-gray-500">...</span>
                    <button class="btn bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2">312</button>
                    <button class="btn bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </main>

    {{-- Create User Modal --}}
    <div id="createUserModal" class="modal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold text-gray-800">Create New User</h3>
                <button onclick="closeCreateUserModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form class="space-y-6">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                        <input type="text" class="input" placeholder="Enter first name">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                        <input type="text" class="input" placeholder="Enter last name">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" class="input" placeholder="Enter email address">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                    <select class="input">
                        <option>Select Role</option>
                        <option>Super Admin</option>
                        <option>Admin</option>
                        <option>Project Manager</option>
                        <option>Member</option>
                        <option>Client</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tenant</label>
                    <select class="input">
                        <option>Select Tenant</option>
                        <option>TechCorp</option>
                        <option>ABC Corp</option>
                        <option>XYZ Ltd</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <input type="password" class="input" placeholder="Enter password">
                </div>
                
                <div class="flex gap-4 pt-4">
                    <button type="button" onclick="closeCreateUserModal()" class="btn bg-gray-100 hover:bg-gray-200 text-gray-700 flex-1">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary flex-1">
                        <i class="fas fa-plus"></i>
                        <span>Create User</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCreateUserModal() {
            document.getElementById('createUserModal').classList.add('show');
        }
        
        function closeCreateUserModal() {
            document.getElementById('createUserModal').classList.remove('show');
        }
        
        // Close modal when clicking outside
        document.getElementById('createUserModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCreateUserModal();
            }
        });
    </script>
</body>
</html>
