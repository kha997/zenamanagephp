<?php $__env->startSection('title', 'Enhanced Dashboard - ZenaManage'); ?>
<?php $__env->startSection('page-title', 'Enhanced Dashboard'); ?>

<?php $__env->startSection('content'); ?>
<style>
    .dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem; }
    
    .welcome-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem; border-radius: 12px; margin-bottom: 2rem; }
    .welcome-title { font-size: 2rem; margin-bottom: 0.5rem; }
    .welcome-subtitle { opacity: 0.9; margin-bottom: 1rem; }
    .welcome-actions { display: flex; gap: 1rem; }
    .welcome-btn { background: rgba(255,255,255,0.2); color: white; padding: 0.75rem 1.5rem; border: 1px solid rgba(255,255,255,0.3); border-radius: 6px; text-decoration: none; transition: all 0.3s; }
    .welcome-btn:hover { background: rgba(255,255,255,0.3); }
    
    .stats-overview { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
    .stat-card { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-align: center; position: relative; overflow: hidden; }
    .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: var(--accent-color); }
    .stat-card.projects { --accent-color: #28a745; }
    .stat-card.tasks { --accent-color: #ffc107; }
    .stat-card.team { --accent-color: #17a2b8; }
    .stat-card.documents { --accent-color: #6f42c1; }
    .stat-number { font-size: 2.5rem; font-weight: bold; color: var(--accent-color); margin-bottom: 0.5rem; }
    .stat-label { color: #666; font-weight: 500; }
    .stat-change { font-size: 0.8rem; margin-top: 0.5rem; }
    .stat-change.positive { color: #28a745; }
    .stat-change.negative { color: #dc3545; }
    
    .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
    .action-card { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: all 0.3s ease; cursor: pointer; }
    .action-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
    .action-icon { font-size: 2.5rem; margin-bottom: 1rem; }
    .action-title { font-size: 1.2rem; font-weight: 600; margin-bottom: 0.5rem; color: #333; }
    .action-description { color: #666; margin-bottom: 1rem; font-size: 0.9rem; }
    .action-btn { background: var(--btn-color); color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 6px; text-decoration: none; display: inline-block; transition: all 0.3s; }
    .action-btn:hover { background: var(--btn-hover); transform: translateY(-1px); }
    
    .action-card.projects { --btn-color: #28a745; --btn-hover: #218838; }
    .action-card.tasks { --btn-color: #ffc107; --btn-hover: #e0a800; }
    .action-card.team { --btn-color: #17a2b8; --btn-hover: #138496; }
    .action-card.documents { --btn-color: #6f42c1; --btn-hover: #5a32a3; }
    
    .activity-feed { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .section-title { font-size: 1.3rem; margin-bottom: 1rem; color: #333; display: flex; align-items: center; gap: 0.5rem; }
    .activity-item { padding: 1rem 0; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 1rem; }
    .activity-item:last-child { border-bottom: none; }
    .activity-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
    .activity-icon.project { background: #e8f5e8; color: #28a745; }
    .activity-icon.task { background: #fff3cd; color: #ffc107; }
    .activity-icon.team { background: #d1ecf1; color: #17a2b8; }
    .activity-icon.document { background: #e2e3f1; color: #6f42c1; }
    .activity-content { flex: 1; }
    .activity-text { color: #333; font-weight: 500; }
    .activity-time { color: #666; font-size: 0.8rem; margin-top: 0.25rem; }
    
    .chart-container { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .chart-title { font-size: 1.2rem; margin-bottom: 1rem; color: #333; }
    .chart-placeholder { height: 200px; background: #f8f9fa; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #666; border: 2px dashed #ddd; }
    
    .recent-projects { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .project-item { padding: 1rem 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    .project-item:last-child { border-bottom: none; }
    .project-info { flex: 1; }
    .project-name { font-weight: 600; color: #333; margin-bottom: 0.25rem; }
    .project-status { font-size: 0.8rem; padding: 0.25rem 0.5rem; border-radius: 12px; }
    .project-status.active { background: #d4edda; color: #155724; }
    .project-status.completed { background: #d1ecf1; color: #0c5460; }
    .project-status.pending { background: #fff3cd; color: #856404; }
    .project-progress { width: 100px; height: 6px; background: #e9ecef; border-radius: 3px; overflow: hidden; }
    .project-progress-bar { height: 100%; background: #007bff; transition: width 0.3s ease; }
    
    @media (max-width: 768px) {
        .dashboard-grid { grid-template-columns: 1fr; }
        .welcome-actions { flex-direction: column; }
    }
</style>

<div class="dashboard-grid">
    <div class="main-content">
        <!-- Welcome Section -->
        <div class="welcome-card">
            <h1 class="welcome-title">Welcome back, Super Admin! 👋</h1>
            <p class="welcome-subtitle">Here's what's happening with your projects today</p>
            <div class="welcome-actions">
                <a href="<?php echo e(route('projects.create')); ?>" class="welcome-btn">📋 Create Project</a>
                <a href="<?php echo e(route('tasks.create')); ?>" class="welcome-btn">📝 Add Task</a>
                <a href="<?php echo e(route('team.invite')); ?>" class="welcome-btn">👥 Invite Member</a>
            </div>
        </div>
        
        <!-- Statistics Overview -->
        <div class="stats-overview">
            <div class="stat-card projects">
                <div class="stat-number" id="projectCount">12</div>
                <div class="stat-label">Active Projects</div>
                <div class="stat-change positive">+2 this week</div>
            </div>
            <div class="stat-card tasks">
                <div class="stat-number" id="taskCount">48</div>
                <div class="stat-label">Pending Tasks</div>
                <div class="stat-change negative">-5 today</div>
            </div>
            <div class="stat-card team">
                <div class="stat-number" id="teamCount">8</div>
                <div class="stat-label">Team Members</div>
                <div class="stat-change positive">+1 this month</div>
            </div>
            <div class="stat-card documents">
                <div class="stat-number" id="documentCount">156</div>
                <div class="stat-label">Documents</div>
                <div class="stat-change positive">+12 this week</div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="action-card projects" onclick="location.href='<?php echo e(route('projects.create')); ?>'">
                <div class="action-icon">📋</div>
                <h3 class="action-title">Create Project</h3>
                <p class="action-description">Start a new project with detailed planning and team assignment</p>
                <a href="<?php echo e(route('projects.create')); ?>" class="action-btn">Create Project</a>
            </div>
            
            <div class="action-card tasks" onclick="location.href='<?php echo e(route('tasks.create')); ?>'">
                <div class="action-icon">📝</div>
                <h3 class="action-title">Add Task</h3>
                <p class="action-description">Create and assign tasks to team members with deadlines</p>
                <a href="<?php echo e(route('tasks.create')); ?>" class="action-btn">Add Task</a>
            </div>
            
            <div class="action-card team" onclick="location.href='<?php echo e(route('team.invite')); ?>'">
                <div class="action-icon">👥</div>
                <h3 class="action-title">Invite Member</h3>
                <p class="action-description">Add new team members and assign roles</p>
                <a href="<?php echo e(route('team.invite')); ?>" class="action-btn">Invite Member</a>
            </div>
            
            <div class="action-card documents" onclick="location.href='<?php echo e(route('documents.create')); ?>'">
                <div class="action-icon">📄</div>
                <h3 class="action-title">Upload Document</h3>
                <p class="action-description">Upload and manage project documents and files</p>
                <a href="<?php echo e(route('documents.create')); ?>" class="action-btn">Upload Document</a>
            </div>
        </div>
        
        <!-- Recent Projects -->
        <div class="recent-projects">
            <h2 class="section-title">📊 Recent Projects</h2>
            <div class="project-item">
                <div class="project-info">
                    <div class="project-name">Project Alpha - Office Building</div>
                    <div class="project-status active">Active</div>
                </div>
                <div class="project-progress">
                    <div class="project-progress-bar" style="width: 75%"></div>
                </div>
            </div>
            <div class="project-item">
                <div class="project-info">
                    <div class="project-name">Project Beta - Residential Complex</div>
                    <div class="project-status pending">Planning</div>
                </div>
                <div class="project-progress">
                    <div class="project-progress-bar" style="width: 25%"></div>
                </div>
            </div>
            <div class="project-item">
                <div class="project-info">
                    <div class="project-name">Project Gamma - Shopping Mall</div>
                    <div class="project-status completed">Completed</div>
                </div>
                <div class="project-progress">
                    <div class="project-progress-bar" style="width: 100%"></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="sidebar-content">
        <!-- Activity Feed -->
        <div class="activity-feed">
            <h2 class="section-title">🔄 Recent Activity</h2>
            <div class="activity-item">
                <div class="activity-icon project">📋</div>
                <div class="activity-content">
                    <div class="activity-text">Project Alpha completed design phase</div>
                    <div class="activity-time">2 hours ago</div>
                </div>
            </div>
            <div class="activity-item">
                <div class="activity-icon task">📝</div>
                <div class="activity-content">
                    <div class="activity-text">New task assigned: Site Inspection</div>
                    <div class="activity-time">4 hours ago</div>
                </div>
            </div>
            <div class="activity-item">
                <div class="activity-icon document">📄</div>
                <div class="activity-content">
                    <div class="activity-text">Document uploaded: Building Plans</div>
                    <div class="activity-time">6 hours ago</div>
                </div>
            </div>
            <div class="activity-item">
                <div class="activity-icon team">👥</div>
                <div class="activity-content">
                    <div class="activity-text">Team member John Doe joined</div>
                    <div class="activity-time">1 day ago</div>
                </div>
            </div>
        </div>
        
        <!-- Chart Placeholder -->
        <div class="chart-container">
            <h3 class="chart-title">📈 Project Progress</h3>
            <div class="chart-placeholder">
                📊 Chart visualization will be here
            </div>
        </div>
    </div>
</div>

<script>
    // Animate counters
    function animateCounter(element, target) {
        let current = 0;
        const increment = target / 50;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            element.textContent = Math.floor(current);
        }, 30);
    }
    
    // Start animations when page loads
    document.addEventListener('DOMContentLoaded', function() {
        animateCounter(document.getElementById('projectCount'), 12);
        animateCounter(document.getElementById('taskCount'), 48);
        animateCounter(document.getElementById('teamCount'), 8);
        animateCounter(document.getElementById('documentCount'), 156);
    });
    
    // Real-time updates simulation
    setInterval(() => {
        // Simulate real-time data updates
        const taskCount = document.getElementById('taskCount');
        const currentCount = parseInt(taskCount.textContent);
        const newCount = currentCount + Math.floor(Math.random() * 3) - 1;
        if (newCount >= 0) {
            taskCount.textContent = newCount;
        }
    }, 30000); // Update every 30 seconds
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/dashboard-enhanced.blade.php ENDPATH**/ ?>