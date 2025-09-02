@extends('layouts.app')

@section('title', 'Dashboard - Z.E.N.A Project Management')

@section('content')
<div class="dashboard-container">
    <!-- Header Section -->
    <div class="dashboard-header">
        <div class="header-content">
            <h1 class="dashboard-title">Dashboard</h1>
            <p class="dashboard-subtitle">Tổng quan hệ thống quản lý dự án Z.E.N.A</p>
        </div>
        <div class="header-actions">
            <div class="date-range-picker">
                <input type="date" id="startDate" class="form-control">
                <span>đến</span>
                <input type="date" id="endDate" class="form-control">
                <button class="btn btn-primary" id="applyDateFilter">Áp dụng</button>
            </div>
            <div class="refresh-controls">
                <button class="btn btn-outline-secondary" id="refreshDashboard">
                    <i class="fas fa-sync-alt"></i> Làm mới
                </button>
                <div class="auto-refresh-toggle">
                    <label class="switch">
                        <input type="checkbox" id="autoRefresh">
                        <span class="slider"></span>
                    </label>
                    <span>Tự động làm mới</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card projects-card">
            <div class="stat-icon">
                <i class="fas fa-project-diagram"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-number" id="totalProjects">0</h3>
                <p class="stat-label">Tổng số dự án</p>
                <div class="stat-breakdown">
                    <span class="active-projects">Đang thực hiện: <strong id="activeProjects">0</strong></span>
                    <span class="completed-projects">Hoàn thành: <strong id="completedProjects">0</strong></span>
                </div>
            </div>
            <div class="stat-trend">
                <span class="trend-indicator" id="projectsTrend">+0%</span>
            </div>
        </div>

        <div class="stat-card tasks-card">
            <div class="stat-icon">
                <i class="fas fa-tasks"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-number" id="totalTasks">0</h3>
                <p class="stat-label">Tổng số công việc</p>
                <div class="stat-breakdown">
                    <span class="pending-tasks">Chờ xử lý: <strong id="pendingTasks">0</strong></span>
                    <span class="overdue-tasks">Quá hạn: <strong id="overdueTasks">0</strong></span>
                </div>
            </div>
            <div class="stat-trend">
                <span class="trend-indicator" id="tasksTrend">+0%</span>
            </div>
        </div>

        <div class="stat-card budget-card">
            <div class="stat-icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-number" id="totalBudget">0 VNĐ</h3>
                <p class="stat-label">Tổng ngân sách</p>
                <div class="stat-breakdown">
                    <span class="used-budget">Đã sử dụng: <strong id="usedBudget">0 VNĐ</strong></span>
                    <span class="remaining-budget">Còn lại: <strong id="remainingBudget">0 VNĐ</strong></span>
                </div>
            </div>
            <div class="stat-trend">
                <span class="trend-indicator" id="budgetTrend">+0%</span>
            </div>
        </div>

        <div class="stat-card notifications-card">
            <div class="stat-icon">
                <i class="fas fa-bell"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-number" id="totalNotifications">0</h3>
                <p class="stat-label">Thông báo mới</p>
                <div class="stat-breakdown">
                    <span class="critical-notifications">Khẩn cấp: <strong id="criticalNotifications">0</strong></span>
                    <span class="normal-notifications">Thường: <strong id="normalNotifications">0</strong></span>
                </div>
            </div>
            <div class="stat-trend">
                <span class="trend-indicator" id="notificationsTrend">+0%</span>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="charts-grid">
        <!-- Project Progress Chart -->
        <div class="chart-widget">
            <div class="widget-header">
                <h3>Tiến độ dự án</h3>
                <div class="widget-controls">
                    <select id="projectProgressFilter" class="form-select">
                        <option value="all">Tất cả dự án</option>
                        <option value="active">Đang thực hiện</option>
                        <option value="delayed">Bị trễ</option>
                    </select>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="projectProgressChart"></canvas>
            </div>
        </div>

        <!-- Task Status Distribution -->
        <div class="chart-widget">
            <div class="widget-header">
                <h3>Phân bố trạng thái công việc</h3>
                <div class="widget-controls">
                    <button class="btn btn-sm btn-outline-primary" id="taskChartToggle">Chuyển đổi</button>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="taskStatusChart"></canvas>
            </div>
        </div>

        <!-- Budget Analysis -->
        <div class="chart-widget">
            <div class="widget-header">
                <h3>Phân tích ngân sách</h3>
                <div class="widget-controls">
                    <select id="budgetPeriod" class="form-select">
                        <option value="month">Theo tháng</option>
                        <option value="quarter">Theo quý</option>
                        <option value="year">Theo năm</option>
                    </select>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="budgetChart"></canvas>
            </div>
        </div>

        <!-- Team Performance -->
        <div class="chart-widget">
            <div class="widget-header">
                <h3>Hiệu suất nhóm</h3>
                <div class="widget-controls">
                    <select id="teamMetric" class="form-select">
                        <option value="tasks_completed">Công việc hoàn thành</option>
                        <option value="hours_worked">Giờ làm việc</option>
                        <option value="efficiency">Hiệu suất</option>
                    </select>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="teamPerformanceChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Recent Activities & Notifications -->
    <div class="activities-grid">
        <!-- Recent Activities -->
        <div class="activity-widget">
            <div class="widget-header">
                <h3>Hoạt động gần đây</h3>
                <a href="/activities" class="view-all-link">Xem tất cả</a>
            </div>
            <div class="activity-list" id="recentActivities">
                <!-- Activities will be loaded here -->
                <div class="activity-item loading">
                    <div class="activity-icon">
                        <div class="spinner"></div>
                    </div>
                    <div class="activity-content">
                        <p>Đang tải hoạt động...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Deadlines -->
        <div class="deadline-widget">
            <div class="widget-header">
                <h3>Deadline sắp tới</h3>
                <div class="widget-controls">
                    <select id="deadlineFilter" class="form-select">
                        <option value="7">7 ngày tới</option>
                        <option value="14">14 ngày tới</option>
                        <option value="30">30 ngày tới</option>
                    </select>
                </div>
            </div>
            <div class="deadline-list" id="upcomingDeadlines">
                <!-- Deadlines will be loaded here -->
                <div class="deadline-item loading">
                    <div class="deadline-date">
                        <div class="spinner"></div>
                    </div>
                    <div class="deadline-content">
                        <p>Đang tải deadline...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions-widget">
            <div class="widget-header">
                <h3>Thao tác nhanh</h3>
            </div>
            <div class="quick-actions-grid">
                <a href="/projects/create" class="quick-action-item">
                    <i class="fas fa-plus"></i>
                    <span>Tạo dự án mới</span>
                </a>
                <a href="/tasks/create" class="quick-action-item">
                    <i class="fas fa-tasks"></i>
                    <span>Thêm công việc</span>
                </a>
                <a href="/documents/upload" class="quick-action-item">
                    <i class="fas fa-upload"></i>
                    <span>Tải tài liệu</span>
                </a>
                <a href="/change-requests/create" class="quick-action-item">
                    <i class="fas fa-exchange-alt"></i>
                    <span>Yêu cầu thay đổi</span>
                </a>
                <a href="/reports" class="quick-action-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Xem báo cáo</span>
                </a>
                <a href="/settings" class="quick-action-item">
                    <i class="fas fa-cog"></i>
                    <span>Cài đặt</span>
                </a>
            </div>
        </div>
    </div>

    <!-- System Health & Performance -->
    <div class="system-health-widget">
        <div class="widget-header">
            <h3>Tình trạng hệ thống</h3>
            <div class="system-status" id="systemStatus">
                <span class="status-indicator online"></span>
                <span>Hoạt động bình thường</span>
            </div>
        </div>
        <div class="health-metrics">
            <div class="metric-item">
                <span class="metric-label">CPU Usage</span>
                <div class="metric-bar">
                    <div class="metric-fill" id="cpuUsage" style="width: 0%"></div>
                </div>
                <span class="metric-value" id="cpuValue">0%</span>
            </div>
            <div class="metric-item">
                <span class="metric-label">Memory Usage</span>
                <div class="metric-bar">
                    <div class="metric-fill" id="memoryUsage" style="width: 0%"></div>
                </div>
                <span class="metric-value" id="memoryValue">0%</span>
            </div>
            <div class="metric-item">
                <span class="metric-label">Database</span>
                <div class="metric-bar">
                    <div class="metric-fill" id="dbUsage" style="width: 0%"></div>
                </div>
                <span class="metric-value" id="dbValue">0ms</span>
            </div>
        </div>
    </div>
