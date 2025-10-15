<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZENA Demo - Login & Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .demo-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 500px;
        }

        .demo-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .demo-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .demo-logo i {
            font-size: 2rem;
            color: #667eea;
        }

        .demo-logo h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
        }

        .demo-subtitle {
            color: #64748b;
            font-size: 0.875rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
            font-size: 0.875rem;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.2s;
            background: white;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            width: 100%;
            padding: 12px 16px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-1px);
        }

        .btn-primary:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }

        .demo-accounts {
            margin-top: 30px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .demo-accounts h4 {
            color: #374151;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .demo-account {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .demo-account:hover {
            background: #f1f5f9;
            margin: 0 -10px;
            padding: 8px 10px;
            border-radius: 4px;
        }

        .demo-account:last-child {
            border-bottom: none;
        }

        .demo-role {
            color: #667eea;
            font-weight: 500;
        }

        .demo-password {
            color: #64748b;
            font-family: monospace;
        }

        .status {
            margin-top: 20px;
            padding: 15px;
            border-radius: 8px;
            font-size: 0.875rem;
            display: none;
        }

        .status.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .status.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .status.show {
            display: block;
        }
    </style>
</head>
<body>
    <div class="demo-container">
        <div class="demo-header">
            <div class="demo-logo">
                <i class="fas fa-rocket"></i>
                <h1>ZENA</h1>
            </div>
            <p class="demo-subtitle">Project Management System - Demo</p>
        </div>

        <form id="demo-form">
            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="form-input" 
                    placeholder="Nhập email của bạn"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Mật khẩu</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-input" 
                    placeholder="Nhập mật khẩu"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary" id="login-btn">
                <i class="fas fa-sign-in-alt"></i>
                <span>Đăng nhập</span>
            </button>

            <div id="status" class="status"></div>
        </form>

        <div class="demo-accounts">
            <h4>Demo Accounts (Password: zena1234)</h4>
            <div class="demo-account" onclick="fillAccount('superadmin@zena.com')">
                <span class="demo-role">Super Admin</span>
                <span class="demo-password">superadmin@zena.com</span>
            </div>
            <div class="demo-account" onclick="fillAccount('pm@zena.com')">
                <span class="demo-role">Project Manager</span>
                <span class="demo-password">pm@zena.com</span>
            </div>
            <div class="demo-account" onclick="fillAccount('designer@zena.com')">
                <span class="demo-role">Designer</span>
                <span class="demo-password">designer@zena.com</span>
            </div>
            <div class="demo-account" onclick="fillAccount('client@zena.com')">
                <span class="demo-role">Client</span>
                <span class="demo-password">client@zena.com</span>
            </div>
        </div>
    </div>

    <script>
        function fillAccount(email) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = 'zena1234';
        }

        document.getElementById('demo-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const status = document.getElementById('status');
            const loginBtn = document.getElementById('login-btn');
            
            // Show loading
            loginBtn.disabled = true;
            loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang đăng nhập...';
            
            // Simulate login process
            setTimeout(() => {
                if (password === 'zena1234') {
                    status.className = 'status success show';
                    status.innerHTML = '<i class="fas fa-check-circle"></i> Đăng nhập thành công! Đang chuyển hướng...';
                    
                    // Redirect to dashboard after 1 second
                    setTimeout(() => {
                        window.location.href = '/dashboard';
                    }, 1000);
                } else {
                    status.className = 'status error show';
                    status.innerHTML = '<i class="fas fa-exclamation-circle"></i> Mật khẩu không đúng. Vui lòng sử dụng "zena1234"';
                    
                    loginBtn.disabled = false;
                    loginBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Đăng nhập';
                }
            }, 1000);
        });
    </script>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/demo.blade.php ENDPATH**/ ?>