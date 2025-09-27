{{-- Admin Dashboard - CSS Inline Version --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ZenaManage</title>
    <style>
        /* Reset và Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        /* Header Styles */
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            position: sticky;
            top: 0;
            z-index: 50;
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 80px;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .logo-icon {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            padding: 12px;
            border-radius: 12px;
            color: white;
            font-size: 24px;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .logo-text h1 {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .logo-text p {
            color: #666;
            font-size: 14px;
            margin-top: 4px;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .status-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #10b981;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        /* Navigation */
        .nav {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .nav-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 60px;
        }
        
        .nav-links {
            display: flex;
            align-items: center;
            gap: 2rem;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            color: #666;
        }
        
        .nav-link:hover {
            background: rgba(0, 0, 0, 0.05);
            color: #333;
        }
        
        .nav-link.active {
            background: #3b82f6;
            color: white;
            border-bottom: 2px solid #1d4ed8;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-input {
            width: 256px;
            padding: 8px 16px 8px 40px;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            font-size: 14px;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }
        
        /* KPI Strip */
        .kpi-strip {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .kpi-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }
        
        .kpi-card {
            border-radius: 16px;
            padding: 16px;
            color: white;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            animation: fadeInUp 0.6s ease-out;
        }
        
        .kpi-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.3);
        }
        
        .kpi-card.blue { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .kpi-card.green { background: linear-gradient(135deg, #10b981, #059669); }
        .kpi-card.purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .kpi-card.orange { background: linear-gradient(135deg, #f59e0b, #d97706); }
        
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
        
        .kpi-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        
        .kpi-title {
            font-size: 14px;
            font-weight: 500;
            opacity: 0.9;
            margin-bottom: 4px;
        }
        
        .kpi-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        
        .kpi-change {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 14px;
            font-weight: 500;
            opacity: 0.9;
        }
        
        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        /* Main Content */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 32px;
        }
        
        .content-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: fadeInUp 0.6s ease-out;
        }
        
        .card-title {
            font-size: 24px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 24px;
        }
        
        /* Chart Container */
        .chart-container {
            height: 320px;
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-size: 18px;
            font-weight: 500;
        }
        
        /* Activity List */
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        
        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 16px;
            background: rgba(0, 0, 0, 0.02);
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .activity-item:hover {
            background: rgba(0, 0, 0, 0.05);
        }
        
        .activity-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }
        
        .activity-icon.blue { background: #3b82f6; }
        .activity-icon.green { background: #10b981; }
        .activity-icon.purple { background: #8b5cf6; }
        
        .activity-content h3 {
            font-size: 16px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
        }
        
        .activity-content p {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 8px;
        }
        
        .activity-time {
            font-size: 12px;
            color: #9ca3af;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 16px 24px;
            border-radius: 16px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        
        .action-btn.primary {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }
        
        .action-btn.primary:hover {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.3);
        }
        
        .action-btn.secondary {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .action-btn.secondary:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3);
        }
        
        .action-btn.accent {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
        }
        
        .action-btn.accent:hover {
            background: linear-gradient(135deg, #7c3aed, #6d28d9);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(139, 92, 246, 0.3);
        }
        
        .action-btn.gray {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
        }
        
        .action-btn.gray:hover {
            background: linear-gradient(135deg, #4b5563, #374151);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(107, 114, 128, 0.3);
        }
        
        /* System Status */
        .status-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .status-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px;
            background: rgba(0, 0, 0, 0.02);
            border-radius: 12px;
        }
        
        .status-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        .status-dot.online {
            background: #10b981;
        }
        
        .status-dot.offline {
            background: #ef4444;
        }
        
        .status-name {
            font-weight: 500;
            color: #1f2937;
        }
        
        .status-value {
            font-weight: 500;
            color: #10b981;
        }
        
        .status-value.offline {
            color: #ef4444;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                padding: 0 1rem;
                height: 60px;
            }
            
            .logo-text h1 {
                font-size: 20px;
            }
            
            .nav-content {
                padding: 0 1rem;
                height: 50px;
            }
            
            .nav-links {
                gap: 1rem;
            }
            
            .search-input {
                width: 200px;
            }
            
            .main-content {
                padding: 1rem;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
                gap: 24px;
            }
            
            .kpi-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .kpi-content {
                padding: 1rem;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-crown"></i>
                </div>
                <div class="logo-text">
                    <h1>Admin Dashboard</h1>
                    <p>Welcome back, Administrator</p>
                </div>
            </div>
            <div class="header-actions">
                <div class="status-badge">
                    <div class="status-dot"></div>
                    <span>System Online</span>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="nav">
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
            <div class="search-box">
                <input type="text" placeholder="Search..." class="search-input">
                <i class="fas fa-search search-icon"></i>
            </div>
        </div>
    </nav>

    <!-- KPI Strip -->
    <section class="kpi-strip">
        <div class="kpi-content">
            <div class="kpi-grid">
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

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-grid">
            <!-- Left Column -->
            <div>
                <!-- System Overview Chart -->
                <div class="content-card">
                    <h2 class="card-title">System Overview</h2>
                    <div class="chart-container">
                        <div>
                            <i class="fas fa-chart-line" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                            <div>System Performance Chart</div>
                            <div style="font-size: 14px; margin-top: 8px; opacity: 0.7;">Chart.js integration coming soon</div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="content-card">
                    <h2 class="card-title">Recent Activity</h2>
                    <div class="activity-list">
                        <div class="activity-item">
                            <div class="activity-icon blue">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="activity-content">
                                <h3>User Created</h3>
                                <p>New user "Jane Smith" added to tenant "TechCorp"</p>
                                <div class="activity-time">5 minutes ago</div>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon green">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="activity-content">
                                <h3>Tenant Updated</h3>
                                <p>Tenant "ABC Corp" settings updated</p>
                                <div class="activity-time">15 minutes ago</div>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon purple">
                                <i class="fas fa-download"></i>
                            </div>
                            <div class="activity-content">
                                <h3>System Backup</h3>
                                <p>Daily system backup completed</p>
                                <div class="activity-time">1 hour ago</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Quick Actions -->
                <div class="content-card">
                    <h2 class="card-title">Quick Actions</h2>
                    <div class="quick-actions">
                        <button class="action-btn primary">
                            <i class="fas fa-user-plus"></i>
                            <span>Add User</span>
                        </button>
                        <button class="action-btn secondary">
                            <i class="fas fa-building"></i>
                            <span>Create Tenant</span>
                        </button>
                        <button class="action-btn accent">
                            <i class="fas fa-download"></i>
                            <span>Backup System</span>
                        </button>
                        <button class="action-btn gray">
                            <i class="fas fa-cog"></i>
                            <span>System Settings</span>
                        </button>
                    </div>
                </div>

                <!-- System Status -->
                <div class="content-card">
                    <h2 class="card-title">System Status</h2>
                    <div class="status-list">
                        <div class="status-item">
                            <div class="status-info">
                                <div class="status-dot online"></div>
                                <span class="status-name">Database</span>
                            </div>
                            <span class="status-value">online</span>
                        </div>
                        <div class="status-item">
                            <div class="status-info">
                                <div class="status-dot online"></div>
                                <span class="status-name">Cache</span>
                            </div>
                            <span class="status-value">online</span>
                        </div>
                        <div class="status-item">
                            <div class="status-info">
                                <div class="status-dot online"></div>
                                <span class="status-name">Queue</span>
                            </div>
                            <span class="status-value">online</span>
                        </div>
                        <div class="status-item">
                            <div class="status-info">
                                <div class="status-dot online"></div>
                                <span class="status-name">Storage</span>
                            </div>
                            <span class="status-value">online</span>
                        </div>
                        <div class="status-item">
                            <div class="status-info">
                                <div class="status-dot online"></div>
                                <span class="status-name">Email</span>
                            </div>
                            <span class="status-value">online</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
