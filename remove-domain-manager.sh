#!/bin/bash

# Script để xóa cấu hình domain manager.zena.com.vn

DOMAIN="manager.zena.com.vn"
APACHE_VHOST_FILE="/Applications/XAMPP/xamppfiles/etc/extra/httpd-vhosts.conf"

echo "🗑️  Đang xóa cấu hình ${DOMAIN}..."
echo ""

# Kiểm tra quyền sudo
if [ "$EUID" -ne 0 ]; then 
    echo "⚠️  Bạn cần quyền sudo để chạy script này"
    echo "📝 Chạy lại script với: sudo ./remove-domain-manager.sh"
    exit 1
fi

# 1. Xóa entry từ /etc/hosts
echo "📝 Đang xóa entry khỏi /etc/hosts..."
if grep -q "${DOMAIN}" /etc/hosts; then
    sed -i.bak "/${DOMAIN}/d" /etc/hosts
    echo "   ✓ Đã xóa ${DOMAIN} khỏi /etc/hosts"
else
    echo "   ℹ️  Entry không tồn tại trong /etc/hosts"
fi

# 2. Xóa virtual host khỏi Apache config
echo ""
echo "📝 Đang xóa virtual host khỏi Apache config..."
if grep -q "ServerName ${DOMAIN}" "${APACHE_VHOST_FILE}"; then
    # Backup file hiện tại
    cp "${APACHE_VHOST_FILE}" "${APACHE_VHOST_FILE}.backup.$(date +%Y%m%d_%H%M%S)"
    echo "   ✓ Đã backup file cấu hình"
    
    # Xóa virtual host (từ <VirtualHost đến </VirtualHost>)
    sed -i.bak '/^# Virtual Host cho '"${DOMAIN}"'/,/^<\/VirtualHost>/d' "${APACHE_VHOST_FILE}"
    echo "   ✓ Đã xóa virtual host cho ${DOMAIN}"
    
    # Xóa dòng trống thừa
    sed -i.bak '/^$/N;/^\n$/d' "${APACHE_VHOST_FILE}"
else
    echo "   ℹ️  Virtual host không tồn tại trong Apache config"
fi

echo ""
echo "✅ Đã xóa cấu hình ${DOMAIN}!"
echo ""
echo "📋 Các bước tiếp theo:"
echo "   1. Restart Apache trong XAMPP Control Panel"
echo "   2. Nếu cần, khôi phục cấu hình từ file .backup"

