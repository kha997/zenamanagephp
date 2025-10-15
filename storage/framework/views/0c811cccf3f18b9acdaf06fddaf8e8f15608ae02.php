
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSS Test - ZenaManage</title>
    <style>
        /* Reset và Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        /* Header Styles */
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            position: sticky;
            top: 0;
            z-index: 50;
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 80px;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .logo-icon {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            padding: 12px;
            border-radius: 12px;
            color: white;
            font-size: 24px;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .logo-text h1 {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .logo-text p {
            color: #666;
            font-size: 14px;
            margin-top: 4px;
        }
        
        /* Status Badge */
        .status-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #10b981;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        /* Main Content */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        /* KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .kpi-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            animation: fadeInUp 0.6s ease-out;
        }
        
        .kpi-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.15);
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .kpi-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        
        .kpi-title {
            font-size: 14px;
            font-weight: 500;
            color: #666;
            margin-bottom: 4px;
        }
        
        .kpi-value {
            font-size: 36px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 8px;
        }
        
        .kpi-change {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .kpi-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        
        /* Color Variants */
        .kpi-blue { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .kpi-green { background: linear-gradient(135deg, #10b981, #059669); }
        .kpi-purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .kpi-orange { background: linear-gradient(135deg, #f59e0b, #d97706); }
        
        .change-positive { color: #10b981; }
        .change-negative { color: #ef4444; }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.3);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.9);
            color: #374151;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 1);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 16px;
            margin-top: 32px;
            flex-wrap: wrap;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                padding: 0 1rem;
                height: 60px;
            }
            
            .logo-text h1 {
                font-size: 20px;
            }
            
            .main-content {
                padding: 1rem;
            }
            
            .kpi-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
        
        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-rocket"></i>
                </div>
                <div class="logo-text">
                    <h1>CSS Test Page</h1>
                    <p>Testing inline CSS functionality</p>
                </div>
            </div>
            <div class="status-badge">
                <div class="status-dot"></div>
                <span>CSS Working</span>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <!-- KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-header">
                    <div>
                        <div class="kpi-title">Total Users</div>
                        <div class="kpi-value">1,247</div>
                        <div class="kpi-change change-positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>+12%</span>
                            <span style="color: #999; margin-left: 8px;">from last month</span>
                        </div>
                    </div>
                    <div class="kpi-icon kpi-blue">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-header">
                    <div>
                        <div class="kpi-title">Active Projects</div>
                        <div class="kpi-value">89</div>
                        <div class="kpi-change change-positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>+5%</span>
                            <span style="color: #999; margin-left: 8px;">from last month</span>
                        </div>
                    </div>
                    <div class="kpi-icon kpi-green">
                        <i class="fas fa-project-diagram"></i>
                    </div>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-header">
                    <div>
                        <div class="kpi-title">System Health</div>
                        <div class="kpi-value">99.8%</div>
                        <div class="kpi-change change-positive">
                            <i class="fas fa-heartbeat"></i>
                            <span>All systems operational</span>
                        </div>
                    </div>
                    <div class="kpi-icon kpi-purple">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-header">
                    <div>
                        <div class="kpi-title">Storage Usage</div>
                        <div class="kpi-value">67%</div>
                        <div class="kpi-change change-positive">
                            <i class="fas fa-database"></i>
                            <span>2.1TB of 3.2TB used</span>
                        </div>
                    </div>
                    <div class="kpi-icon kpi-orange">
                        <i class="fas fa-database"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <button class="btn btn-primary">
                <i class="fas fa-plus"></i>
                Create New Project
            </button>
            <button class="btn btn-secondary">
                <i class="fas fa-download"></i>
                Export Data
            </button>
            <button class="btn btn-secondary">
                <i class="fas fa-cog"></i>
                Settings
            </button>
            <button class="btn btn-secondary">
                <i class="fas fa-chart-bar"></i>
                View Analytics
            </button>
        </div>

        <!-- Test Results -->
        <div style="margin-top: 32px; padding: 24px; background: rgba(255, 255, 255, 0.9); border-radius: 16px; backdrop-filter: blur(20px);">
            <h2 style="font-size: 24px; font-weight: 700; margin-bottom: 16px; color: #1f2937;">CSS Test Results</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <div style="padding: 16px; background: #10b981; color: white; border-radius: 12px; text-align: center;">
                    <i class="fas fa-check-circle" style="font-size: 24px; margin-bottom: 8px;"></i>
                    <div style="font-weight: 600;">Gradients</div>
                    <div style="font-size: 14px; opacity: 0.9;">Working</div>
                </div>
                <div style="padding: 16px; background: #3b82f6; color: white; border-radius: 12px; text-align: center;">
                    <i class="fas fa-check-circle" style="font-size: 24px; margin-bottom: 8px;"></i>
                    <div style="font-weight: 600;">Animations</div>
                    <div style="font-size: 14px; opacity: 0.9;">Working</div>
                </div>
                <div style="padding: 16px; background: #8b5cf6; color: white; border-radius: 12px; text-align: center;">
                    <i class="fas fa-check-circle" style="font-size: 24px; margin-bottom: 8px;"></i>
                    <div style="font-weight: 600;">Backdrop Blur</div>
                    <div style="font-size: 14px; opacity: 0.9;">Working</div>
                </div>
                <div style="padding: 16px; background: #f59e0b; color: white; border-radius: 12px; text-align: center;">
                    <i class="fas fa-check-circle" style="font-size: 24px; margin-bottom: 8px;"></i>
                    <div style="font-weight: 600;">Responsive</div>
                    <div style="font-size: 14px; opacity: 0.9;">Working</div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/test-css-inline.blade.php ENDPATH**/ ?>