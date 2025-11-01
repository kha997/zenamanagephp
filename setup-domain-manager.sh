#!/bin/bash

# Script để setup domain manager.zena.com.vn
# Yêu cầu: Quyền sudo để sửa /etc/hosts

DOMAIN="manager.zena.com.vn"
IP="127.0.0.1"
DOCUMENT_ROOT="/Applications/XAMPP/xamppfiles/htdocs/zenamanage/public"
APACHE_VHOST_FILE="/Applications/XAMPP/xamppfiles/etc/extra/httpd-vhosts.conf"

echo "🚀 Đang setup domain ${DOMAIN}..."
echo ""

# Kiểm tra quyền sudo
if [ "$EUID" -ne 0 ]; then 
    echo "⚠️  Bạn cần quyền sudo để chạy script này"
    echo "📝 Chạy lại script với: sudo ./setup-domain-manager.sh"
    exit 1
fi

# 1. Thêm entry vào /etc/hosts
echo "📝 Đang thêm entry vào /etc/hosts..."
if grep -q "${DOMAIN}" /etc/hosts; then
    echo "   ✓ Entry đã tồn tại trong /etc/hosts"
else
    echo "${IP} ${DOMAIN}" >> /etc/hosts
    echo "   ✓ Đã thêm ${DOMAIN} vào /etc/hosts"
fi

# 2. Thêm virtual host vào Apache
echo ""
echo "📝 Đang thêm virtual host vào Apache config..."

# Kiểm tra xem virtual host đã tồn tại chưa
if grep -q "ServerName ${DOMAIN}" "${APACHE_VHOST_FILE}"; then
    echo "   ✓ Virtual host đã tồn tại trong Apache config"
else
    # Backup file hiện tại
    cp "${APACHE_VHOST_FILE}" "${APACHE_VHOST_FILE}.backup.$(date +%Y%m%d_%H%M%S)"
    echo "   ✓ Đã backup file cấu hình"

    # Thêm virtual host mới
    cat >> "${APACHE_VHOST_FILE}" << EOF

# Virtual Host cho ${DOMAIN}
<VirtualHost *:80>
    ServerAdmin admin@${DOMAIN}
    DocumentRoot "${DOCUMENT_ROOT}"
    ServerName ${DOMAIN}
    ServerAlias www.${DOMAIN}
    
    # Cấu hình thư mục
    <Directory "${DOCUMENT_ROOT}">
        Options Indexes FollowSymLinks MultiViews
        AllowOverride All
        Require all granted
        
        # Kích hoạt mod_rewrite cho Laravel
        RewriteEngine On
        
        # Laravel URL rewriting
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^ index.php [L]
    </Directory>
    
    # Log files
    ErrorLog "/Applications/XAMPP/xamppfiles/logs/${DOMAIN}-error.log"
    CustomLog "/Applications/XAMPP/xamppfiles/logs/${DOMAIN}-access.log" common
</VirtualHost>
EOF
    echo "   ✓ Đã thêm virtual host cho ${DOMAIN}"
fi

# 3. Kiểm tra mod_rewrite
echo ""
echo "🔍 Đang kiểm tra Apache modules..."
if grep -q "LoadModule rewrite_module" /Applications/XAMPP/xamppfiles/etc/httpd.conf; then
    echo "   ✓ mod_rewrite đã được kích hoạt"
else
    echo "   ⚠️  mod_rewrite chưa được kích hoạt"
    echo "      Vui lòng bỏ comment dòng: LoadModule rewrite_module modules/mod_rewrite.so"
fi

# 4. Kiểm tra virtual hosts được enable chưa
echo ""
echo "🔍 Đang kiểm tra virtual hosts..."
if grep -q "Include.*httpd-vhosts.conf" /Applications/XAMPP/xamppfiles/etc/httpd.conf; then
    echo "   ✓ Virtual hosts đã được kích hoạt"
else
    echo "   ⚠️  Virtual hosts chưa được kích hoạt"
    echo "      Vui lòng bỏ comment dòng: Include etc/extra/httpd-vhosts.conf"
fi

echo ""
echo "✅ Hoàn tất cấu hình!"
echo ""
echo "📋 Các bước tiếp theo:"
echo "   1. Restart Apache trong XAMPP Control Panel"
echo "   2. Truy cập http://${DOMAIN}"
echo "   3. Nếu chưa có .env, copy từ env.example và cập nhật APP_URL=http://${DOMAIN}"
echo ""
echo "🔧 Để revert lại, chạy: sudo ./remove-domain-manager.sh"

