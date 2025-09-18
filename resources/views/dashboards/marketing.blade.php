<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketing Dashboard - ZENA</title>
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
            background: #f59e0b;
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
            border-left: 4px solid #f59e0b; 
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
            color: #f59e0b; 
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
            background: #f59e0b; 
            color: white; 
        }
        .btn-primary:hover { 
            background: #d97706; 
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
                        <a href="/dashboard/marketing" class="nav-link active">
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
                    <h1 class="page-title">📊 Marketing Dashboard</h1>
                    <div class="user-info">
                        <span>Xin chào, User!</span>
                        <span class="role-badge">Marketing Manager</span>
                    </div>
                </div>
            </div>
            
            <div class="status-bar">
                ✅ System Status: Online | 📅 {{ now()->format('Y-m-d H:i:s') }} | 🔄 Real-time Updates: Active
            </div>

            <!-- Marketing Modules -->
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <div class="dashboard-title">📈 Campaign Performance</div>
                    <div class="dashboard-description">
                        Track marketing campaign performance, reach, engagement and conversion rates.
                    </div>
                    <div class="dashboard-stats">
                        <div class="stat">
                            <div class="stat-number">12</div>
                            <div class="stat-label">Active Campaigns</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">3.2%</div>
                            <div class="stat-label">CTR</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">45K</div>
                            <div class="stat-label">Impressions</div>
                        </div>
                    </div>
                    <div class="dashboard-actions">
                        <button class="btn btn-primary" onclick="viewCampaigns()">View Campaigns</button>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="dashboard-title">🎯 Lead Generation</div>
                    <div class="dashboard-description">
                        Monitor lead generation, qualification and conversion funnel metrics.
                    </div>
                    <div class="dashboard-stats">
                        <div class="stat">
                            <div class="stat-number">1,247</div>
                            <div class="stat-label">New Leads</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">23%</div>
                            <div class="stat-label">Conversion</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">$125</div>
                            <div class="stat-label">Cost/Lead</div>
                        </div>
                    </div>
                    <div class="dashboard-actions">
                        <button class="btn btn-primary" onclick="viewLeads()">View Leads</button>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="dashboard-title">📱 Social Media</div>
                    <div class="dashboard-description">
                        Track social media engagement, followers growth and content performance.
                    </div>
                    <div class="dashboard-stats">
                        <div class="stat">
                            <div class="stat-number">25K</div>
                            <div class="stat-label">Followers</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">4.5%</div>
                            <div class="stat-label">Engagement</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">156</div>
                            <div class="stat-label">Posts</div>
                        </div>
                    </div>
                    <div class="dashboard-actions">
                        <button class="btn btn-primary" onclick="viewSocialMedia()">View Social</button>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="dashboard-title">📧 Email Marketing</div>
                    <div class="dashboard-description">
                        Monitor email campaign performance, open rates and click-through rates.
                    </div>
                    <div class="dashboard-stats">
                        <div class="stat">
                            <div class="stat-number">28%</div>
                            <div class="stat-label">Open Rate</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">5.2%</div>
                            <div class="stat-label">Click Rate</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">2.1%</div>
                            <div class="stat-label">Unsubscribe</div>
                        </div>
                    </div>
                    <div class="dashboard-actions">
                        <button class="btn btn-primary" onclick="viewEmailMarketing()">View Emails</button>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="dashboard-title">🔍 SEO Analytics</div>
                    <div class="dashboard-description">
                        Track search engine optimization performance and organic traffic.
                    </div>
                    <div class="dashboard-stats">
                        <div class="stat">
                            <div class="stat-number">1,247</div>
                            <div class="stat-label">Keywords</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">45K</div>
                            <div class="stat-label">Organic Traffic</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">78</div>
                            <div class="stat-label">Domain Score</div>
                        </div>
                    </div>
                    <div class="dashboard-actions">
                        <button class="btn btn-primary" onclick="viewSEO()">View SEO</button>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="dashboard-title">💰 Marketing ROI</div>
                    <div class="dashboard-description">
                        Calculate return on investment for marketing activities and campaigns.
                    </div>
                    <div class="dashboard-stats">
                        <div class="stat">
                            <div class="stat-number">$45K</div>
                            <div class="stat-label">Revenue</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">$12K</div>
                            <div class="stat-label">Spent</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">375%</div>
                            <div class="stat-label">ROI</div>
                        </div>
                    </div>
                    <div class="dashboard-actions">
                        <button class="btn btn-primary" onclick="viewROI()">View ROI</button>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        function viewCampaigns() {
            window.location.href = '/dashboard/marketing';
        }
        
        function viewLeads() {
            window.location.href = '/dashboard/marketing';
        }
        
        function viewSocialMedia() {
            window.location.href = '/dashboard/marketing';
        }
        
        function viewEmailMarketing() {
            window.location.href = '/dashboard/marketing';
        }
        
        function viewSEO() {
            window.location.href = '/dashboard/marketing';
        }
        
        function viewROI() {
            window.location.href = '/dashboard/marketing';
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
