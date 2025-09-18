<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'ZenaManage')</title>
    <style>
        /* Reset styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #374151;
            background-color: #f9fafb;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 32px 24px;
            text-align: center;
        }
        
        .logo {
            width: 60px;
            height: 60px;
            background-color: #ffffff;
            border-radius: 12px;
            margin: 0 auto 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
        
        .email-title {
            color: #ffffff;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .email-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
        }
        
        .email-content {
            padding: 32px 24px;
        }
        
        .email-footer {
            background-color: #f8fafc;
            padding: 24px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        
        .footer-text {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 16px;
        }
        
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 24px;
            margin-bottom: 16px;
        }
        
        .footer-link {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }
        
        .footer-link:hover {
            text-decoration: underline;
        }
        
        .company-info {
            color: #9ca3af;
            font-size: 12px;
        }
        
        /* Button styles */
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #667eea;
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            text-align: center;
            transition: background-color 0.2s;
        }
        
        .btn:hover {
            background-color: #5a67d8;
        }
        
        .btn-secondary {
            background-color: #6b7280;
        }
        
        .btn-secondary:hover {
            background-color: #4b5563;
        }
        
        /* Content styles */
        .content-section {
            margin-bottom: 24px;
        }
        
        .content-title {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 12px;
        }
        
        .content-text {
            color: #4b5563;
            margin-bottom: 16px;
        }
        
        .highlight-box {
            background-color: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 6px;
            padding: 16px;
            margin: 16px 0;
        }
        
        .highlight-title {
            font-weight: 600;
            color: #0c4a6e;
            margin-bottom: 8px;
        }
        
        .highlight-text {
            color: #075985;
            font-size: 14px;
        }
        
        /* Responsive */
        @media (max-width: 600px) {
            .email-container {
                margin: 0;
                border-radius: 0;
            }
            
            .email-header,
            .email-content,
            .email-footer {
                padding: 24px 16px;
            }
            
            .footer-links {
                flex-direction: column;
                gap: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <div class="logo">ZM</div>
            <h1 class="email-title">@yield('title', 'ZenaManage')</h1>
            <p class="email-subtitle">@yield('subtitle', 'Project Management Platform')</p>
        </div>
        
        <!-- Content -->
        <div class="email-content">
            @yield('content')
        </div>
        
        <!-- Footer -->
        <div class="email-footer">
            <p class="footer-text">
                This email was sent from ZenaManage Project Management Platform
            </p>
            
            <div class="footer-links">
                <a href="{{ config('app.url') }}" class="footer-link">Visit Website</a>
                <a href="{{ config('app.url') }}/support" class="footer-link">Support</a>
                <a href="{{ config('app.url') }}/privacy" class="footer-link">Privacy Policy</a>
            </div>
            
            <div class="company-info">
                <p>© {{ date('Y') }} ZenaManage. All rights reserved.</p>
                <p>If you have any questions, please contact our support team.</p>
            </div>
        </div>
    </div>
</body>
</html>
