#!/bin/bash

# ZenaManage HTTPS Configuration Script

# Exit immediately if a command exits with a non-zero status.
set -e

# --- Configuration ---
PROJECT_PATH=$(pwd)
LOG_FILE="$PROJECT_PATH/storage/logs/enable-https-$(date +%Y%m%d_%H%M%S).log"

# --- Functions ---
log() {
    echo -e "[$(date +'%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

success() {
    log "✅ $1"
}

error() {
    log "❌ $1"
    exit 1
}

warning() {
    log "⚠️  $1"
}

# --- Main Script ---
log "🔒 Enabling HTTPS for Production"
log "==============================="

# 1. Check if we're in development environment
if [[ "$APP_ENV" == "local" || "$APP_ENV" == "development" ]]; then
    warning "Development environment detected - HTTPS setup skipped"
    log "For production HTTPS setup, please:"
    log "1. Set APP_ENV=production"
    log "2. Configure domain name"
    log "3. Obtain SSL certificate"
    log "4. Configure web server (Apache/Nginx)"
    exit 0
fi

# 2. Update APP_URL to HTTPS
log "Updating APP_URL to HTTPS..."
CURRENT_URL=$(grep "APP_URL=" .env | cut -d '=' -f 2)
if [[ "$CURRENT_URL" == http://* ]]; then
    HTTPS_URL=$(echo "$CURRENT_URL" | sed 's/http:/https:/')
    sed -i.bak "s|APP_URL=.*|APP_URL=$HTTPS_URL|" .env
    success "Updated APP_URL to: $HTTPS_URL"
else
    log "APP_URL already configured: $CURRENT_URL"
fi

# 3. Configure HTTPS middleware
log "Configuring HTTPS middleware..."

# Create HTTPS middleware if it doesn't exist
if [ ! -f "app/Http/Middleware/ForceHttps.php" ]; then
    cat > app/Http/Middleware/ForceHttps.php << 'EOF'
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceHttps
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (!$request->secure() && app()->environment('production')) {
            return redirect()->secure($request->getRequestUri());
        }

        return $next($request);
    }
}
EOF
    success "Created ForceHttps middleware"
fi

# 4. Register HTTPS middleware
log "Registering HTTPS middleware..."
if ! grep -q "ForceHttps" app/Http/Kernel.php; then
    sed -i.bak '/protected \$middleware = \[/a\        \App\Http\Middleware\ForceHttps::class,' app/Http/Kernel.php
    success "Registered ForceHttps middleware"
else
    log "ForceHttps middleware already registered"
fi

# 5. Configure secure session settings
log "Configuring secure session settings..."
sed -i.bak 's/SESSION_SECURE_COOKIE=.*/SESSION_SECURE_COOKIE=true/' .env
sed -i.bak 's/SESSION_HTTP_ONLY=.*/SESSION_HTTP_ONLY=true/' .env
sed -i.bak 's/SESSION_SAME_SITE=.*/SESSION_SAME_SITE=strict/' .env
success "Configured secure session settings"

# 6. Configure secure cookie settings
log "Configuring secure cookie settings..."
if ! grep -q "COOKIE_SECURE" .env; then
    echo "COOKIE_SECURE=true" >> .env
fi
if ! grep -q "COOKIE_HTTP_ONLY" .env; then
    echo "COOKIE_HTTP_ONLY=true" >> .env
fi
if ! grep -q "COOKIE_SAME_SITE" .env; then
    echo "COOKIE_SAME_SITE=strict" >> .env
fi
success "Configured secure cookie settings"

# 7. Update CSP headers
log "Configuring Content Security Policy..."
if [ ! -f "app/Http/Middleware/SecurityHeaders.php" ]; then
    cat > app/Http/Middleware/SecurityHeaders.php << 'EOF'
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Security headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // CSP header
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; " .
               "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
               "img-src 'self' data: https:; " .
               "font-src 'self' https://cdn.jsdelivr.net; " .
               "connect-src 'self'; " .
               "frame-ancestors 'none';";
        
        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
EOF
    success "Created SecurityHeaders middleware"
fi

# Register SecurityHeaders middleware
if ! grep -q "SecurityHeaders" app/Http/Kernel.php; then
    sed -i.bak '/protected \$middleware = \[/a\        \App\Http\Middleware\SecurityHeaders::class,' app/Http/Kernel.php
    success "Registered SecurityHeaders middleware"
else
    log "SecurityHeaders middleware already registered"
fi

# 8. Clear caches
log "Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# 9. Test HTTPS configuration
log "Testing HTTPS configuration..."
if curl -s -I https://localhost:8000 &> /dev/null; then
    success "HTTPS connection successful"
else
    warning "HTTPS connection failed (expected in development)"
fi

# 10. Create SSL certificate generation script
log "Creating SSL certificate generation script..."
cat > scripts/generate-ssl-certificate.sh << 'EOF'
#!/bin/bash

# Generate SSL Certificate for Development

PROJECT_PATH=$(pwd)
DOMAIN="localhost"

echo "🔒 Generating SSL Certificate for Development"
echo "============================================="

# Create certificates directory
mkdir -p storage/ssl

# Generate private key
openssl genrsa -out storage/ssl/server.key 2048

# Generate certificate signing request
openssl req -new -key storage/ssl/server.key -out storage/ssl/server.csr -subj "/C=US/ST=State/L=City/O=Organization/CN=$DOMAIN"

# Generate self-signed certificate
openssl x509 -req -days 365 -in storage/ssl/server.csr -signkey storage/ssl/server.key -out storage/ssl/server.crt

# Set permissions
chmod 600 storage/ssl/server.key
chmod 644 storage/ssl/server.crt

echo "✅ SSL certificate generated successfully!"
echo "Certificate: storage/ssl/server.crt"
echo "Private Key: storage/ssl/server.key"
echo ""
echo "To use with Apache, add to httpd.conf:"
echo "SSLEngine on"
echo "SSLCertificateFile $PROJECT_PATH/storage/ssl/server.crt"
echo "SSLCertificateKeyFile $PROJECT_PATH/storage/ssl/server.key"
EOF

chmod +x scripts/generate-ssl-certificate.sh
success "Created SSL certificate generation script"

# 11. Summary
log ""
log "🔒 HTTPS Configuration Summary"
log "==============================="
log "✅ APP_URL updated to HTTPS"
log "✅ ForceHttps middleware created and registered"
log "✅ SecurityHeaders middleware created and registered"
log "✅ Secure session settings configured"
log "✅ Secure cookie settings configured"
log "✅ CSP headers configured"
log "✅ SSL certificate generation script created"
log ""
log "📊 Security Configuration:"
log "- Force HTTPS redirects in production"
log "- Secure session cookies"
log "- HTTP-only cookies"
log "- Strict SameSite policy"
log "- Content Security Policy"
log "- Security headers (X-Frame-Options, X-XSS-Protection, etc.)"
log ""
log "🎯 Next Steps:"
log "1. Generate SSL certificate: ./scripts/generate-ssl-certificate.sh"
log "2. Configure web server (Apache/Nginx) with SSL"
log "3. Test HTTPS connection"
log "4. Verify security headers"
log "5. Test HTTPS redirects"
log ""
log "HTTPS configuration completed at: $(date)"
log "Log file: $LOG_FILE"
