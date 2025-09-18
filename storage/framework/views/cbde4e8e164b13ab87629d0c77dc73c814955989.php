<?php $__env->startSection('title', 'Dashboard - ZenaManage'); ?>
<?php $__env->startSection('page-title', 'Dashboard'); ?>

<?php $__env->startSection('content'); ?>
<style>
    .welcome-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem; border-radius: 12px; margin-bottom: 2rem; }
    .welcome-title { font-size: 2rem; margin-bottom: 0.5rem; }
    .welcome-subtitle { opacity: 0.9; }
    
    .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
    
    .action-card { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s; }
    .action-card:hover { transform: translateY(-2px); box-shadow: 0 8px 15px rgba(0,0,0,0.15); }
    
    .action-icon { font-size: 2rem; margin-bottom: 1rem; }
    .action-title { font-size: 1.2rem; font-weight: 600; margin-bottom: 0.5rem; color: #333; }
    .action-description { color: #666; margin-bottom: 1rem; font-size: 0.9rem; }
    .action-btn { background: #007bff; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 6px; text-decoration: none; display: inline-block; transition: background 0.2s; }
    .action-btn:hover { background: #0056b3; }
    
    .action-card.projects .action-icon { color: #28a745; }
    .action-card.projects .action-btn { background: #28a745; }
    .action-card.projects .action-btn:hover { background: #218838; }
    
    .action-card.tasks .action-icon { color: #ffc107; }
    .action-card.tasks .action-btn { background: #ffc107; color: #212529; }
    .action-card.tasks .action-btn:hover { background: #e0a800; }
    
    .action-card.team .action-icon { color: #17a2b8; }
    .action-card.team .action-btn { background: #17a2b8; }
    .action-card.team .action-btn:hover { background: #138496; }
    
    .action-card.documents .action-icon { color: #6f42c1; }
    .action-card.documents .action-btn { background: #6f42c1; }
    .action-card.documents .action-btn:hover { background: #5a32a3; }
    
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
    .stat-card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
    .stat-number { font-size: 2rem; font-weight: bold; color: #007bff; }
    .stat-label { color: #666; margin-top: 0.5rem; }
    
    .recent-section { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .section-title { font-size: 1.3rem; margin-bottom: 1rem; color: #333; }
    .recent-item { padding: 0.75rem 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    .recent-item:last-child { border-bottom: none; }
    .recent-text { color: #333; }
    .recent-time { color: #666; font-size: 0.9rem; }
</style>

<div class="container">
        <div class="welcome-section">
            <h1 class="welcome-title">Welcome to ZenaManage</h1>
            <p class="welcome-subtitle">Your comprehensive project management solution</p>
        </div>

        <div class="quick-actions">
            <div class="action-card projects">
                <div class="action-icon">📋</div>
                <h3 class="action-title">Create Project</h3>
                <p class="action-description">Start a new project with detailed planning and team assignment</p>
                <a href="<?php echo e(route('projects.create')); ?>" class="action-btn">Create Project</a>
            </div>

            <div class="action-card tasks">
                <div class="action-icon">📝</div>
                <h3 class="action-title">Add Task</h3>
                <p class="action-description">Create and assign tasks to team members with deadlines</p>
                <a href="<?php echo e(route('tasks.create')); ?>" class="action-btn">Add Task</a>
            </div>

            <div class="action-card team">
                <div class="action-icon">👥</div>
                <h3 class="action-title">Invite Member</h3>
                <p class="action-description">Add new team members and assign roles</p>
                <a href="<?php echo e(route('team.invite')); ?>" class="action-btn">Invite Member</a>
            </div>

            <div class="action-card documents">
                <div class="action-icon">📄</div>
                <h3 class="action-title">Upload Document</h3>
                <p class="action-description">Upload and manage project documents and files</p>
                <a href="<?php echo e(route('documents.create')); ?>" class="action-btn">Upload Document</a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">12</div>
                <div class="stat-label">Active Projects</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">48</div>
                <div class="stat-label">Pending Tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">8</div>
                <div class="stat-label">Team Members</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">156</div>
                <div class="stat-label">Documents</div>
            </div>
        </div>

        <div class="recent-section">
            <h2 class="section-title">Recent Activity</h2>
            <div class="recent-item">
                <span class="recent-text">Project Alpha - Design phase completed</span>
                <span class="recent-time">2 hours ago</span>
            </div>
            <div class="recent-item">
                <span class="recent-text">New task assigned: Site Inspection</span>
                <span class="recent-time">4 hours ago</span>
            </div>
            <div class="recent-item">
                <span class="recent-text">Document uploaded: Building Plans</span>
                <span class="recent-time">6 hours ago</span>
            </div>
            <div class="recent-item">
                <span class="recent-text">Team member John Doe joined</span>
                <span class="recent-time">1 day ago</span>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/dashboard.blade.php ENDPATH**/ ?>