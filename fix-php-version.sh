#!/bin/bash

# Script để fix PHP version trong XAMPP
# Chỉ giữ lại PHP 8.2 module, comment các modules cũ

HTTPD_CONF="/Applications/XAMPP/xamppfiles/etc/httpd.conf"
BACKUP_FILE="${HTTPD_CONF}.backup.$(date +%Y%m%d_%H%M%S)"

echo "🔧 Fixing PHP version in Apache config..."
echo ""

# Check if running as sudo
if [ "$EUID" -ne 0 ]; then 
    echo "⚠️  Script cần quyền sudo"
    echo ""
    echo "📝 Chạy lại với sudo:"
    echo "   sudo bash fix-php-version.sh"
    exit 1
fi

# Backup httpd.conf
echo "📝 Step 1: Backing up httpd.conf..."
cp "$HTTPD_CONF" "$BACKUP_FILE"
echo "   ✅ Backup created: $BACKUP_FILE"

# Comment php4 and php5 modules, ensure php8_module is active
echo ""
echo "📝 Step 2: Updating PHP modules..."
sed -i.bak 's/^LoadModule php4_module/#LoadModule php4_module/g' "$HTTPD_CONF"
sed -i.bak 's/^LoadModule php5_module/#LoadModule php5_module/g' "$HTTPD_CONF"

# Ensure php8_module is not commented
sed -i.bak 's/^#LoadModule php8_module/LoadModule php8_module/g' "$HTTPD_CONF"

echo "   ✅ PHP modules updated"

# Verify
echo ""
echo "📝 Step 3: Verifying changes..."
echo ""
echo "Active PHP modules:"
grep -E "^LoadModule.*php" "$HTTPD_CONF" | grep -v "^#" | while read line; do
    echo "   ✅ $line"
done

echo ""
echo "Commented PHP modules:"
grep -E "^#LoadModule.*php" "$HTTPD_CONF" | while read line; do
    echo "   ⚠️  $line"
done

echo ""
echo "✅ Configuration updated!"
echo ""
echo "📋 Next steps:"
echo "   1. Restart Apache from XAMPP Control Panel"
echo "   2. Test: https://manager.zena.com.vn/phpinfo.php"
echo "   3. Verify PHP version >= 8.2.0"
echo "   4. Test dashboard: https://manager.zena.com.vn/app/dashboard"
echo ""

