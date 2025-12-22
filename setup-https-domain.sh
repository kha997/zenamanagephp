#!/bin/bash

# Setup HTTPS cho manager.zena.com.vn với mkcert
# Yêu cầu: Chạy với sudo

DOMAIN="manager.zena.com.vn"
SSL_DIR="/Applications/XAMPP/xamppfiles/etc/ssl"
PROJECT_ROOT="/Applications/XAMPP/xamppfiles/htdocs/zenamanage"

echo "🔒 Setup HTTPS cho ${DOMAIN}"
echo "===================================="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "⚠️  Script cần quyền sudo"
    echo ""
    echo "📝 Chạy lại với sudo:"
    echo "   sudo bash setup-https-domain.sh"
    exit 1
fi

# Step 1: Install mkcert CA (if not already installed)
echo "📝 Step 1: Installing mkcert CA..."
if [ -f "$HOME/.local/share/mkcert/rootCA.pem" ]; then
    echo "   ✓ mkcert CA already installed"
else
    echo "   ⚠️  Run manually: mkcert -install"
    echo "      (This needs to be run without sudo first)"
fi

# Step 2: Create SSL directory if not exists
echo ""
echo "📝 Step 2: Creating SSL directory..."
mkdir -p "${SSL_DIR}"
echo "   ✓ SSL directory ready: ${SSL_DIR}"

# Step 3: Generate certificate
echo ""
echo "📝 Step 3: Generating certificate for ${DOMAIN}..."
cd "${SSL_DIR}"
if [ -f "${DOMAIN}.pem" ] && [ -f "${DOMAIN}-key.pem" ]; then
    echo "   ✓ Certificate already exists"
    ls -lh "${DOMAIN}"*
else
    # Generate certificate
    mkcert "${DOMAIN}" "www.${DOMAIN}"
    if [ $? -eq 0 ]; then
        # Rename files to expected format
        if [ -f "${DOMAIN}.pem" ]; then
            echo "   ✓ Certificate files created"
        else
            # Files might be named differently by mkcert
            mv "${DOMAIN}+2.pem" "${DOMAIN}.pem" 2>/dev/null || true
            mv "${DOMAIN}+2-key.pem" "${DOMAIN}-key.pem" 2>/dev/null || true
        fi
        ls -lh "${DOMAIN}"*
    else
        echo "   ✗ Failed to generate certificate"
        exit 1
    fi
fi

# Step 4: Add SSL virtual host to httpd-vhosts.conf
echo ""
echo "📝 Step 4: Configuring Apache SSL virtual host..."

VHOST_FILE="/Applications/XAMPP/xamppfiles/etc/extra/httpd-vhosts.conf"

# Check if SSL vhost already exists
if grep -q "ServerName ${DOMAIN}" "${VHOST_FILE}" && grep -q "<VirtualHost \*:443>" "${VHOST_FILE}"; then
    echo "   ✓ SSL virtual host already configured"
else
    # Add SSL virtual host
    cat >> "${VHOST_FILE}" << 'SSL_VHOST_EOF'

# SSL Virtual Host cho manager.zena.com.vn
<VirtualHost *:443>
    ServerAdmin admin@manager.zena.com.vn
    DocumentRoot "/Applications/XAMPP/xamppfiles/htdocs/zenamanage/public"
    ServerName manager.zena.com.vn
    ServerAlias www.manager.zena.com.vn
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile "/Applications/XAMPP/xamppfiles/etc/ssl/manager.zena.com.vn.pem"
    SSLCertificateKeyFile "/Applications/XAMPP/xamppfiles/etc/ssl/manager.zena.com.vn-key.pem"
    
    # Cấu hình thư mục
    <Directory "/Applications/XAMPP/xamppfiles/htdocs/zenamanage/public">
        Options Indexes FollowSymLinks MultiViews
        AllowOverride All
        Require all granted
        
        # Kích hoạt mod_rewrite cho Laravel
        RewriteEngine On
        
        # Laravel URL rewriting
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^ index.php [L]
        
        # Force HTTPS (optional)
        # RewriteCond %{HTTPS} off
        # RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
    </Directory>
    
    # Log files
    ErrorLog "/Applications/XAMPP/xamppfiles/logs/manager-zena-ssl-error.log"
    CustomLog "/Applications/XAMPP/xamppfiles/logs/manager-zena-ssl-access.log" common
</VirtualHost>

# Redirect HTTP to HTTPS for manager.zena.com.vn
<VirtualHost *:80>
    ServerName manager.zena.com.vn
    ServerAlias www.manager.zena.com.vn
    
    Redirect permanent / https://manager.zena.com.vn/
</VirtualHost>
SSL_VHOST_EOF
    echo "   ✓ Added SSL virtual host configuration"
fi

# Step 5: Verify mod_ssl is enabled
echo ""
echo "📝 Step 5: Checking mod_ssl..."
if grep -q "LoadModule ssl_module" /Applications/XAMPP/xamppfiles/etc/httpd.conf; then
    echo "   ✓ mod_ssl is enabled"
else
    echo "   ⚠️  mod_ssl is not enabled"
    echo "      Please uncomment this line in httpd.conf:"
    echo "      LoadModule ssl_module modules/mod_ssl.so"
fi

# Step 6: Verify httpd-ssl.conf is included
echo ""
echo "📝 Step 6: Checking SSL configuration..."
if grep -q "Include.*httpd-ssl.conf" /Applications/XAMPP/xamppfiles/etc/httpd.conf; then
    echo "   ✓ httpd-ssl.conf is included"
else
    echo "   ⚠️  httpd-ssl.conf is not included"
    echo "      Please uncomment this line in httpd.conf:"
    echo "      Include etc/extra/httpd-ssl.conf"
fi

echo ""
echo "✅ SSL Configuration Complete!"
echo ""
echo "📋 Next steps:"
echo "   1. Make sure mod_ssl is enabled in httpd.conf"
echo "   2. Make sure httpd-ssl.conf is included in httpd.conf"
echo "   3. Update .env file:"
echo "      APP_URL=https://manager.zena.com.vn"
echo ""
echo "   4. Clear Laravel cache:"
echo "      cd ${PROJECT_ROOT}"
echo "      php artisan config:clear"
echo ""
echo "   5. Restart Apache from XAMPP Control Panel"
echo ""
echo "   6. Access: https://manager.zena.com.vn"
echo ""