</div>

<!-- Dashboard Styles -->
<style>
.dashboard-container {
    padding: 20px;
    background-color: #f8f9fa;
    min-height: 100vh;
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.dashboard-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: #2c3e50;
    margin: 0;
}

.dashboard-subtitle {
    color: #7f8c8d;
    margin: 5px 0 0 0;
    font-size: 1.1rem;
}

.header-actions {
    display: flex;
    gap: 20px;
    align-items: center;
}

.date-range-picker {
    display: flex;
    gap: 10px;
    align-items: center;
}

.refresh-controls {
    display: flex;
    gap: 15px;
    align-items: center;
}

.auto-refresh-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Switch Toggle */
.switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 24px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: #3498db;
}

input:checked + .slider:before {
    transform: translateX(26px);
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 20px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
}

.projects-card .stat-icon { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.tasks-card .stat-icon { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.budget-card .stat-icon { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.notifications-card .stat-icon { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }

.stat-content {
    flex: 1;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0;
    color: #2c3e50;
}

.stat-label {
    color: #7f8c8d;
    margin: 5px 0 10px 0;
    font-size: 1rem;
}

.stat-breakdown {
    display: flex;
    flex-direction: column;
    gap: 5px;
    font-size: 0.9rem;
}

.stat-breakdown span {
    color: #95a5a6;
}

.stat-breakdown strong {
    color: #2c3e50;
}

.stat-trend {
    font-size: 1.1rem;
    font-weight: 600;
}

.trend-indicator {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.9rem;
}

.trend-indicator.positive {
    background: #d4edda;
    color: #155724;
}

.trend-indicator.negative {
    background: #f8d7da;
    color: #721c24;
}

/* Charts Grid */
.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.chart-widget {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    overflow: hidden;
}

.widget-header {
    padding: 20px;
    border-bottom: 1px solid #ecf0f1;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.widget-header h3 {
    margin: 0;
    color: #2c3e50;
    font-size: 1.3rem;
    font-weight: 600;
}

.widget-controls {
    display: flex;
    gap: 10px;
}

.chart-container {
    padding: 20px;
    height: 300px;
    position: relative;
}

/* Activities Grid */
.activities-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 300px;
    gap: 20px;
    margin-bottom: 30px;
}

@media (max-width: 1200px) {
    .activities-grid {
        grid-template-columns: 1fr;
    }
}

.activity-widget,
.deadline-widget,
.quick-actions-widget {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    overflow: hidden;
}

.view-all-link {
    color: #3498db;
    text-decoration: none;
    font-size: 0.9rem;
}

.view-all-link:hover {
    text-decoration: underline;
}

.activity-list,
.deadline-list {
    max-height: 400px;
    overflow-y: auto;
}

.activity-item,
.deadline-item {
    padding: 15px 20px;
    border-bottom: 1px solid #ecf0f1;
    display: flex;
    gap: 15px;
    align-items: center;
}

.activity-item:last-child,
.deadline-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #3498db;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 16px;
}

.activity-content {
    flex: 1;
}

.activity-content h4 {
    margin: 0 0 5px 0;
    font-size: 1rem;
    color: #2c3e50;
}

.activity-content p {
    margin: 0;
    color: #7f8c8d;
    font-size: 0.9rem;
}

.activity-time {
    color: #95a5a6;
    font-size: 0.8rem;
}

.deadline-date {
    text-align: center;
    min-width: 60px;
}

.deadline-day {
    font-size: 1.5rem;
    font-weight: 700;
    color: #e74c3c;
    margin: 0;
}

.deadline-month {
    font-size: 0.8rem;
    color: #95a5a6;
    margin: 0;
}

.deadline-content h4 {
    margin: 0 0 5px 0;
    font-size: 1rem;
    color: #2c3e50;
}

.deadline-project {
    color: #7f8c8d;
    font-size: 0.9rem;
    margin: 0;
}

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    padding: 20px;
}

