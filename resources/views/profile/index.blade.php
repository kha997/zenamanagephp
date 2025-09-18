@extends('layouts.app')

@section('title', 'User Profile - ZenaManage')
@section('page-title', 'User Profile')

@section('content')
<style>
    .profile-container { max-width: 800px; margin: 0 auto; }
    
    .profile-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem; border-radius: 12px; margin-bottom: 2rem; text-align: center; }
    .profile-avatar { width: 120px; height: 120px; background: rgba(255,255,255,0.2); border-radius: 50%; margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; font-size: 3rem; border: 4px solid rgba(255,255,255,0.3); }
    .profile-name { font-size: 2rem; font-weight: bold; margin-bottom: 0.5rem; }
    .profile-role { opacity: 0.9; font-size: 1.1rem; }
    
    .profile-tabs { display: flex; background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 2rem; overflow: hidden; }
    .tab-button { flex: 1; padding: 1rem; background: none; border: none; cursor: pointer; font-weight: 500; color: #666; transition: all 0.3s; }
    .tab-button.active { background: #007bff; color: white; }
    .tab-button:hover:not(.active) { background: #f8f9fa; }
    
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    
    .form-section { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 2rem; }
    .form-title { font-size: 1.3rem; margin-bottom: 1.5rem; color: #333; }
    .form-group { margin-bottom: 1.5rem; }
    .form-label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #333; }
    .form-input { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem; transition: border-color 0.3s; }
    .form-input:focus { outline: none; border-color: #007bff; }
    .form-textarea { min-height: 100px; resize: vertical; }
    .form-select { background: white; }
    
    .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; text-decoration: none; display: inline-block; transition: all 0.3s; }
    .btn-primary { background: #007bff; color: white; }
    .btn-primary:hover { background: #0056b3; }
    .btn-secondary { background: #6c757d; color: white; margin-left: 1rem; }
    .btn-secondary:hover { background: #545b62; }
    
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
    .stat-card { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-align: center; }
    .stat-number { font-size: 2rem; font-weight: bold; color: #007bff; margin-bottom: 0.5rem; }
    .stat-label { color: #666; }
    
    .activity-list { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .activity-item { padding: 1rem 0; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 1rem; }
    .activity-item:last-child { border-bottom: none; }
    .activity-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; background: #f8f9fa; }
    .activity-content { flex: 1; }
    .activity-text { color: #333; font-weight: 500; }
    .activity-time { color: #666; font-size: 0.8rem; margin-top: 0.25rem; }
</style>

<div class="profile-container">
    <!-- Profile Header -->
    <div class="profile-header">
        <div class="profile-avatar">👤</div>
        <h1 class="profile-name">Super Admin</h1>
        <p class="profile-role">System Administrator</p>
    </div>
    
    <!-- Profile Tabs -->
    <div class="profile-tabs">
        <button class="tab-button active" onclick="showTab('overview')">📊 Overview</button>
        <button class="tab-button" onclick="showTab('personal')">👤 Personal Info</button>
        <button class="tab-button" onclick="showTab('security')">🔒 Security</button>
        <button class="tab-button" onclick="showTab('activity')">📝 Activity</button>
    </div>
    
    <!-- Overview Tab -->
    <div id="overview" class="tab-content active">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">12</div>
                <div class="stat-label">Projects Managed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">48</div>
                <div class="stat-label">Tasks Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">156</div>
                <div class="stat-label">Documents Uploaded</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">8</div>
                <div class="stat-label">Team Members</div>
            </div>
        </div>
        
        <div class="form-section">
            <h3 class="form-title">📈 Recent Performance</h3>
            <p>Your productivity has increased by 25% this month compared to last month. Great job!</p>
            <div style="margin-top: 1rem; padding: 1rem; background: #d4edda; border-radius: 6px; color: #155724;">
                ✅ All projects are on track<br>
                ✅ No overdue tasks<br>
                ✅ Team satisfaction: 95%
            </div>
        </div>
    </div>
    
    <!-- Personal Info Tab -->
    <div id="personal" class="tab-content">
        <div class="form-section">
            <h3 class="form-title">👤 Personal Information</h3>
            <form>
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-input" value="Super Admin">
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-input" value="admin@zena.local">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" class="form-input" value="+84 123 456 789">
                </div>
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <select class="form-input form-select">
                        <option>Administration</option>
                        <option>Project Management</option>
                        <option>Engineering</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Bio</label>
                    <textarea class="form-input form-textarea" placeholder="Tell us about yourself...">System administrator with 5+ years of experience in project management and team leadership.</textarea>
                </div>
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <button type="button" class="btn btn-secondary">Cancel</button>
            </form>
        </div>
    </div>
    
    <!-- Security Tab -->
    <div id="security" class="tab-content">
        <div class="form-section">
            <h3 class="form-title">🔒 Security Settings</h3>
            <form>
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input type="password" class="form-input" placeholder="Enter current password">
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" class="form-input" placeholder="Enter new password">
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" class="form-input" placeholder="Confirm new password">
                </div>
                <button type="submit" class="btn btn-primary">Update Password</button>
            </form>
        </div>
        
        <div class="form-section">
            <h3 class="form-title">🔐 Two-Factor Authentication</h3>
            <p>Add an extra layer of security to your account.</p>
            <button type="button" class="btn btn-primary">Enable 2FA</button>
        </div>
    </div>
    
    <!-- Activity Tab -->
    <div id="activity" class="tab-content">
        <div class="activity-list">
            <h3 class="form-title">📝 Recent Activity</h3>
            <div class="activity-item">
                <div class="activity-icon">📋</div>
                <div class="activity-content">
                    <div class="activity-text">Created new project: Office Building Alpha</div>
                    <div class="activity-time">2 hours ago</div>
                </div>
            </div>
            <div class="activity-item">
                <div class="activity-icon">📝</div>
                <div class="activity-content">
                    <div class="activity-text">Assigned task to John Doe: Site Inspection</div>
                    <div class="activity-time">4 hours ago</div>
                </div>
            </div>
            <div class="activity-item">
                <div class="activity-icon">📄</div>
                <div class="activity-content">
                    <div class="activity-text">Uploaded document: Building Plans v2.1</div>
                    <div class="activity-time">6 hours ago</div>
                </div>
            </div>
            <div class="activity-item">
                <div class="activity-icon">👥</div>
                <div class="activity-content">
                    <div class="activity-text">Invited new team member: Jane Smith</div>
                    <div class="activity-time">1 day ago</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function showTab(tabName) {
        // Hide all tab contents
        const tabContents = document.querySelectorAll('.tab-content');
        tabContents.forEach(content => content.classList.remove('active'));
        
        // Remove active class from all tab buttons
        const tabButtons = document.querySelectorAll('.tab-button');
        tabButtons.forEach(button => button.classList.remove('active'));
        
        // Show selected tab content
        document.getElementById(tabName).classList.add('active');
        
        // Add active class to clicked button
        event.target.classList.add('active');
    }
</script>
@endsection
