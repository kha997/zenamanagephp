<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects Dashboard - ZENA</title>
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
            background: #8b5cf6;
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
            border-left: 4px solid #8b5cf6; 
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
            color: #8b5cf6; 
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
            background: #8b5cf6; 
            color: white; 
        }
        .btn-primary:hover { 
            background: #7c3aed; 
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
                        <a href="/dashboard/performance" class="nav-link">
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
                        <a href="/dashboard/projects" class="nav-link active">
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
                    <h1 class="page-title">🎯 Projects Dashboard</h1>
                    <div class="user-info">
                        <span>Xin chào, User!</span>
                        <span class="role-badge">Project Manager</span>
                    </div>
                </div>
            </div>
            
            <div class="status-bar">
                ✅ System Status: Online | 📅 <?php echo e(now()->format('Y-m-d H:i:s')); ?> | 🔄 Real-time Updates: Active
            </div>

            <!-- Projects Modules -->
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <div class="dashboard-title">📋 Project Overview</div>
                    <div class="dashboard-description">
                        Monitor all active projects, their status and key performance indicators.
                    </div>
                    <div class="dashboard-stats">
                        <div class="stat">
                            <div class="stat-number">8</div>
                            <div class="stat-label">Active Projects</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">67%</div>
                            <div class="stat-label">Completion</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">3</div>
                            <div class="stat-label">Overdue</div>
                        </div>
                    </div>
                    <div class="dashboard-actions">
                        <button class="btn btn-primary" onclick="viewProjects()">View Projects</button>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="dashboard-title">✅ Task Management</div>
                    <div class="dashboard-description">
                        Track task progress, assignments and completion rates across projects.
                    </div>
                    <div class="dashboard-stats">
                        <div class="stat">
                            <div class="stat-number">24</div>
                            <div class="stat-label">Total Tasks</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">18</div>
                            <div class="stat-label">Completed</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">75%</div>
                            <div class="stat-label">Progress</div>
                        </div>
                    </div>
                    <div class="dashboard-actions">
                        <button class="btn btn-primary" onclick="viewTasks()">View Tasks</button>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="dashboard-title">👥 Team Management</div>
                    <div class="dashboard-description">
                        Manage team members, assignments and workload distribution.
                    </div>
                    <div class="dashboard-stats">
                        <div class="stat">
                            <div class="stat-number">12</div>
                            <div class="stat-label">Team Members</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">8</div>
                            <div class="stat-label">Active</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">92%</div>
                            <div class="stat-label">Utilization</div>
                        </div>
                    </div>
                    <div class="dashboard-actions">
                        <button class="btn btn-primary" onclick="viewTeam()">View Team</button>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="dashboard-title">📅 Timeline & Milestones</div>
                    <div class="dashboard-description">
                        Track project timelines, milestones and delivery schedules.
                    </div>
                    <div class="dashboard-stats">
                        <div class="stat">
                            <div class="stat-number">15</div>
                            <div class="stat-label">Milestones</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">12</div>
                            <div class="stat-label">Completed</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">80%</div>
                            <div class="stat-label">On Schedule</div>
                        </div>
                    </div>
                    <div class="dashboard-actions">
                        <button class="btn btn-primary" onclick="viewTimeline()">View Timeline</button>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="dashboard-title">💰 Budget Tracking</div>
                    <div class="dashboard-description">
                        Monitor project budgets, costs and financial performance.
                    </div>
                    <div class="dashboard-stats">
                        <div class="stat">
                            <div class="stat-number">$450K</div>
                            <div class="stat-label">Total Budget</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">$320K</div>
                            <div class="stat-label">Spent</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">71%</div>
                            <div class="stat-label">Used</div>
                        </div>
                    </div>
                    <div class="dashboard-actions">
                        <button class="btn btn-primary" onclick="viewBudget()">View Budget</button>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="dashboard-title">📊 Project Analytics</div>
                    <div class="dashboard-description">
                        Analyze project performance, productivity metrics and insights.
                    </div>
                    <div class="dashboard-stats">
                        <div class="stat">
                            <div class="stat-number">95%</div>
                            <div class="stat-label">Quality Score</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">4.8</div>
                            <div class="stat-label">Client Rating</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">+12%</div>
                            <div class="stat-label">Efficiency</div>
                        </div>
                    </div>
                    <div class="dashboard-actions">
                        <button class="btn btn-primary" onclick="viewAnalytics()">View Analytics</button>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        function viewProjects() {
            window.location.href = '/projects';
        }
        
        function viewTasks() {
            // Redirect to tasks page
            window.location.href = '/tasks';
        }
        
        function viewTeam() {
            // Redirect to team page
            window.location.href = '/team';
        }
        
        function viewTimeline() {
            // Redirect to projects page with timeline tab
            window.location.href = '/projects';
        }
        
        function viewBudget() {
            // Redirect to projects page with budget tab
            window.location.href = '/projects';
        }
        
        function viewAnalytics() {
            // Redirect to projects page with analytics tab
            window.location.href = '/projects';
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
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/dashboards/projects.blade.php ENDPATH**/ ?>