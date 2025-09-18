<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - ZENA Project Management</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
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

        .login-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .login-logo i {
            font-size: 2rem;
            color: #667eea;
        }

        .login-logo h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
        }

        .login-subtitle {
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

        /* Password Input Container */
        .password-input-container {
            position: relative;
        }

        .password-input {
            padding-right: 45px;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 5px;
            border-radius: 4px;
            transition: color 0.2s;
        }

        .password-toggle:hover {
            color: #667eea;
        }

        /* Form Options */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            font-size: 0.875rem;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: #374151;
        }

        .remember-me input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #667eea;
        }

        .forgot-password {
            color: #667eea;
            text-decoration: none;
            transition: color 0.2s;
        }

        .forgot-password:hover {
            color: #5a67d8;
            text-decoration: underline;
        }

        .form-input.error {
            border-color: #ef4444;
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

        .error-message {
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 5px;
        }

        .success-message {
            color: #10b981;
            font-size: 0.875rem;
            margin-top: 5px;
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

        .loading {
            display: none;
        }

        .loading.show {
            display: inline-block;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-logo">
                <i class="fas fa-rocket"></i>
                <h1>ZENA</h1>
            </div>
            <p class="login-subtitle">Project Management System</p>
        </div>

        <form id="login-form" method="GET" action="/test-login/superadmin@zena.com">
            
            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="form-input" 
                    placeholder="Nhập email của bạn"
                    required
                    autocomplete="email"
                >
                <div id="email-error" class="error-message" style="display: none;"></div>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Mật khẩu</label>
                <div class="password-input-container">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-input password-input" 
                        placeholder="Nhập mật khẩu"
                        required
                        autocomplete="current-password"
                    >
                    <button type="button" class="password-toggle" id="password-toggle">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div id="password-error" class="error-message" style="display: none;"></div>
            </div>

            <div class="form-options">
                <label class="remember-me">
                    <input type="checkbox" id="remember-me">
                    <span>Ghi nhớ đăng nhập</span>
                </label>
                <a href="#" class="forgot-password" id="forgot-password-link">Quên mật khẩu?</a>
            </div>

            <button type="submit" class="btn btn-primary" id="login-btn">
                <i class="fas fa-sign-in-alt"></i>
                <span>Đăng nhập</span>
                <i class="fas fa-spinner fa-spin loading" id="loading-icon"></i>
            </button>

            <div id="form-error" class="error-message" style="display: none;"></div>
            <div id="form-success" class="success-message" style="display: none;"></div>
        </form>

        <div class="demo-accounts">
            <h4>Demo Accounts (Password: zena1234)</h4>
            <div class="demo-account">
                <span class="demo-role">Super Admin</span>
                <span class="demo-password">superadmin@zena.com</span>
            </div>
            <div class="demo-account">
                <span class="demo-role">Project Manager</span>
                <span class="demo-password">pm@zena.com</span>
            </div>
            <div class="demo-account">
                <span class="demo-role">Designer</span>
                <span class="demo-password">designer@zena.com</span>
            </div>
            <div class="demo-account">
                <span class="demo-role">Client</span>
                <span class="demo-password">client@zena.com</span>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('login-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const form = this;
            const email = form.email.value;
            const password = form.password.value;
            const loginBtn = document.getElementById('login-btn');
            const loadingIcon = document.getElementById('loading-icon');
            
            // Clear previous errors
            clearErrors();
            
            // Show loading
            loginBtn.disabled = true;
            loadingIcon.classList.add('show');
            
            // Check password
            if (password !== 'zena1234') {
                document.getElementById('form-error').textContent = 'Mật khẩu không đúng. Vui lòng sử dụng: zena1234';
                document.getElementById('form-error').style.display = 'block';
                loginBtn.disabled = false;
                loadingIcon.classList.remove('show');
                return;
            }
            
            // Redirect to test-login with email
            window.location.href = `/test-login/${encodeURIComponent(email)}`;
        });
        
        function clearErrors() {
            document.getElementById('email-error').style.display = 'none';
            document.getElementById('password-error').style.display = 'none';
            document.getElementById('form-error').style.display = 'none';
            document.getElementById('form-success').style.display = 'none';
        }
        
        // Auto-fill demo account on click
        document.querySelectorAll('.demo-account').forEach(account => {
            account.addEventListener('click', function() {
                const email = this.querySelector('.demo-password').textContent;
                document.getElementById('email').value = email;
                document.getElementById('password').value = 'zena1234';
            });
        });

        // Password toggle functionality
        const passwordToggle = document.getElementById('password-toggle');
        const passwordInput = document.getElementById('password');
        
        if (passwordToggle && passwordInput) {
            passwordToggle.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                const icon = this.querySelector('i');
                icon.className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
            });
        }

        // Forgot password functionality
        const forgotPasswordLink = document.getElementById('forgot-password-link');
        if (forgotPasswordLink) {
            forgotPasswordLink.addEventListener('click', function(e) {
                e.preventDefault();
                
                const email = document.getElementById('email').value;
                if (!email) {
                    alert('Vui lòng nhập email trước khi reset mật khẩu');
                    return;
                }
                
                // Simple forgot password simulation
                const confirmed = confirm(`Gửi link reset mật khẩu đến ${email}?`);
                if (confirmed) {
                    alert('Link reset mật khẩu đã được gửi đến email của bạn!\n\n(Demo: Password mặc định là "zena1234")');
                }
            });
        }
    </script>
</body>
</html><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/auth/login.blade.php ENDPATH**/ ?>