.quick-action-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 20px;
    border: 2px solid #ecf0f1;
    border-radius: 10px;
    text-decoration: none;
    color: #2c3e50;
    transition: all 0.3s ease;
}

.quick-action-item:hover {
    border-color: #3498db;
    background: #f8f9fa;
    color: #3498db;
    transform: translateY(-2px);
}

.quick-action-item i {
    font-size: 24px;
}

.quick-action-item span {
    font-size: 0.9rem;
    font-weight: 500;
    text-align: center;
}

/* System Health Widget */
.system-health-widget {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    overflow: hidden;
    margin-bottom: 20px;
}

.system-status {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    color: #27ae60;
}

.status-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #27ae60;
    animation: pulse 2s infinite;
}

.status-indicator.offline {
    background: #e74c3c;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

.health-metrics {
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.metric-item {
    display: flex;
    align-items: center;
    gap: 15px;
}

.metric-label {
    min-width: 100px;
    font-size: 0.9rem;
    color: #7f8c8d;
}

.metric-bar {
    flex: 1;
    height: 8px;
    background: #ecf0f1;
    border-radius: 4px;
    overflow: hidden;
}

.metric-fill {
    height: 100%;
    background: linear-gradient(90deg, #27ae60, #2ecc71);
    transition: width 0.5s ease;
}

.metric-fill.warning {
    background: linear-gradient(90deg, #f39c12, #e67e22);
}

.metric-fill.danger {
    background: linear-gradient(90deg, #e74c3c, #c0392b);
}

.metric-value {
    min-width: 50px;
    text-align: right;
    font-size: 0.9rem;
    font-weight: 600;
    color: #2c3e50;
}

/* Loading States */
.loading {
    opacity: 0.6;
}

.spinner {
    width: 20px;
    height: 20px;
    border: 2px solid #ecf0f1;
    border-top: 2px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }
    
    .header-actions {
        flex-direction: column;
        width: 100%;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .charts-grid {
        grid-template-columns: 1fr;
    }
    
    .quick-actions-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Dashboard JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
class DashboardManager {
    constructor() {
        this.charts = {};
        this.autoRefreshInterval = null;
        this.refreshRate = 30000; // 30 seconds
        this.init();
    }

    /**
     * Initialize dashboard
     */
    init() {
        this.setupEventListeners();
        this.loadInitialData();
        this.initializeCharts();
        this.setupAutoRefresh();
        this.loadRecentActivities();
        this.loadUpcomingDeadlines();
        this.startSystemHealthMonitoring();
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Date filter
        document.getElementById('applyDateFilter').addEventListener('click', () => {
            this.applyDateFilter();
        });

        // Refresh button
        document.getElementById('refreshDashboard').addEventListener('click', () => {
            this.refreshDashboard();
        });

        // Auto refresh toggle
        document.getElementById('autoRefresh').addEventListener('change', (e) => {
            this.toggleAutoRefresh(e.target.checked);
        });

        // Chart filters
        document.getElementById('projectProgressFilter').addEventListener('change', () => {
            this.updateProjectProgressChart();
        });

        document.getElementById('taskChartToggle').addEventListener('click', () => {
            this.toggleTaskChart();
        });

        document.getElementById('budgetPeriod').addEventListener('change', () => {
            this.updateBudgetChart();
        });

        document.getElementById('teamMetric').addEventListener('change', () => {
            this.updateTeamPerformanceChart();
        });

        document.getElementById('deadlineFilter').addEventListener('change', () => {
            this.loadUpcomingDeadlines();
        });
    }

    /**
     * Load initial dashboard data
     */
    async loadInitialData() {
        try {
            const response = await fetch('/api/v1/dashboard/stats', {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json'
                }
            });

            if (response.ok) {
                const data = await response.json();
                this.updateStatsCards(data.data);
            }
        } catch (error) {
            console.error('Error loading dashboard stats:', error);
            this.showError('Không thể tải dữ liệu thống kê');
        }
    }

    /**
     * Update stats cards
     */
    updateStatsCards(stats) {
        // Projects stats
        document.getElementById('totalProjects').textContent = stats.projects.total || 0;
        document.getElementById('activeProjects').textContent = stats.projects.active || 0;
        document.getElementById('completedProjects').textContent = stats.projects.completed || 0;
        this.updateTrend('projectsTrend', stats.projects.trend || 0);

        // Tasks stats
        document.getElementById('totalTasks').textContent = stats.tasks.total || 0;
        document.getElementById('pendingTasks').textContent = stats.tasks.pending || 0;
        document.getElementById('overdueTasks').textContent = stats.tasks.overdue || 0;
        this.updateTrend('tasksTrend', stats.tasks.trend || 0);

        // Budget stats
        document.getElementById('totalBudget').textContent = this.formatCurrency(stats.budget.total || 0);
        document.getElementById('usedBudget').textContent = this.formatCurrency(stats.budget.used || 0);
        document.getElementById('remainingBudget').textContent = this.formatCurrency(stats.budget.remaining || 0);
        this.updateTrend('budgetTrend', stats.budget.trend || 0);

        // Notifications stats
        document.getElementById('totalNotifications').textContent = stats.notifications.total || 0;
        document.getElementById('criticalNotifications').textContent = stats.notifications.critical || 0;
        document.getElementById('normalNotifications').textContent = stats.notifications.normal || 0;
        this.updateTrend('notificationsTrend', stats.notifications.trend || 0);
    }

    /**
     * Update trend indicator
     */
    updateTrend(elementId, trend) {
        const element = document.getElementById(elementId);
        const isPositive = trend >= 0;
        
        element.textContent = `${isPositive ? '+' : ''}${trend}%`;
        element.className = `trend-indicator ${isPositive ? 'positive' : 'negative'}`;
    }

    /**
     * Initialize charts
     */
    initializeCharts() {
        this.initProjectProgressChart();
        this.initTaskStatusChart();
        this.initBudgetChart();
        this.initTeamPerformanceChart();
    }

    /**
     * Initialize project progress chart
     */
    initProjectProgressChart() {
        const ctx = document.getElementById('projectProgressChart').getContext('2d');
        this.charts.projectProgress = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [],
                datasets: [{
                    label: 'Tiến độ (%)',
                    data: [],
                    backgroundColor: 'rgba(52, 152, 219, 0.8)',
                    borderColor: 'rgba(52, 152, 219, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
        this.updateProjectProgressChart();
    }

    /**
     * Initialize task status chart
     */
    initTaskStatusChart() {
        const ctx = document.getElementById('taskStatusChart').getContext('2d');
        this.charts.taskStatus = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Hoàn thành', 'Đang thực hiện', 'Chờ xử lý', 'Quá hạn'],
                datasets: [{
                    data: [0, 0, 0, 0],
                    backgroundColor: [
                        '#27ae60',
                        '#3498db',
                        '#f39c12',
                        '#e74c3c'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
        this.updateTaskStatusChart();
    }

    /**
     * Initialize budget chart
     */
    initBudgetChart() {
        const ctx = document.getElementById('budgetChart').getContext('2d');
        this.charts.budget = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Ngân sách dự kiến',
                    data: [],
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Chi phí thực tế',
                    data: [],
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        this.updateBudgetChart();
    }

    /**
     * Initialize team performance chart
     */
    initTeamPerformanceChart() {
        const ctx = document.getElementById('teamPerformanceChart').getContext('2d');
        this.charts.teamPerformance = new Chart(ctx, {
            type: 'radar',
            data: {
                labels: [],
                datasets: [{
                    label: 'Hiệu suất',
                    data: [],
                    backgroundColor: 'rgba(155, 89, 182, 0.2)',
                    borderColor: 'rgba(155, 89, 182, 1)',
                    pointBackgroundColor: 'rgba(155, 89, 182, 1)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        beginAtZero: true
                    }
                }
            }
        });
        this.updateTeamPerformanceChart();
    }

    /**
     * Update project progress chart
     */
    async updateProjectProgressChart() {
        try {
            const filter = document.getElementById('projectProgressFilter').value;
            const response = await fetch(`/api/v1/dashboard/project-progress?filter=${filter}`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json'
                }
            });

            if (response.ok) {
                const data = await response.json();
                this.charts.projectProgress.data.labels = data.data.labels;
                this.charts.projectProgress.data.datasets[0].data = data.data.values;
                this.charts.projectProgress.update();
            }
        } catch (error) {
            console.error('Error updating project progress chart:', error);
        }
    }

    /**
     * Update task status chart
     */
    async updateTaskStatusChart() {
        try {
            const response = await fetch('/api/v1/dashboard/task-status', {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json'
                }
            });

            if (response.ok) {
                const data = await response.json();
                this.charts.taskStatus.data.datasets[0].data = data.data.values;
                this.charts.taskStatus.update();
            }
        } catch (error) {
            console.error('Error updating task status chart:', error);
        }
    }

    /**
     * Update budget chart
     */
    async updateBudgetChart() {
        try {
            const period = document.getElementById('budgetPeriod').value;
            const response = await fetch(`/api/v1/dashboard/budget-analysis?period=${period}`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json'
                }
            });

            if (response.ok) {
                const data = await response.json();
                this.charts.budget.data.labels = data.data.labels;
                this.charts.budget.data.datasets[0].data = data.data.planned;
                this.charts.budget.data.datasets[1].data = data.data.actual;
                this.charts.budget.update();
            }
        } catch (error) {
            console.error('Error updating budget chart:', error);
        }
    }

    /**
     * Update team performance chart
     */
    async updateTeamPerformanceChart() {
        try {
            const metric = document.getElementById('teamMetric').value;
            const response = await fetch(`/api/v1/dashboard/team-performance?metric=${metric}`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json'
                }
            });

            if (response.ok) {
                const data = await response.json();
                this.charts.teamPerformance.data.labels = data.data.labels;
                this.charts.teamPerformance.data.datasets[0].data = data.data.values;
                this.charts.teamPerformance.update();
            }
        } catch (error) {
            console.error('Error updating team performance chart:', error);
        }
    }

    /**
     * Toggle task chart type
     */
    toggleTaskChart() {
        const currentType = this.charts.taskStatus.config.type;
        const newType = currentType === 'doughnut' ? 'bar' : 'doughnut';
        
        this.charts.taskStatus.destroy();
        this.charts.taskStatus = new Chart(document.getElementById('taskStatusChart').getContext('2d'), {
            type: newType,
            data: this.charts.taskStatus.data,
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
        
        this.updateTaskStatusChart();
    }

    /**
     * Load recent activities
     */
    async loadRecentActivities() {
        try {
            const response = await fetch('/api/v1/dashboard/recent-activities', {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json'
                }
            });

            if (response.ok) {
                const data = await response.json();
                this.renderActivities(data.data);
            }
        } catch (error) {
            console.error('Error loading recent activities:', error);
            this.renderActivitiesError();
        }
    }

    /**
     * Render activities
     */
    renderActivities(activities) {
        const container = document.getElementById('recentActivities');
        
        if (activities.length === 0) {
            container.innerHTML = `
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-info"></i>
                    </div>
                    <div class="activity-content">
                        <p>Chưa có hoạt động nào</p>
                    </div>
                </div>
            `;
            return;
        }

        container.innerHTML = activities.map(activity => `
            <div class="activity-item">
                <div class="activity-icon">
                    <i class="fas ${this.getActivityIcon(activity.type)}"></i>
                </div>
                <div class="activity-content">
                    <h4>${activity.title}</h4>
                    <p>${activity.description}</p>
                </div>
                <div class="activity-time">
                    ${this.formatTimeAgo(activity.created_at)}
                </div>
            </div>
        `).join('');
    }

    /**
     * Render activities error
     */
    renderActivitiesError() {
        const container = document.getElementById('recentActivities');
        container.innerHTML = `
            <div class="activity-item">
                <div class="activity-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="activity-content">
                    <p>Không thể tải hoạt động</p>
                </div>
            </div>
        `;
    }

    /**
     * Load upcoming deadlines
     */
    async loadUpcomingDeadlines() {
        try {
            const days = document.getElementById('deadlineFilter').value;
            const response = await fetch(`/api/v1/dashboard/upcoming-deadlines?days=${days}`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json'
                }
            });

            if (response.ok) {
                const data = await response.json();
                this.renderDeadlines(data.data);
            }
        } catch (error) {
            console.error('Error loading upcoming deadlines:', error);
            this.renderDeadlinesError();
        }
    }

    /**
     * Render deadlines
     */
    renderDeadlines(deadlines) {
        const container = document.getElementById('upcomingDeadlines');
        
        if (deadlines.length === 0) {
            container.innerHTML = `
                <div class="deadline-item">
                    <div class="deadline-date">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="deadline-content">
                        <p>Không có deadline sắp tới</p>
                    </div>
                </div>
            `;
            return;
        }

        container.innerHTML = deadlines.map(deadline => {
            const date = new Date(deadline.end_date);
            return `
                <div class="deadline-item">
                    <div class="deadline-date">
                        <p class="deadline-day">${date.getDate()}</p>
                        <p class="deadline-month">${this.getMonthName(date.getMonth())}</p>
                    </div>
                    <div class="deadline-content">
                        <h4>${deadline.name}</h4>
                        <p class="deadline-project">${deadline.project_name}</p>
                    </div>
                </div>
            `;
        }).join('');
    }

    /**
     * Render deadlines error
     */
    renderDeadlinesError() {
        const container = document.getElementById('upcomingDeadlines');
        container.innerHTML = `
            <div class="deadline-item">
                <div class="deadline-date">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="deadline-content">
                    <p>Không thể tải deadline</p>
                </div>
            </div>
        `;
    }

    /**
     * Start system health monitoring
     */
    startSystemHealthMonitoring() {
        this.updateSystemHealth();
        setInterval(() => {
            this.updateSystemHealth();
        }, 10000); // Update every 10 seconds
    }

    /**
     * Update system health
     */
    async updateSystemHealth() {
        try {
            const response = await fetch('/api/v1/dashboard/system-health', {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json'
                }
            });

            if (response.ok) {
                const data = await response.json();
                this.renderSystemHealth(data.data);
            }
        } catch (error) {
            console.error('Error updating system health:', error);
            this.renderSystemHealthError();
        }
    }

    /**
     * Render system health
     */
    renderSystemHealth(health) {
        // Update status indicator
        const statusElement = document.getElementById('systemStatus');
        const isOnline = health.status === 'online';
        statusElement.innerHTML = `
            <span class="status-indicator ${isOnline ? 'online' : 'offline'}"></span>
            <span>${isOnline ? 'Hoạt động bình thường' : 'Có vấn đề'}</span>
        `;

        // Update metrics
        this.updateMetric('cpuUsage', 'cpuValue', health.cpu_usage, '%');
        this.updateMetric('memoryUsage', 'memoryValue', health.memory_usage, '%');
        this.updateMetric('dbUsage', 'dbValue', health.db_response_time, 'ms');
    }

    /**
     * Update metric
     */
    updateMetric(barId, valueId, value, unit) {
        const bar = document.getElementById(barId);
        const valueElement = document.getElementById(valueId);
        
        bar.style.width = `${Math.min(value, 100)}%`;
        valueElement.textContent = `${value}${unit}`;
        
        // Update color based on value
        bar.className = 'metric-fill';
        if (value > 80) {
            bar.classList.add('danger');
        } else if (value > 60) {
            bar.classList.add('warning');
        }
    }

    /**
     * Render system health error
     */
    renderSystemHealthError() {
        const statusElement = document.getElementById('systemStatus');
        statusElement.innerHTML = `
            <span class="status-indicator offline"></span>
            <span>Không thể kiểm tra</span>
        `;
    }

    /**
     * Setup auto refresh
     */
    setupAutoRefresh() {
        const autoRefreshCheckbox = document.getElementById('autoRefresh');
        autoRefreshCheckbox.checked = true;
        this.toggleAutoRefresh(true);
    }

    /**
     * Toggle auto refresh
     */
    toggleAutoRefresh(enabled) {
        if (enabled) {
            this.autoRefreshInterval = setInterval(() => {
                this.refreshDashboard();
            }, this.refreshRate);
        } else {
            if (this.autoRefreshInterval) {
                clearInterval(this.autoRefreshInterval);
                this.autoRefreshInterval = null;
            }
        }
    }

    /**
     * Apply date filter
     */
    applyDateFilter() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        
        if (!startDate || !endDate) {
            this.showError('Vui lòng chọn khoảng thời gian');
            return;
        }
        
        if (new Date(startDate) > new Date(endDate)) {
            this.showError('Ngày bắt đầu không thể lớn hơn ngày kết thúc');
            return;
        }
        
        // Store filter dates and refresh
        this.filterStartDate = startDate;
        this.filterEndDate = endDate;
        this.refreshDashboard();
    }

    /**
     * Refresh dashboard
     */
    async refreshDashboard() {
        const refreshButton = document.getElementById('refreshDashboard');
        const icon = refreshButton.querySelector('i');
        
        // Show loading state
        icon.classList.add('fa-spin');
        refreshButton.disabled = true;
        
        try {
            await Promise.all([
                this.loadInitialData(),
                this.updateProjectProgressChart(),
                this.updateTaskStatusChart(),
                this.updateBudgetChart(),
                this.updateTeamPerformanceChart(),
                this.loadRecentActivities(),
                this.loadUpcomingDeadlines()
            ]);
            
            this.showSuccess('Dashboard đã được cập nhật');
        } catch (error) {
            console.error('Error refreshing dashboard:', error);
            this.showError('Không thể làm mới dashboard');
        } finally {
            // Remove loading state
            icon.classList.remove('fa-spin');
            refreshButton.disabled = false;
        }
    }

    /**
     * Get activity icon
     */
    getActivityIcon(type) {
        const icons = {
            'project_created': 'fa-plus',
            'task_completed': 'fa-check',
            'document_uploaded': 'fa-upload',
            'change_request': 'fa-exchange-alt',
            'comment_added': 'fa-comment',
            'user_assigned': 'fa-user-plus',
            'deadline_approaching': 'fa-clock',
            'budget_exceeded': 'fa-exclamation-triangle'
        };
        return icons[type] || 'fa-info';
    }

    /**
     * Format time ago
     */
    formatTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);
        
        if (diffInSeconds < 60) {
            return 'Vừa xong';
        } else if (diffInSeconds < 3600) {
            const minutes = Math.floor(diffInSeconds / 60);
            return `${minutes} phút trước`;
        } else if (diffInSeconds < 86400) {
            const hours = Math.floor(diffInSeconds / 3600);
            return `${hours} giờ trước`;
        } else {
            const days = Math.floor(diffInSeconds / 86400);
            return `${days} ngày trước`;
        }
    }

    /**
     * Get month name
     */
    getMonthName(monthIndex) {
        const months = [
            'Th1', 'Th2', 'Th3', 'Th4', 'Th5', 'Th6',
            'Th7', 'Th8', 'Th9', 'Th10', 'Th11', 'Th12'
        ];
        return months[monthIndex];
    }

    /**
     * Format currency
     */
    formatCurrency(amount) {
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND'
        }).format(amount);
    }

    /**
     * Show success message
     */
    showSuccess(message) {
        // Implementation depends on your notification system
        console.log('Success:', message);
    }

    /**
     * Show error message
     */
    showError(message) {
        // Implementation depends on your notification system
        console.error('Error:', message);
    }
}

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new DashboardManager();
});
</script>
@endsection