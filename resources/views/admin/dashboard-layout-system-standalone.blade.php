{{-- Admin Dashboard với Layout System (Standalone) --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Layout System Demo</title>
    
    {{-- ZenaManage CSS Framework --}}
    <style>
        /* ===== ZENAMANAGE CSS FRAMEWORK ===== */
        
        /* Reset và Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            /* Color System */
            --primary: #3b82f6;
            --primary-dark: #1d4ed8;
            --secondary: #10b981;
            --secondary-dark: #059669;
            --accent: #8b5cf6;
            --accent-dark: #7c3aed;
            --warning: #f59e0b;
            --warning-dark: #d97706;
            --danger: #ef4444;
            --danger-dark: #dc2626;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            
            /* Spacing System */
            --space-1: 0.25rem;
            --space-2: 0.5rem;
            --space-3: 0.75rem;
            --space-4: 1rem;
            --space-6: 1.5rem;
            --space-8: 2rem;
            --space-12: 3rem;
            --space-16: 4rem;
            
            /* Typography */
            --font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --font-size-xs: 0.75rem;
            --font-size-sm: 0.875rem;
            --font-size-base: 1rem;
            --font-size-lg: 1.125rem;
            --font-size-xl: 1.25rem;
            --font-size-2xl: 1.5rem;
            --font-size-3xl: 1.875rem;
            --font-size-4xl: 2.25rem;
            
            /* Border Radius */
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --radius-2xl: 1.5rem;
            
            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        body {
            font-family: var(--font-family);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--gray-800);
            line-height: 1.6;
        }
        
        /* ===== UTILITY CLASSES ===== */
        
        /* Display */
        .hidden { display: none !important; }
        .block { display: block !important; }
        .flex { display: flex !important; }
        .grid { display: grid !important; }
        .inline-flex { display: inline-flex !important; }
        
        /* Flexbox */
        .flex-col { flex-direction: column !important; }
        .flex-row { flex-direction: row !important; }
        .items-center { align-items: center !important; }
        .items-start { align-items: flex-start !important; }
        .items-end { align-items: flex-end !important; }
        .justify-center { justify-content: center !important; }
        .justify-between { justify-content: space-between !important; }
        .justify-start { justify-content: flex-start !important; }
        .justify-end { justify-content: flex-end !important; }
        
        /* Grid */
        .grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)) !important; }
        .grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
        .grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
        .grid-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)) !important; }
        
        /* Spacing */
        .p-0 { padding: 0 !important; }
        .p-1 { padding: var(--space-1) !important; }
        .p-2 { padding: var(--space-2) !important; }
        .p-3 { padding: var(--space-3) !important; }
        .p-4 { padding: var(--space-4) !important; }
        .p-6 { padding: var(--space-6) !important; }
        .p-8 { padding: var(--space-8) !important; }
        
        .px-4 { padding-left: var(--space-4) !important; padding-right: var(--space-4) !important; }
        .py-2 { padding-top: var(--space-2) !important; padding-bottom: var(--space-2) !important; }
        .py-4 { padding-top: var(--space-4) !important; padding-bottom: var(--space-4) !important; }
        .py-6 { padding-top: var(--space-6) !important; padding-bottom: var(--space-6) !important; }
        .py-8 { padding-top: var(--space-8) !important; padding-bottom: var(--space-8) !important; }
        
        .m-0 { margin: 0 !important; }
        .m-4 { margin: var(--space-4) !important; }
        .mx-auto { margin-left: auto !important; margin-right: auto !important; }
        .mb-4 { margin-bottom: var(--space-4) !important; }
        .mb-6 { margin-bottom: var(--space-6) !important; }
        .mb-8 { margin-bottom: var(--space-8) !important; }
        
        .gap-2 { gap: var(--space-2) !important; }
        .gap-4 { gap: var(--space-4) !important; }
        .gap-6 { gap: var(--space-6) !important; }
        .gap-8 { gap: var(--space-8) !important; }
        
        /* Typography */
        .text-xs { font-size: var(--font-size-xs) !important; }
        .text-sm { font-size: var(--font-size-sm) !important; }
        .text-base { font-size: var(--font-size-base) !important; }
        .text-lg { font-size: var(--font-size-lg) !important; }
        .text-xl { font-size: var(--font-size-xl) !important; }
        .text-2xl { font-size: var(--font-size-2xl) !important; }
        .text-3xl { font-size: var(--font-size-3xl) !important; }
        .text-4xl { font-size: var(--font-size-4xl) !important; }
        
        .font-medium { font-weight: 500 !important; }
        .font-semibold { font-weight: 600 !important; }
        .font-bold { font-weight: 700 !important; }
        
        .text-center { text-align: center !important; }
        .text-left { text-align: left !important; }
        .text-right { text-align: right !important; }
        
        /* Colors */
        .text-primary { color: var(--primary) !important; }
        .text-secondary { color: var(--secondary) !important; }
        .text-accent { color: var(--accent) !important; }
        .text-gray-500 { color: var(--gray-500) !important; }
        .text-gray-600 { color: var(--gray-600) !important; }
        .text-gray-700 { color: var(--gray-700) !important; }
        .text-gray-800 { color: var(--gray-800) !important; }
        .text-white { color: white !important; }
        
        .bg-primary { background-color: var(--primary) !important; }
        .bg-secondary { background-color: var(--secondary) !important; }
        .bg-accent { background-color: var(--accent) !important; }
        .bg-white { background-color: white !important; }
        .bg-gray-50 { background-color: var(--gray-50) !important; }
        .bg-gray-100 { background-color: var(--gray-100) !important; }
        
        /* Border Radius */
        .rounded { border-radius: var(--radius-md) !important; }
        .rounded-lg { border-radius: var(--radius-lg) !important; }
        .rounded-xl { border-radius: var(--radius-xl) !important; }
        .rounded-2xl { border-radius: var(--radius-2xl) !important; }
        .rounded-full { border-radius: 9999px !important; }
        
        /* Shadows */
        .shadow-sm { box-shadow: var(--shadow-sm) !important; }
        .shadow-md { box-shadow: var(--shadow-md) !important; }
        .shadow-lg { box-shadow: var(--shadow-lg) !important; }
        .shadow-xl { box-shadow: var(--shadow-xl) !important; }
        .shadow-2xl { box-shadow: var(--shadow-2xl) !important; }
        
        /* Width & Height */
        .w-full { width: 100% !important; }
        .w-auto { width: auto !important; }
        .h-full { height: 100% !important; }
        .h-auto { height: auto !important; }
        .min-h-screen { min-height: 100vh !important; }
        
        /* Position */
        .relative { position: relative !important; }
        .absolute { position: absolute !important; }
        .fixed { position: fixed !important; }
        .sticky { position: sticky !important; }
        
        .top-0 { top: 0 !important; }
        .right-0 { right: 0 !important; }
        .left-0 { left: 0 !important; }
        .bottom-0 { bottom: 0 !important; }
        
        /* Z-Index */
        .z-10 { z-index: 10 !important; }
        .z-20 { z-index: 20 !important; }
        .z-50 { z-index: 50 !important; }
        
        /* ===== COMPONENT CLASSES ===== */
        
        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--space-8);
        }
        
        /* Card */
        .card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-2xl);
            padding: var(--space-8);
            box-shadow: var(--shadow-xl);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-2xl);
        }
        
        /* Button */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-2);
            padding: var(--space-3) var(--space-6);
            border-radius: var(--radius-xl);
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: var(--font-size-base);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark), #1e40af);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, var(--secondary), var(--secondary-dark));
            color: white;
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, var(--secondary-dark), #047857);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3);
        }
        
        .btn-accent {
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            color: white;
        }
        
        .btn-accent:hover {
            background: linear-gradient(135deg, var(--accent-dark), #6d28d9);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(139, 92, 246, 0.3);
        }
        
        /* Input */
        .input {
            width: 100%;
            padding: var(--space-3) var(--space-4);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-xl);
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            font-size: var(--font-size-base);
            transition: all 0.3s ease;
        }
        
        .input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            box-shadow: var(--shadow-lg);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            position: sticky;
            top: 0;
            z-index: 50;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 80px;
        }
        
        /* Navigation */
        .nav {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .nav-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 60px;
        }
        
        .nav-links {
            display: flex;
            align-items: center;
            gap: var(--space-8);
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-2) var(--space-4);
            border-radius: var(--radius-xl);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            color: var(--gray-600);
        }
        
        .nav-link:hover {
            background: rgba(0, 0, 0, 0.05);
            color: var(--gray-800);
        }
        
        .nav-link.active {
            background: var(--primary);
            color: white;
            border-bottom: 2px solid var(--primary-dark);
        }
        
        /* KPI Card */
        .kpi-card {
            border-radius: var(--radius-2xl);
            padding: var(--space-6);
            color: white;
            box-shadow: var(--shadow-xl);
            transition: all 0.3s ease;
            animation: fadeInUp 0.6s ease-out;
        }
        
        .kpi-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-2xl);
        }
        
        .kpi-card.blue { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); }
        .kpi-card.green { background: linear-gradient(135deg, var(--secondary), var(--secondary-dark)); }
        .kpi-card.purple { background: linear-gradient(135deg, var(--accent), var(--accent-dark)); }
        .kpi-card.orange { background: linear-gradient(135deg, var(--warning), var(--warning-dark)); }
        
        .kpi-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--space-4);
        }
        
        .kpi-title {
            font-size: var(--font-size-sm);
            font-weight: 500;
            opacity: 0.9;
            margin-bottom: var(--space-1);
        }
        
        .kpi-value {
            font-size: var(--font-size-4xl);
            font-weight: 700;
            margin-bottom: var(--space-2);
        }
        
        .kpi-change {
            display: flex;
            align-items: center;
            gap: var(--space-1);
            font-size: var(--font-size-sm);
            font-weight: 500;
            opacity: 0.9;
        }
        
        .kpi-icon {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-xl);
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: var(--font-size-2xl);
        }
        
        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .animate-fade-in { animation: fadeInUp 0.6s ease-out; }
        .animate-pulse { animation: pulse 2s infinite; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container { padding: 0 var(--space-4); }
            .header-content { height: 60px; }
            .nav-content { height: 50px; }
            .nav-links { gap: var(--space-4); }
            .grid-cols-2 { grid-template-columns: 1fr !important; }
            .grid-cols-3 { grid-template-columns: 1fr !important; }
            .grid-cols-4 { grid-template-columns: 1fr !important; }
        }
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
                            <i class="fas fa-crown"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-800">Admin Dashboard</h1>
                            <p class="text-gray-600 text-sm">Welcome back, Administrator</p>
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
                    <a href="/admin" class="nav-link active">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="/admin/users" class="nav-link">
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
                        <input type="text" placeholder="Search..." class="input w-64 pl-10">
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
                            <div class="kpi-title">Active Tenants</div>
                            <div class="kpi-value">89</div>
                            <div class="kpi-change">
                                <i class="fas fa-arrow-up"></i>
                                <span>+5%</span>
                                <span style="opacity: 0.7; margin-left: 8px;">from last month</span>
                            </div>
                        </div>
                        <div class="kpi-icon">
                            <i class="fas fa-building"></i>
                        </div>
                    </div>
                </div>

                <div class="kpi-card purple">
                    <div class="kpi-header">
                        <div>
                            <div class="kpi-title">System Health</div>
                            <div class="kpi-value">99.8%</div>
                            <div class="kpi-change">
                                <i class="fas fa-heartbeat"></i>
                                <span>All systems operational</span>
                            </div>
                        </div>
                        <div class="kpi-icon">
                            <i class="fas fa-heartbeat"></i>
                        </div>
                    </div>
                </div>

                <div class="kpi-card orange">
                    <div class="kpi-header">
                        <div>
                            <div class="kpi-title">Storage Usage</div>
                            <div class="kpi-value">67%</div>
                            <div class="kpi-change">
                                <i class="fas fa-database"></i>
                                <span>2.1TB of 3.2TB used</span>
                            </div>
                        </div>
                        <div class="kpi-icon">
                            <i class="fas fa-database"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Main Content --}}
    <main class="container py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column -->
            <div class="lg:col-span-2">
                <!-- System Overview Chart -->
                <div class="card mb-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">System Overview</h2>
                    <div class="h-80 bg-gray-50 rounded-xl flex items-center justify-center">
                        <div class="text-center">
                            <i class="fas fa-chart-line text-4xl text-gray-400 mb-4"></i>
                            <div class="text-gray-600">System Performance Chart</div>
                            <div class="text-sm text-gray-500 mt-2">Chart.js integration coming soon</div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">Recent Activity</h2>
                    <div class="space-y-6">
                        <div class="flex items-start gap-4 p-4 bg-gray-50 rounded-xl">
                            <div class="w-12 h-12 bg-blue-500 rounded-xl flex items-center justify-center text-white">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-semibold text-gray-800">User Created</h3>
                                <p class="text-gray-600 text-sm">New user "Jane Smith" added to tenant "TechCorp"</p>
                                <p class="text-gray-400 text-xs mt-1">5 minutes ago</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4 p-4 bg-gray-50 rounded-xl">
                            <div class="w-12 h-12 bg-green-500 rounded-xl flex items-center justify-center text-white">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-semibold text-gray-800">Tenant Updated</h3>
                                <p class="text-gray-600 text-sm">Tenant "ABC Corp" settings updated</p>
                                <p class="text-gray-400 text-xs mt-1">15 minutes ago</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4 p-4 bg-gray-50 rounded-xl">
                            <div class="w-12 h-12 bg-purple-500 rounded-xl flex items-center justify-center text-white">
                                <i class="fas fa-download"></i>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-semibold text-gray-800">System Backup</h3>
                                <p class="text-gray-600 text-sm">Daily system backup completed</p>
                                <p class="text-gray-400 text-xs mt-1">1 hour ago</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Quick Actions -->
                <div class="card mb-8">
                    <h2 class="text-xl font-bold text-gray-800 mb-6">Quick Actions</h2>
                    <div class="space-y-4">
                        <button class="btn btn-primary w-full">
                            <i class="fas fa-user-plus"></i>
                            <span>Add User</span>
                        </button>
                        <button class="btn btn-secondary w-full">
                            <i class="fas fa-building"></i>
                            <span>Create Tenant</span>
                        </button>
                        <button class="btn btn-accent w-full">
                            <i class="fas fa-download"></i>
                            <span>Backup System</span>
                        </button>
                        <button class="btn w-full bg-gray-600 hover:bg-gray-700 text-white">
                            <i class="fas fa-cog"></i>
                            <span>System Settings</span>
                        </button>
                    </div>
                </div>

                <!-- System Status -->
                <div class="card">
                    <h2 class="text-xl font-bold text-gray-800 mb-6">System Status</h2>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div class="flex items-center gap-3">
                                <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                                <span class="font-medium text-gray-800">Database</span>
                            </div>
                            <span class="text-green-600 font-medium">online</span>
                        </div>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div class="flex items-center gap-3">
                                <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                                <span class="font-medium text-gray-800">Cache</span>
                            </div>
                            <span class="text-green-600 font-medium">online</span>
                        </div>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div class="flex items-center gap-3">
                                <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                                <span class="font-medium text-gray-800">Queue</span>
                            </div>
                            <span class="text-green-600 font-medium">online</span>
                        </div>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div class="flex items-center gap-3">
                                <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                                <span class="font-medium text-gray-800">Storage</span>
                            </div>
                            <span class="text-green-600 font-medium">online</span>
                        </div>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div class="flex items-center gap-3">
                                <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                                <span class="font-medium text-gray-800">Email</span>
                            </div>
                            <span class="text-green-600 font-medium">online</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
