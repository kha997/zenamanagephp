#!/bin/bash

# Quick HTTPS Setup Script
# Chạy các lệnh này từng bước

set -e

DOMAIN="manager.zena.com.vn"
SSL_DIR="/Applications/XAMPP/xamppfiles/etc/ssl"
PROJECT_ROOT="/Applications/XAMPP/xamppfiles/htdocs/zenamanage"

echo "🔒 HTTPS Setup cho ${DOMAIN}"
echo "================================"
echo ""

# Step 1: Install CA
echo "📝 Step 1: Install mkcert CA"
echo "   Chạy: mkcert -install"
echo ""
read -p "   Bạn đã chạy 'mkcert -install' chưa? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "   ⚠️  Vui lòng chạy: mkcert -install"
    exit 1
fi

# Step 2: Create certificate
echo ""
echo "📝 Step 2: Creating certificate"
mkdir -p "${SSL_DIR}"
cd "${SSL_DIR}"

echo "   📍 Generating certificate..."
mkcert "${DOMAIN}" "www.${DOMAIN}" "localhost"

# Rename files if needed
if [ -f "${DOMAIN}+2.pem" ]; then
    echo "   📝 Renaming certificate files..."
    mv "${DOMAIN}+2.pem" "${DOMAIN}.pem"
    mv "${DOMAIN}+2-key.pem" "${DOMAIN}-key.pem"
fi

if [ -f "${DOMAIN}+3.pem" ]; then
    mv "${DOMAIN}+3.pem" "${DOMAIN}.pem"
    mv "${DOMAIN}+3-key.pem" "${DOMAIN}-key.pem"
fi

echo "   ✅ Certificate created:"
ls -lh "${DOMAIN}"* | grep -E "(pem|key)"

# Step 3: Update Apache config
echo ""
echo "📝 Step 3: Apache configuration"
echo "   ⚠️  Vui lòng tự thêm SSL virtual host vào httpd-vhosts.conf"
echo "   Xem file: HTTPS_SETUP_GUIDE.md để biết chi tiết"
echo ""
echo "   File cần sửa: /Applications/XAMPP/xamppfiles/etc/extra/httpd-vhosts.conf"
echo "   Files: /Applications/XAMPP/xamppfiles/etc/httpd.conf (enable mod_ssl)"

# Step 4: Update .env
echo ""
echo "📝 Step 4: Update .env file"

if [ -f "${PROJECT_ROOT}/.env" ]; then
    cd "${PROJECT_ROOT}"
    
    # Backup
    cp .env .env.backup.$(date +%Y%m%d_%H%M%S)
    echo "   ✅ Backup created"
    
    # Update APP_URL
    if grep -q "APP_URL=" .env; then
        sed -i.bak 's|APP_URL=.*|APP_URL=https://manager.zena.com.vn|g' .env
        echo "   ✅ APP_URL updated to HTTPS"
    else
        echo "   ⚠️  APP_URL not found in .env"
        echo "   Please manually add: APP_URL=https://manager.zena.com.vn"
    fi
    
    # Update SANCTUM_STATEFUL_DOMAINS
    if grep -q "SANCTUM_STATEFUL_DOMAINS=" .env; then
        if ! grep -q "manager.zena.com.vn" .env; then
            sed -i.bak 's|SANCTUM_STATEFUL_DOMAINS=\(.*\)|SANCTUM_STATEFUL_DOMAINS=\1,manager.zena.com.vn|g' .env
            echo "   ✅ SANCTUM_STATEFUL_DOMAINS updated"
        fi
    fi
else
    echo "   ⚠️  .env file not found"
fi

# Step 5: Clear cache
echo ""
echo "📝 Step 5: Clearing Laravel cache"
cd "${PROJECT_ROOT}"

php artisan config:clear 2>/dev/null && echo "   ✅ Config cache cleared" || echo "   ⚠️  Config clear failed"
php artisan cache:clear 2>/dev/null && echo "   ✅ Application cache cleared" || echo "   ⚠️  Cache clear failed"
php artisan route:clear 2>/dev/null && echo "   ✅ Route cache cleared" || echo "   ⚠️  Route clear failed"

echo ""
echo "✅ Quick Setup Complete!"
echo ""
echo "📋 Next Steps:"
echo "   1. Add SSL virtual host to httpd-vhosts.conf (see HTTPS_SETUP_GUIDE.md)"
echo "   2. Enable mod_ssl in httpd.conf (see HTTPS_SETUP_GUIDE.md)"
echo "   3. Restart Apache from XAMPP Control Panel"
echo "   4. Open browser: https://manager.zena.com.vn"
echo ""
echo "📖 Detailed instructions: cat HTTPS_SETUP_GUIDE.md"

