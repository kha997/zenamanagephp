<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Detail - ZENA</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8f9fa; color: #333; }
        .app-container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; position: fixed; height: 100vh; overflow-y: auto; z-index: 1000; }
        .sidebar-header { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .logo { font-size: 1.5rem; font-weight: bold; color: white; text-decoration: none; }
        .sidebar-nav { padding: 20px 0; }
        .nav-item { margin-bottom: 5px; }
        .nav-link { display: flex; align-items: center; padding: 12px 20px; color: white; text-decoration: none; transition: all 0.3s; }
        .nav-link:hover { background: rgba(255,255,255,0.1); }
        .nav-link.active { background: rgba(255,255,255,0.2); }
        .nav-icon { margin-right: 10px; width: 20px; }
        .main-content { margin-left: 250px; flex: 1; padding: 20px; }
        .header { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .header-content { display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 2rem; color: #333; }
        .user-info { display: flex; align-items: center; gap: 10px; }
        .role-badge { background: #8b5cf6; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; margin-left: 10px; }
        .task-header { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .task-title { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .task-name { font-size: 1.8rem; font-weight: bold; color: #333; }
        .task-status { padding: 6px 12px; border-radius: 20px; font-size: 0.9rem; font-weight: 600; }
        .status-in-progress { background: #dbeafe; color: #1e40af; }
        .btn { padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 0.9rem; transition: all 0.3s; margin-right: 10px; }
        .btn-primary { background: #8b5cf6; color: white; }
        .btn-primary:hover { background: #7c3aed; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .task-details { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .detail-row { display: flex; margin-bottom: 15px; }
        .detail-label { font-weight: 600; width: 150px; color: #374151; }
        .detail-value { color: #666; }
    </style>
</head>
<body>
    <div class="app-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="/dashboard" class="logo">🚀 ZENA</a>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="nav-item"><a href="/dashboard" class="nav-link"><i class="fas fa-tachometer-alt nav-icon"></i>Dashboard</a></li>
                    <li class="nav-item"><a href="/dashboard/sales" class="nav-link"><i class="fas fa-chart-line nav-icon"></i>Sales Analytics</a></li>
                    <li class="nav-item"><a href="/dashboard/users" class="nav-link"><i class="fas fa-users nav-icon"></i>User Management</a></li>
                    <li class="nav-item"><a href="/dashboard/performance" class="nav-link"><i class="fas fa-tachometer-alt nav-icon"></i>Performance</a></li>
                    <li class="nav-item"><a href="/dashboard/marketing" class="nav-link"><i class="fas fa-bullhorn nav-icon"></i>Marketing</a></li>
                    <li class="nav-item"><a href="/dashboard/financial" class="nav-link"><i class="fas fa-dollar-sign nav-icon"></i>Financial</a></li>
                    <li class="nav-item"><a href="/dashboard/projects" class="nav-link"><i class="fas fa-project-diagram nav-icon"></i>Projects</a></li>
                    <li class="nav-item"><a href="/tasks" class="nav-link active"><i class="fas fa-tasks nav-icon"></i>Tasks</a></li>
                    <li class="nav-item"><a href="/team" class="nav-link"><i class="fas fa-users nav-icon"></i>Team</a></li>
                    <li class="nav-item"><a href="/admin" class="nav-link"><i class="fas fa-cog nav-icon"></i>Admin</a></li>
                </ul>
            </nav>
        </aside>
        <main class="main-content">
            <div class="header">
                <div class="header-content">
                    <h1 class="page-title">📋 Task Detail</h1>
                    <div class="user-info">
                        <span>Xin chào, User!</span>
                        <span class="role-badge">Project Manager</span>
                    </div>
                </div>
            </div>
            <div class="task-header">
                <div class="task-title">
                    <div>
                        <span class="task-name">Design UI Mockups</span>
                    </div>
                    <span class="task-status status-in-progress">In Progress</span>
                </div>
                <div style="margin: 15px 0;">
                    <strong>Project:</strong> Villa Project Alpha | 
                    <strong>Assignee:</strong> Sarah Johnson | 
                    <strong>Priority:</strong> High
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-primary" onclick="editTask()">
                        <i class="fas fa-edit"></i> Edit Task
                    </button>
                    <button class="btn btn-secondary" onclick="changeStatus()">
                        <i class="fas fa-check"></i> Change Status
                    </button>
                    <button class="btn btn-secondary" onclick="addComment()">
                        <i class="fas fa-comment"></i> Add Comment
                    </button>
                </div>
            </div>
            <div class="task-details">
                <h3 style="margin-bottom: 20px;">Task Details</h3>
                <div class="detail-row">
                    <div class="detail-label">Description:</div>
                    <div class="detail-value">Create comprehensive UI mockups for the villa project including all rooms, exterior design, and landscaping features.</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Due Date:</div>
                    <div class="detail-value">January 20, 2025</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Progress:</div>
                    <div class="detail-value">75% Complete</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Estimated Hours:</div>
                    <div class="detail-value">40 hours</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Spent Hours:</div>
                    <div class="detail-value">30 hours</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Created:</div>
                    <div class="detail-value">January 10, 2025</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Last Updated:</div>
                    <div class="detail-value">January 15, 2025</div>
                </div>
            </div>
        </main>
    </div>
    <script>
        function editTask() {
            window.location.href = "/tasks/<?php echo e($task); ?>/edit";
        }
        function changeStatus() {
            alert("Change status functionality will be implemented");
        }
        function addComment() {
            alert("Add comment functionality will be implemented");
        }
    </script>
</body>
</html><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/tasks/show.blade.php ENDPATH**/ ?>