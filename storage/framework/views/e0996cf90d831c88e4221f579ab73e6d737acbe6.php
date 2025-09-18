<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management Dashboard - ZENA</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .status-bar { 
            background: #d4edda; 
            border: 1px solid #c3e6cb; 
            border-radius: 8px; 
            padding: 15px; 
            margin-bottom: 20px; 
            color: #155724; 
        }
        
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .metric-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        .metric-value { font-size: 2rem; font-weight: bold; color: #667eea; margin-bottom: 5px; }
        .metric-label { color: #666; font-size: 0.9rem; }
        .charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .chart-container { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .chart-title { font-size: 1.2rem; margin-bottom: 15px; color: #333; }
        .table-container { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .table th { background: #f8f9fa; font-weight: bold; }
        .btn { padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 0.9rem; margin: 5px; }
        .btn-primary { background: #667eea; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: bold; }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .status-pending { background: #fff3cd; color: #856404; }
        .search-box { margin-bottom: 20px; }
        .search-box input { padding: 10px; border: 1px solid #ddd; border-radius: 6px; width: 300px; }
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
                        <a href="/dashboard/users" class="nav-link active">
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
                    <h1 class="page-title">👥 User Management</h1>
                    <div class="user-info">
                        <span>Xin chào, User!</span>
                        <span class="role-badge">User Manager</span>
                    </div>
                </div>
            </div>
            
            <div class="status-bar">
                ✅ System Status: Online | 📅 <?php echo e(now()->format('Y-m-d H:i:s')); ?> | 🔄 Real-time Updates: Active
            </div>
            <h1 class="dashboard-title">👥 User Management Overview</h1>
            
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-value" id="totalUsers">2,847</div>
                    <div class="metric-label">Total Users</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value" id="newUsers">156</div>
                    <div class="metric-label">New This Week</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value" id="activeUsers">2,678</div>
                    <div class="metric-label">Active Users</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value" id="premiumUsers">423</div>
                    <div class="metric-label">Premium Users</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value" id="retentionRate">94.2%</div>
                    <div class="metric-label">Retention Rate</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value" id="avgSession">12.5m</div>
                    <div class="metric-label">Avg Session</div>
                </div>
            </div>
            
            <div class="charts-grid">
                <div class="chart-container">
                    <h3 class="chart-title">User Registration Trend</h3>
                    <canvas id="registrationChart" width="400" height="200"></canvas>
                </div>
                <div class="chart-container">
                    <h3 class="chart-title">User Activity Distribution</h3>
                    <canvas id="activityChart" width="300" height="200"></canvas>
                </div>
            </div>
            
            <div class="table-container">
                <div class="search-box">
                    <input type="text" id="userSearch" placeholder="Search users by name or email..." onkeyup="searchUsers()">
                </div>
                <h3 class="chart-title">Recent Users</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="usersTable">
                        <tr>
                            <td>#001</td>
                            <td>John Smith</td>
                            <td>john@example.com</td>
                            <td>Admin</td>
                            <td><span class="status-badge status-active">Active</span></td>
                            <td>2 hours ago</td>
                            <td>
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('read', App\Models\User::class)): ?>
                                    <button class="btn btn-primary" onclick="viewUser(1)">View</button>
                                <?php endif; ?>
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('update', App\Models\User::class)): ?>
                                    <button class="btn btn-secondary" onclick="editUser(1)">Edit</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>#002</td>
                            <td>Sarah Johnson</td>
                            <td>sarah@example.com</td>
                            <td>User</td>
                            <td><span class="status-badge status-active">Active</span></td>
                            <td>1 day ago</td>
                            <td>
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('read', App\Models\User::class)): ?>
                                    <button class="btn btn-primary" onclick="viewUser(2)">View</button>
                                <?php endif; ?>
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('update', App\Models\User::class)): ?>
                                    <button class="btn btn-secondary" onclick="editUser(2)">Edit</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>#003</td>
                            <td>Mike Wilson</td>
                            <td>mike@example.com</td>
                            <td>Premium</td>
                            <td><span class="status-badge status-pending">Pending</span></td>
                            <td>3 days ago</td>
                            <td>
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('read', App\Models\User::class)): ?>
                                    <button class="btn btn-primary" onclick="viewUser(3)">View</button>
                                <?php endif; ?>
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('update', App\Models\User::class)): ?>
                                    <button class="btn btn-secondary" onclick="editUser(3)">Edit</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>#004</td>
                            <td>Emily Davis</td>
                            <td>emily@example.com</td>
                            <td>User</td>
                            <td><span class="status-badge status-inactive">Inactive</span></td>
                            <td>1 week ago</td>
                            <td>
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('read', App\Models\User::class)): ?>
                                    <button class="btn btn-primary" onclick="viewUser(4)">View</button>
                                <?php endif; ?>
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('update', App\Models\User::class)): ?>
                                    <button class="btn btn-secondary" onclick="editUser(4)">Edit</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>#005</td>
                            <td>David Brown</td>
                            <td>david@example.com</td>
                            <td>Moderator</td>
                            <td><span class="status-badge status-active">Active</span></td>
                            <td>30 minutes ago</td>
                            <td>
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('read', App\Models\User::class)): ?>
                                    <button class="btn btn-primary" onclick="viewUser(5)">View</button>
                                <?php endif; ?>
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('update', App\Models\User::class)): ?>
                                    <button class="btn btn-secondary" onclick="editUser(5)">Edit</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <script>
        // Registration Chart
        const registrationCtx = document.getElementById('registrationChart').getContext('2d');
        const registrationChart = new Chart(registrationCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'New Registrations',
                    data: [120, 150, 180, 200, 175, 220],
                    backgroundColor: '#667eea',
                    borderColor: '#5a6fd8',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Activity Chart
        const activityCtx = document.getElementById('activityChart').getContext('2d');
        const activityChart = new Chart(activityCtx, {
            type: 'pie',
            data: {
                labels: ['Active', 'Inactive', 'Pending'],
                datasets: [{
                    data: [70, 20, 10],
                    backgroundColor: [
                        '#28a745',
                        '#dc3545',
                        '#ffc107'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Functions
        function addUser() {
            window.location.href = '/dashboard/users';
        }
        
        function exportUsers() {
            window.location.href = '/dashboard/users';
        }
        
        function viewUser(userId) {
            window.location.href = '/dashboard/users';
        }
        
        function editUser(userId) {
            window.location.href = '/dashboard/users';
        }
        
        function searchUsers() {
            const searchTerm = document.getElementById('userSearch').value.toLowerCase();
            const rows = document.querySelectorAll('#usersTable tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        // Real-time updates
        setInterval(() => {
            // Simulate new user registrations
            const newUsers = document.getElementById('newUsers');
            const currentValue = parseInt(newUsers.textContent);
            newUsers.textContent = currentValue + Math.floor(Math.random() * 3);
            
            // Update total users
            const totalUsers = document.getElementById('totalUsers');
            const totalValue = parseInt(totalUsers.textContent);
            totalUsers.textContent = totalValue + Math.floor(Math.random() * 2);
        }, 10000);
    </script>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/dashboards/users.blade.php ENDPATH**/ ?>