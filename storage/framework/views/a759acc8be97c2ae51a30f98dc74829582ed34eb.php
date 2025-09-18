<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Dashboard - ZENA</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8f9fa; color: #333; }
        
        /* Layout */
        .app-container { display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { 
            width: 250px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            position: fixed; 
            height: 100vh; 
            overflow-y: auto; 
            z-index: 1000;
        }
        .sidebar-header { 
            padding: 20px; 
            border-bottom: 1px solid rgba(255,255,255,0.1); 
        }
        .logo { 
            font-size: 1.5rem; 
            font-weight: bold; 
            color: white; 
            text-decoration: none; 
        }
        .sidebar-nav { 
            padding: 20px 0; 
        }
        .nav-item { 
            margin-bottom: 5px; 
        }
        .nav-link { 
            display: flex; 
            align-items: center; 
            padding: 12px 20px; 
            color: white; 
            text-decoration: none; 
            transition: all 0.3s; 
        }
        .nav-link:hover { 
            background: rgba(255,255,255,0.1); 
        }
        .nav-link.active { 
            background: rgba(255,255,255,0.2); 
        }
        .nav-icon { 
            margin-right: 10px; 
            width: 20px; 
        }
        
        /* Main Content */
        .main-content { 
            margin-left: 250px; 
            flex: 1; 
            padding: 20px; 
        }
        .header { 
            background: white; 
            padding: 20px; 
            border-radius: 10px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
            margin-bottom: 20px; 
        }
        .header-content { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        .page-title { 
            font-size: 2rem; 
            color: #333; 
        }
        .user-info { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }
        .role-badge {
            background: #667eea;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-left: 10px;
        }
        
        /* Dashboard Grid */
        .dashboard-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
            gap: 20px; 
            margin-bottom: 30px; 
        }
        .dashboard-card { 
            background: white; 
            border-radius: 12px; 
            padding: 20px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
            transition: transform 0.3s, box-shadow 0.3s; 
            border-left: 4px solid #10b981; 
        }
        .dashboard-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 5px 20px rgba(0,0,0,0.15); 
        }
        .dashboard-title { 
            font-size: 1.3rem; 
            font-weight: bold; 
            margin-bottom: 10px; 
            color: #333; 
        }
        .dashboard-description { 
            color: #666; 
            margin-bottom: 15px; 
            line-height: 1.5; 
        }
        .dashboard-stats { 
            display: flex; 
            gap: 15px; 
            margin-bottom: 15px; 
        }
        .stat { 
            text-align: center; 
        }
        .stat-number { 
            font-size: 1.5rem; 
            font-weight: bold; 
            color: #10b981; 
        }
        .stat-label { 
            font-size: 0.8rem; 
            color: #666; 
        }
        .dashboard-actions { 
            display: flex; 
            gap: 10px; 
        }
        .btn { 
            padding: 8px 16px; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer; 
            text-decoration: none; 
            font-size: 0.9rem; 
            transition: all 0.3s; 
        }
        .btn-primary { 
            background: #10b981; 
            color: white; 
        }
        .btn-primary:hover { 
            background: #059669; 
        }
        .btn-secondary { 
            background: #6c757d; 
            color: white; 
        }
        .btn-secondary:hover { 
            background: #5a6268; 
        }
        .status-bar { 
            background: #d4edda; 
            border: 1px solid #c3e6cb; 
            border-radius: 8px; 
            padding: 15px; 
            margin-bottom: 20px; 
            color: #155724; 
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="/dashboard" class="logo">🚀 ZENA</a>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="nav-item">
                        <a href="/dashboard" class="nav-link">
                            <i class="fas fa-tachometer-alt nav-icon"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/dashboard/sales" class="nav-link">
                            <i class="fas fa-chart-line nav-icon"></i>
                            Sales Analytics
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/dashboard/users" class="nav-link">
                            <i class="fas fa-users nav-icon"></i>
                            User Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/dashboard/performance" class="nav-link active">
                            <i class="fas fa-tachometer-alt nav-icon"></i>
                            Performance
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/dashboard/marketing" class="nav-link">
                            <i class="fas fa-bullhorn nav-icon"></i>
                            Marketing
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/dashboard/financial" class="nav-link">
                            <i class="fas fa-dollar-sign nav-icon"></i>
                            Financial
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/dashboard/projects" class="nav-link">
                            <i class="fas fa-project-diagram nav-icon"></i>
                            Projects
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/tasks" class="nav-link">
                            <i class="fas fa-tasks nav-icon"></i>
                            Tasks
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/team" class="nav-link">
                            <i class="fas fa-users nav-icon"></i>
                            Team
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/admin" class="nav-link">
                            <i class="fas fa-cog nav-icon"></i>
                            Admin
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <div class="header-content">
                    <h1 class="page-title">⚡ Performance Dashboard</h1>
                    <div class="user-info">
                        <span>Xin chào, User!</span>
                        <span class="role-badge">Performance Monitor</span>
                    </div>
                </div>
            </div>
            
            <div class="status-bar">
                ✅ System Status: Online | 📅 <?php echo e(now()->format('Y-m-d H:i:s')); ?> | 🔄 Real-time Updates: Active
            </div>

            <!-- Performance Modules -->
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <div class="dashboard-title">🖥️ Server Performance</div>
                    <div class="dashboard-description">
                        Monitor server CPU, memory, disk usage and response times.
                    </div>
                    <div class="dashboard-stats">
                        <div class="stat">
                            <div class="stat-number">45%</div>
                            <div class="stat-label">CPU Usage</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">2.1GB</div>
                            <div class="stat-label">Memory</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">78%</div>
                            <div class="stat-label">Disk</div>
                        </div>
                    </div>
                    <div class="dashboard-actions">
                        <button class="btn btn-primary" onclick="viewServerDetails()">View Details</button>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="dashboard-title">🌐 Network Performance</div>
                    <div class="dashboard-description">
                        Track network latency, bandwidth usage and connection quality.
                    </div>
                    <div class="dashboard-stats">
                        <div class="stat">
                            <div class="stat-number">12ms</div>
                            <div class="stat-label">Latency</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">1.2GB</div>
                            <div class="stat-label">Bandwidth</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">99.9%</div>
                            <div class="stat-label">Uptime</div>
                        </div>
                    </div>
                    <div class="dashboard-actions">
                        <button class="btn btn-primary" onclick="viewNetworkDetails()">View Details</button>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="dashboard-title">📊 Application Metrics</div>
                    <div class="dashboard-description">
                        Monitor application performance, response times and error rates.
                    </div>
                    <div class="dashboard-stats">
                        <div class="stat">
                            <div class="stat-number">156ms</div>
                            <div class="stat-label">Avg Response</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">0.1%</div>
                            <div class="stat-label">Error Rate</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">2.1K</div>
                            <div class="stat-label">Requests/min</div>
                        </div>
                    </div>
                    <div class="dashboard-actions">
                        <button class="btn btn-primary" onclick="viewAppMetrics()">View Details</button>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="dashboard-title">🗄️ Database Performance</div>
                    <div class="dashboard-description">
                        Track database query performance, connection pools and optimization.
                    </div>
                    <div class="dashboard-stats">
                        <div class="stat">
                            <div class="stat-number">23ms</div>
                            <div class="stat-label">Query Time</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">45</div>
                            <div class="stat-label">Connections</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">98%</div>
                            <div class="stat-label">Cache Hit</div>
                        </div>
                    </div>
                    <div class="dashboard-actions">
                        <button class="btn btn-primary" onclick="viewDatabaseDetails()">View Details</button>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="dashboard-title">🔍 Performance Alerts</div>
                    <div class="dashboard-description">
                        Monitor performance thresholds and receive alerts for issues.
                    </div>
                    <div class="dashboard-stats">
                        <div class="stat">
                            <div class="stat-number">3</div>
                            <div class="stat-label">Active Alerts</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">12</div>
                            <div class="stat-label">Resolved Today</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">95%</div>
                            <div class="stat-label">SLA</div>
                        </div>
                    </div>
                    <div class="dashboard-actions">
                        <button class="btn btn-primary" onclick="viewAlerts()">View Alerts</button>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="dashboard-title">📈 Performance Trends</div>
                    <div class="dashboard-description">
                        Analyze performance trends and capacity planning insights.
                    </div>
                    <div class="dashboard-stats">
                        <div class="stat">
                            <div class="stat-number">+5%</div>
                            <div class="stat-label">Growth</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">30d</div>
                            <div class="stat-label">Trend Period</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">85%</div>
                            <div class="stat-label">Capacity</div>
                        </div>
                    </div>
                    <div class="dashboard-actions">
                        <button class="btn btn-primary" onclick="viewTrends()">View Trends</button>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        function viewServerDetails() {
            window.location.href = '/dashboard/performance';
        }
        
        function viewNetworkDetails() {
            window.location.href = '/dashboard/performance';
        }
        
        function viewAppMetrics() {
            window.location.href = '/dashboard/performance';
        }
        
        function viewDatabaseDetails() {
            window.location.href = '/dashboard/performance';
        }
        
        function viewAlerts() {
            window.location.href = '/dashboard/performance';
        }
        
        function viewTrends() {
            window.location.href = '/dashboard/performance';
        }
        
        // Real-time updates simulation
        setInterval(function() {
            const timestamp = new Date().toLocaleString();
            document.querySelector('.status-bar').innerHTML = 
                '✅ System Status: Online | 📅 ' + timestamp + ' | 🔄 Real-time Updates: Active';
        }, 1000);
        
        // Sidebar navigation active state
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname;
            const navLinks = document.querySelectorAll('.nav-link');
            
            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === currentPath) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/dashboards/performance.blade.php ENDPATH**/ ?>