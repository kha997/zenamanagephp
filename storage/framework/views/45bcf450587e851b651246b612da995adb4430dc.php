<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - ZENA</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8f9fa; color: #333; }
        .app-container { display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: linear-gradient(135deg, #1f2937 0%, #374151 100%); color: white; position: fixed; height: 100vh; overflow-y: auto; z-index: 1000; }
        .sidebar-header { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .logo { font-size: 1.5rem; font-weight: bold; color: white; text-decoration: none; }
        .sidebar-nav { padding: 20px 0; }
        .nav-section { margin-bottom: 30px; }
        .nav-section-title { padding: 10px 20px; font-size: 0.8rem; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; }
        .nav-item { margin-bottom: 2px; }
        .nav-link { display: flex; align-items: center; padding: 12px 20px; color: #d1d5db; text-decoration: none; transition: all 0.3s; }
        .nav-link:hover { background: rgba(255,255,255,0.1); color: white; }
        .nav-link.active { background: rgba(59, 130, 246, 0.2); color: #60a5fa; border-right: 3px solid #3b82f6; }
        .nav-icon { margin-right: 12px; width: 20px; text-align: center; }
        .main-content { margin-left: 280px; flex: 1; padding: 20px; }
        .header { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .header-content { display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 2rem; color: #333; }
        .user-info { display: flex; align-items: center; gap: 10px; }
        .role-badge { background: #dc2626; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; margin-left: 10px; }
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .dashboard-card { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); transition: transform 0.3s, box-shadow 0.3s; border-left: 4px solid #dc2626; }
        .dashboard-card:hover { transform: translateY(-5px); box-shadow: 0 5px 20px rgba(0,0,0,0.15); }
        .dashboard-title { font-size: 1.3rem; font-weight: bold; margin-bottom: 10px; color: #333; }
        .dashboard-description { color: #666; margin-bottom: 15px; line-height: 1.5; }
        .dashboard-stats { display: flex; gap: 15px; margin-bottom: 15px; }
        .stat { text-align: center; }
        .stat-number { font-size: 1.5rem; font-weight: bold; color: #dc2626; }
        .stat-label { font-size: 0.8rem; color: #666; }
        .dashboard-actions { display: flex; gap: 10px; }
        .btn { padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 0.9rem; transition: all 0.3s; }
        .btn-primary { background: #dc2626; color: white; }
        .btn-primary:hover { background: #b91c1c; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .status-bar { background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; padding: 15px; margin-bottom: 20px; color: #155724; }
    </style>
</head>
<body>
    <div class="app-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="/admin" class="logo">⚙️ Admin Panel</a>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">System Management</div>
                    <ul>
                        <li class="nav-item"><a href="/admin" class="nav-link active"><i class="fas fa-tachometer-alt nav-icon"></i>Dashboard</a></li>
                        <li class="nav-item"><a href="/admin/users" class="nav-link"><i class="fas fa-users nav-icon"></i>User Management</a></li>
                        <li class="nav-item"><a href="/admin/roles" class="nav-link"><i class="fas fa-user-shield nav-icon"></i>Role & Permissions</a></li>
                        <li class="nav-item"><a href="/admin/system-health" class="nav-link"><i class="fas fa-heartbeat nav-icon"></i>System Health</a></li>
                    </ul>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title">Business Modules</div>
                    <ul>
                        <li class="nav-item"><a href="/admin/projects" class="nav-link"><i class="fas fa-project-diagram nav-icon"></i>Project Management</a></li>
                        <li class="nav-item"><a href="/admin/tasks" class="nav-link"><i class="fas fa-tasks nav-icon"></i>Task Management</a></li>
                        <li class="nav-item"><a href="/admin/documents" class="nav-link"><i class="fas fa-file-alt nav-icon"></i>Document Center</a></li>
                        <li class="nav-item"><a href="/admin/quality" class="nav-link"><i class="fas fa-check-circle nav-icon"></i>Quality Control</a></li>
                    </ul>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title">Operations</div>
                    <ul>
                        <li class="nav-item"><a href="/admin/audit-logs" class="nav-link"><i class="fas fa-clipboard-list nav-icon"></i>Audit Logs</a></li>
                        <li class="nav-item"><a href="/admin/backup" class="nav-link"><i class="fas fa-database nav-icon"></i>Backup & Recovery</a></li>
                        <li class="nav-item"><a href="/admin/settings" class="nav-link"><i class="fas fa-cog nav-icon"></i>System Settings</a></li>
                        <li class="nav-item"><a href="/admin/reports" class="nav-link"><i class="fas fa-chart-bar nav-icon"></i>Reports & Analytics</a></li>
                    </ul>
                </div>
            </nav>
        </aside>
        <main class="main-content">
            <div class="header">
                <div class="header-content">
                    <h1 class="page-title">⚙️ Admin Panel</h1>
                    <div class="user-info">
                        <span>Xin chào, Admin!</span>
                        <span class="role-badge">System Administrator</span>
                    </div>
                </div>
            </div>
            <div class="status-bar">
                ✅ System Status: Online | 📅 <?php echo e(now()->format("Y-m-d H:i:s")); ?> | 🔄 Real-time Updates: Active
            </div>
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <div class="dashboard-title">👥 User Management</div>
                    <div class="dashboard-description">Manage users, roles, permissions and access control across the system.</div>
                    <div class="dashboard-stats">
                        <div class="stat"><div class="stat-number">1,247</div><div class="stat-label">Total Users</div></div>
                        <div class="stat"><div class="stat-number">156</div><div class="stat-label">Active Today</div></div>
                        <div class="stat"><div class="stat-number">94%</div><div class="stat-label">Online</div></div>
                    </div>
                    <div class="dashboard-actions">
                        <button class="btn btn-primary" onclick="manageUsers()">Manage Users</button>
                        <button class="btn btn-secondary" onclick="viewUserReports()">Reports</button>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="dashboard-title">🏢 System Health</div>
                    <div class="dashboard-description">Monitor system performance, server status and overall health metrics.</div>
                    <div class="dashboard-stats">
                        <div class="stat"><div class="stat-number">99.9%</div><div class="stat-label">Uptime</div></div>
                        <div class="stat"><div class="stat-number">45%</div><div class="stat-label">CPU Usage</div></div>
                        <div class="stat"><div class="stat-number">2.1GB</div><div class="stat-label">Memory</div></div>
                    </div>
                    <div class="dashboard-actions">
                        <button class="btn btn-primary" onclick="viewSystemHealth()">View Details</button>
                        <button class="btn btn-secondary" onclick="configureAlerts()">Alerts</button>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="dashboard-title">📋 Project Management</div>
                    <div class="dashboard-description">Oversee all projects, track progress and manage resources allocation.</div>
                    <div class="dashboard-stats">
                        <div class="stat"><div class="stat-number">8</div><div class="stat-label">Active Projects</div></div>
                        <div class="stat"><div class="stat-number">67%</div><div class="stat-label">Completion</div></div>
                        <div class="stat"><div class="stat-number">$450K</div><div class="stat-label">Budget</div></div>
                    </div>
                    <div class="dashboard-actions">
                        <button class="btn btn-primary" onclick="manageProjects()">Manage Projects</button>
                        <button class="btn btn-secondary" onclick="viewProjectAnalytics()">Analytics</button>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="dashboard-title">📊 Quality Control</div>
                    <div class="dashboard-description">Monitor quality metrics, inspections and compliance across all projects.</div>
                    <div class="dashboard-stats">
                        <div class="stat"><div class="stat-number">95%</div><div class="stat-label">Quality Score</div></div>
                        <div class="stat"><div class="stat-number">12</div><div class="stat-label">Inspections</div></div>
                        <div class="stat"><div class="stat-number">3</div><div class="stat-label">NCRs</div></div>
                    </div>
                    <div class="dashboard-actions">
                        <button class="btn btn-primary" onclick="manageQuality()">Manage Quality</button>
                        <button class="btn btn-secondary" onclick="viewQualityReports()">Reports</button>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="dashboard-title">📁 Document Center</div>
                    <div class="dashboard-description">Manage documents, drawings, specifications and version control.</div>
                    <div class="dashboard-stats">
                        <div class="stat"><div class="stat-number">2,847</div><div class="stat-label">Documents</div></div>
                        <div class="stat"><div class="stat-number">156</div><div class="stat-label">New Today</div></div>
                        <div class="stat"><div class="stat-number">45GB</div><div class="stat-label">Storage</div></div>
                    </div>
                    <div class="dashboard-actions">
                        <button class="btn btn-primary" onclick="manageDocuments()">Manage Documents</button>
                        <button class="btn btn-secondary" onclick="viewDocumentAnalytics()">Analytics</button>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="dashboard-title">📋 Audit Logs</div>
                    <div class="dashboard-description">Track system activities, user actions and security events.</div>
                    <div class="dashboard-stats">
                        <div class="stat"><div class="stat-number">24</div><div class="stat-label">Logs Today</div></div>
                        <div class="stat"><div class="stat-number">3</div><div class="stat-label">Alerts</div></div>
                        <div class="stat"><div class="stat-number">100%</div><div class="stat-label">Compliance</div></div>
                    </div>
                    <div class="dashboard-actions">
                        <button class="btn btn-primary" onclick="viewAuditLogs()">View Logs</button>
                        <button class="btn btn-secondary" onclick="exportLogs()">Export</button>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script>
        function manageUsers() { alert("Opening User Management..."); }
        function viewUserReports() { alert("Opening User Reports..."); }
        function viewSystemHealth() { alert("Opening System Health Details..."); }
        function configureAlerts() { alert("Opening Alert Configuration..."); }
        function manageProjects() { alert("Opening Project Management..."); }
        function viewProjectAnalytics() { alert("Opening Project Analytics..."); }
        function manageQuality() { alert("Opening Quality Control..."); }
        function viewQualityReports() { alert("Opening Quality Reports..."); }
        function manageDocuments() { alert("Opening Document Center..."); }
        function viewDocumentAnalytics() { alert("Opening Document Analytics..."); }
        function viewAuditLogs() { alert("Opening Audit Logs..."); }
        function exportLogs() { alert("Exporting Audit Logs..."); }
        setInterval(function() {
            const timestamp = new Date().toLocaleString();
            document.querySelector(".status-bar").innerHTML = 
                "✅ System Status: Online | 📅 " + timestamp + " | 🔄 Real-time Updates: Active";
        }, 1000);
        document.addEventListener("DOMContentLoaded", function() {
            const currentPath = window.location.pathname;
            const navLinks = document.querySelectorAll(".nav-link");
            navLinks.forEach(link => {
                link.classList.remove("active");
                if (link.getAttribute("href") === currentPath) {
                    link.classList.add("active");
                }
            });
        });
    </script>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/dashboard.blade.php ENDPATH**/ ?>