{{-- Admin Projects Oversight với Layout System --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Projects Oversight - ZenaManage</title>
    
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
        
        /* Header */
        .header { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); box-shadow: var(--shadow-lg); border-bottom: 1px solid var(--gray-200); position: sticky; top: 0; z-index: 50; }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 var(--space-8); display: flex; justify-content: space-between; align-items: center; height: 80px; }
        .logo { display: flex; align-items: center; gap: var(--space-4); }
        .back-link { color: var(--primary); text-decoration: none; font-weight: 500; transition: color 0.2s; }
        .back-link:hover { color: #1d4ed8; }
        .page-title { font-size: 30px; font-weight: 700; color: var(--gray-900); margin: 0; }
        .page-subtitle { color: var(--gray-600); margin: 4px 0 0 0; font-size: 14px; }
        .header-actions { display: flex; align-items: center; gap: var(--space-4); }
        .notification-btn { position: relative; padding: var(--space-2); color: var(--gray-600); transition: all 0.2s; background: none; border: none; cursor: pointer; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; }
        .notification-btn:hover { color: var(--primary); background: var(--gray-100); }
        .notification-icon { font-size: 20px; line-height: 1; }
        .notification-badge { position: absolute; top: 6px; right: 6px; width: 8px; height: 8px; background: var(--danger); border-radius: 50%; }
        .user-menu { position: relative; }
        .user-btn { display: flex; align-items: center; gap: var(--space-2); padding: var(--space-2); border-radius: var(--radius-md); transition: background-color 0.2s; cursor: pointer; }
        .user-btn:hover { background: var(--gray-100); }
        .user-avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--primary); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .user-name { font-weight: 500; color: var(--gray-700); }
        .chevron-icon { color: var(--gray-500); font-size: 12px; transition: transform 0.2s ease; }
        .dropdown-menu { position: absolute; right: 0; top: 100%; margin-top: var(--space-2); background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-xl); border: 1px solid var(--gray-200); min-width: 200px; z-index: 100; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.2s ease; }
        .dropdown-menu.show { opacity: 1; visibility: visible; transform: translateY(0); }
        .dropdown-item { display: block; padding: var(--space-3) var(--space-4); color: var(--gray-700); text-decoration: none; transition: background-color 0.2s; }
        .dropdown-item:hover { background: var(--gray-50); }
        
        /* Navigation */
        .nav { background: var(--gray-800); box-shadow: var(--shadow-md); }
        .nav-content { max-width: 1200px; margin: 0 auto; padding: 0 var(--space-8); }
        .nav-links { display: flex; align-items: center; gap: var(--space-1); height: 48px; }
        .nav-link { padding: var(--space-3) var(--space-4); color: var(--gray-300); text-decoration: none; border-radius: var(--radius-md); transition: all 0.2s; font-weight: 500; }
        .nav-link:hover { background: var(--gray-700); color: white; }
        .nav-link.active { background: var(--gray-900); color: white; }
        
        /* Main Content */
        .main-content { max-width: 1200px; margin: 0 auto; padding: var(--space-8); }
        
        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--space-6); margin-bottom: var(--space-8); }
        .stat-card { background: white; border-radius: var(--radius-lg); box-shadow: var(--shadow-md); padding: var(--space-6); transition: transform 0.2s, box-shadow 0.2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
        .stat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4); }
        .stat-title { font-size: 14px; font-weight: 500; color: var(--gray-600); }
        .stat-icon { width: 48px; height: 48px; border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .stat-icon.blue { background: linear-gradient(135deg, var(--primary), #1d4ed8); color: white; }
        .stat-icon.green { background: linear-gradient(135deg, var(--secondary), #059669); color: white; }
        .stat-icon.purple { background: linear-gradient(135deg, var(--accent), #7c3aed); color: white; }
        .stat-icon.orange { background: linear-gradient(135deg, var(--warning), #d97706); color: white; }
        .stat-value { font-size: 32px; font-weight: 700; color: var(--gray-900); margin-bottom: var(--space-2); }
        .stat-change { display: flex; align-items: center; gap: var(--space-1); font-size: 14px; font-weight: 500; }
        .stat-change.positive { color: var(--secondary); }
        .stat-change.negative { color: var(--danger); }
        
        /* Projects Table */
        .table-container { background: white; border-radius: var(--radius-lg); box-shadow: var(--shadow-md); overflow: hidden; }
        .table-header { padding: var(--space-6); border-bottom: 1px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center; }
        .table-title { font-size: 18px; font-weight: 600; color: var(--gray-900); }
        .btn-primary { background: var(--primary); color: white; padding: var(--space-3) var(--space-4); border-radius: var(--radius-md); border: none; font-weight: 500; cursor: pointer; transition: background-color 0.2s; display: flex; align-items: center; gap: var(--space-2); }
        .btn-primary:hover { background: #2563eb; }
        
        .table { width: 100%; border-collapse: collapse; }
        .table th { background: var(--gray-50); padding: var(--space-4) var(--space-6); text-align: left; font-size: 12px; font-weight: 600; color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--gray-200); }
        .table td { padding: var(--space-4) var(--space-6); border-bottom: 1px solid var(--gray-200); }
        .table tbody tr:hover { background: var(--gray-50); }
        
        .project-info { display: flex; align-items: center; gap: var(--space-3); }
        .project-avatar { width: 40px; height: 40px; border-radius: var(--radius-lg); background: var(--primary); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .project-details h4 { font-weight: 600; color: var(--gray-900); margin-bottom: 2px; }
        .project-details p { font-size: 14px; color: var(--gray-500); }
        
        .status-badge { padding: var(--space-1) var(--space-3); border-radius: var(--radius-sm); font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
        .status-planning { background: #fef3c7; color: #92400e; }
        .status-active { background: #dcfce7; color: #166534; }
        .status-on-hold { background: #e5e7eb; color: #374151; }
        .status-completed { background: #dbeafe; color: #1e40af; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        
        .priority-badge { padding: var(--space-1) var(--space-3); border-radius: var(--radius-sm); font-size: 12px; font-weight: 600; }
        .priority-low { background: #d1fae5; color: #065f46; }
        .priority-medium { background: #fef3c7; color: #92400e; }
        .priority-high { background: #fee2e2; color: #991b1b; }
        .priority-critical { background: #fecaca; color: #7f1d1d; }
        
        .progress-bar { width: 100%; height: 8px; background: var(--gray-200); border-radius: var(--radius-sm); overflow: hidden; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, var(--primary), var(--secondary)); transition: width 0.3s ease; }
        
        .action-buttons { display: flex; gap: var(--space-2); }
        .btn-sm { padding: var(--space-2) var(--space-3); border-radius: var(--radius-sm); border: none; font-size: 12px; font-weight: 500; cursor: pointer; transition: all 0.2s; }
        .btn-edit { background: var(--primary); color: white; }
        .btn-edit:hover { background: #2563eb; }
        .btn-delete { background: var(--danger); color: white; }
        .btn-delete:hover { background: #dc2626; }
        .btn-view { background: var(--gray-600); color: white; }
        .btn-view:hover { background: var(--gray-700); }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-content { padding: 0 var(--space-4); }
            .main-content { padding: var(--space-4); }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: var(--space-4); }
            .nav-content { padding: 0 var(--space-4); }
            .nav-links { overflow-x: auto; }
            .table { font-size: 14px; }
            .table th, .table td { padding: var(--space-3) var(--space-4); }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .table-container { overflow-x: auto; }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        function toggleDropdown() {
            const dropdown = document.getElementById('dropdown-menu');
            const chevron = document.getElementById('chevron');
            
            if (dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
                chevron.style.transform = 'rotate(0deg)';
            } else {
                dropdown.classList.add('show');
                chevron.style.transform = 'rotate(180deg)';
            }
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('dropdown-menu');
            const userMenu = event.target.closest('.user-menu');
            
            if (!userMenu && dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
                document.getElementById('chevron').style.transform = 'rotate(0deg)';
            }
        });
        
        // Ensure Font Awesome loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Page loaded, Font Awesome should be available');
            
            // Check if Font Awesome is loaded
            const testIcon = document.createElement('i');
            testIcon.className = 'fas fa-bell';
            testIcon.style.position = 'absolute';
            testIcon.style.left = '-9999px';
            document.body.appendChild(testIcon);
            
            const computedStyle = window.getComputedStyle(testIcon, ':before');
            const fontFamily = computedStyle.getPropertyValue('font-family');
            
            if (fontFamily.includes('Font Awesome')) {
                console.log('Font Awesome is loaded successfully');
                // Replace emoji with Font Awesome icon
                const notificationIcon = document.querySelector('.notification-icon');
                if (notificationIcon) {
                    notificationIcon.innerHTML = '<i class="fas fa-bell"></i>';
                }
            } else {
                console.log('Font Awesome not loaded, using emoji fallback');
            }
            
            document.body.removeChild(testIcon);
        });
    </script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="logo">
                    <a href="/admin" class="back-link">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <div>
                        <h1 class="page-title">Projects Oversight</h1>
                        <p class="page-subtitle">Monitor all projects across tenants</p>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="notification-btn" onclick="alert('Notifications clicked!')">
                        <span class="notification-icon">🔔</span>
                        <span class="notification-badge"></span>
                    </button>
                    <div class="user-menu">
                        <button onclick="toggleDropdown()" class="user-btn">
                            <div class="user-avatar">AU</div>
                            <span class="user-name">Admin User</span>
                            <i class="fas fa-chevron-down chevron-icon" id="chevron"></i>
                        </button>
                        <div id="dropdown-menu" class="dropdown-menu">
                            <a href="#" class="dropdown-item">Profile</a>
                            <a href="#" class="dropdown-item">Settings</a>
                            <a href="#" class="dropdown-item">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Global Navigation -->
        <nav class="nav">
            <div class="nav-content">
                <div class="nav-links">
                    <a href="/admin" class="nav-link">Dashboard</a>
                    <a href="/admin/users" class="nav-link">Users</a>
                    <a href="/admin/tenants" class="nav-link">Tenants</a>
                    <a href="/admin/projects" class="nav-link active">Projects</a>
                    <a href="/admin/security" class="nav-link">Security</a>
                    <a href="/admin/settings" class="nav-link">Settings</a>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Total Projects</div>
                            <div class="stat-value">247</div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i>
                                <span>+18 this month</span>
                            </div>
                        </div>
                        <div class="stat-icon blue">
                            <i class="fas fa-project-diagram"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Active Projects</div>
                            <div class="stat-value">189</div>
                            <div class="stat-change positive">
                                <i class="fas fa-check-circle"></i>
                                <span>76% active rate</span>
                            </div>
                        </div>
                        <div class="stat-icon green">
                            <i class="fas fa-play-circle"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Completed</div>
                            <div class="stat-value">45</div>
                            <div class="stat-change positive">
                                <i class="fas fa-trophy"></i>
                                <span>18% completion rate</span>
                            </div>
                        </div>
                        <div class="stat-icon purple">
                            <i class="fas fa-trophy"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Overdue</div>
                            <div class="stat-value">13</div>
                            <div class="stat-change negative">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>5% overdue rate</span>
                            </div>
                        </div>
                        <div class="stat-icon orange">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Projects Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3 class="table-title">All Projects</h3>
                    <button class="btn-primary">
                        <i class="fas fa-plus"></i>
                        Add Project
                    </button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Tenant</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Progress</th>
                                <th>Due Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div class="project-info">
                                        <div class="project-avatar">EC</div>
                                        <div class="project-details">
                                            <h4>E-commerce Platform</h4>
                                            <p>Online shopping platform development</p>
                                        </div>
                                    </div>
                                </td>
                                <td>Acme Corporation</td>
                                <td>
                                    <span class="status-badge status-active">Active</span>
                                </td>
                                <td>
                                    <span class="priority-badge priority-high">High</span>
                                </td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: 75%"></div>
                                    </div>
                                    <span style="font-size: 12px; color: var(--gray-600);">75%</span>
                                </td>
                                <td>Dec 15, 2024</td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-sm btn-view">View</button>
                                        <button class="btn-sm btn-edit">Edit</button>
                                        <button class="btn-sm btn-delete">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            
                            <tr>
                                <td>
                                    <div class="project-info">
                                        <div class="project-avatar">MA</div>
                                        <div class="project-details">
                                            <h4>Mobile App</h4>
                                            <p>iOS and Android mobile application</p>
                                        </div>
                                    </div>
                                </td>
                                <td>TechCorp Solutions</td>
                                <td>
                                    <span class="status-badge status-planning">Planning</span>
                                </td>
                                <td>
                                    <span class="priority-badge priority-critical">Critical</span>
                                </td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: 15%"></div>
                                    </div>
                                    <span style="font-size: 12px; color: var(--gray-600);">15%</span>
                                </td>
                                <td>Mar 30, 2025</td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-sm btn-view">View</button>
                                        <button class="btn-sm btn-edit">Edit</button>
                                        <button class="btn-sm btn-delete">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            
                            <tr>
                                <td>
                                    <div class="project-info">
                                        <div class="project-avatar">WD</div>
                                        <div class="project-details">
                                            <h4>Website Design</h4>
                                            <p>Corporate website redesign</p>
                                        </div>
                                    </div>
                                </td>
                                <td>StartupMax</td>
                                <td>
                                    <span class="status-badge status-completed">Completed</span>
                                </td>
                                <td>
                                    <span class="priority-badge priority-medium">Medium</span>
                                </td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: 100%"></div>
                                    </div>
                                    <span style="font-size: 12px; color: var(--gray-600);">100%</span>
                                </td>
                                <td>Nov 20, 2024</td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-sm btn-view">View</button>
                                        <button class="btn-sm btn-edit">Edit</button>
                                        <button class="btn-sm btn-delete">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            
                            <tr>
                                <td>
                                    <div class="project-info">
                                        <div class="project-avatar">SA</div>
                                        <div class="project-details">
                                            <h4>System Analysis</h4>
                                            <p>Legacy system analysis and migration</p>
                                        </div>
                                    </div>
                                </td>
                                <td>Global Finance</td>
                                <td>
                                    <span class="status-badge status-on-hold">On Hold</span>
                                </td>
                                <td>
                                    <span class="priority-badge priority-low">Low</span>
                                </td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: 40%"></div>
                                    </div>
                                    <span style="font-size: 12px; color: var(--gray-600);">40%</span>
                                </td>
                                <td>Feb 28, 2025</td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-sm btn-view">View</button>
                                        <button class="btn-sm btn-edit">Edit</button>
                                        <button class="btn-sm btn-delete">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            
                            <tr>
                                <td>
                                    <div class="project-info">
                                        <div class="project-avatar">DB</div>
                                        <div class="project-details">
                                            <h4>Database Migration</h4>
                                            <p>Cloud database migration project</p>
                                        </div>
                                    </div>
                                </td>
                                <td>Design Co</td>
                                <td>
                                    <span class="status-badge status-active">Active</span>
                                </td>
                                <td>
                                    <span class="priority-badge priority-high">High</span>
                                </td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: 60%"></div>
                                    </div>
                                    <span style="font-size: 12px; color: var(--gray-600);">60%</span>
                                </td>
                                <td>Jan 15, 2025</td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-sm btn-view">View</button>
                                        <button class="btn-sm btn-edit">Edit</button>
                                        <button class="btn-sm btn-delete">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>