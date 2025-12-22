#!/bin/bash

# Quick setup script for manager.zena.com.vn

echo "🚀 Quick Setup Domain manager.zena.com.vn"
echo "=========================================="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "⚠️  Script cần quyền sudo"
    echo ""
    echo "📝 Chạy lại với sudo:"
    echo "   sudo bash quick-setup-domain.sh"
    exit 1
fi

DOMAIN="manager.zena.com.vn"

# Step 1: Add to /etc/hosts
echo "📝 Step 1: Adding to /etc/hosts..."
if grep -q "$DOMAIN" /etc/hosts; then
    echo "   ✓ Entry already exists"
else
    echo "127.0.0.1 $DOMAIN" >> /etc/hosts
    echo "   ✓ Added $DOMAIN"
fi

# Step 2: Verify virtual host exists
echo ""
echo "📝 Step 2: Checking virtual host configuration..."
if grep -q "ServerName $DOMAIN" /Applications/XAMPP/xamppfiles/etc/extra/httpd-vhosts.conf; then
    echo "   ✓ Virtual host already configured"
else
    echo "   ⚠️  Virtual host not found"
    echo "   Please add it manually or run: ./setup-domain-manager.sh"
fi

echo ""
echo "✅ Domain configuration complete!"
echo ""
echo "📋 Next steps:"
echo "   1. Update .env file:"
echo "      - Change APP_URL to: http://$DOMAIN"
echo ""
echo "   2. Clear Laravel cache:"
echo "      cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage"
echo "      php artisan config:clear && php artisan cache:clear"
echo ""
echo "   3. Restart Apache in XAMPP Control Panel"
echo ""
echo "   4. Open browser: http://$DOMAIN"
echo ""

