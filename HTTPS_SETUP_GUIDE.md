# 🔒 Hướng dẫn Setup HTTPS cho manager.zena.com.vn

## Mục tiêu
Setup HTTPS local với certificate đáng tin cậy bằng mkcert để truy cập `https://manager.zena.com.vn` mà không có lỗi certificate.

---

## 🚀 Quick Start (Tất cả các bước)

### Bước 1: Cài đặt mkcert CA (chỉ cần làm 1 lần)

```bash
# Chạy lệnh này (không cần sudo)
mkcert -install
```

**Lưu ý:** Nếu lệnh này yêu cầu sudo password, hãy nhập password của bạn.

### Bước 2: Tạo Certificate

```bash
cd /Applications/XAMPP/xamppfiles/etc/ssl

# Tạo certificate cho domain
mkcert manager.zena.com.vn www.manager.zena.com.vn

# Kiểm tra files đã được tạo
ls -lh manager.zena.com.vn*
```

Output sẽ có 2 files:
- `manager.zena.com.vn+2.pem` (hoặc tương tự)
- `manager.zena.com.vn+2-key.pem` (hoặc tương tự)

### Bước 3: Rename Certificate Files (nếu cần)

```bash
cd /Applications/XAMPP/xamppfiles/etc/ssl

# Nếu files có tên với "+2", rename chúng
mv manager.zena.com.vn+2.pem manager.zena.com.vn.pem
mv manager.zena.com.vn+2-key.pem manager.zena.com.vn-key.pem

# Hoặc tạo symlink
ln -sf manager.zena.com.vn+2.pem manager.zena.com.vn.pem
ln -sf manager.zena.com.vn+2-key.pem manager.zena.com.vn-key.pem
```

### Bước 4: Cấu hình Apache Virtual Host SSL

Mở file:
```bash
sudo nano /Applications/XAMPP/xamppfiles/etc/extra/httpd-vhosts.conf
```

Thêm vào cuối file:

```apache
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
    </Directory>
    
    # Log files
    ErrorLog "/Applications/XAMPP/xamppfiles/logs/manager-zena-ssl-error.log"
    CustomLog "/Applications/XAMPP/xamppfiles/logs/manager-zena-ssl-access.log" common
</VirtualHost>

# Redirect HTTP to HTTPS
<VirtualHost *:80>
    ServerName manager.zena.com.vn
    ServerAlias www.manager.zena.com.vn
    
    Redirect permanent / https://manager.zena.com.vn/
</VirtualHost>
```

Lưu file (Ctrl+X, Y, Enter).

### Bước 5: Enable mod_ssl trong Apache

Mở file:
```bash
sudo nano /Applications/XAMPP/xamppfiles/etc/httpd.conf
```

Tìm và bỏ comment (xóa dấu `#`) các dòng sau:

```apache
# Load mod_ssl
LoadModule ssl_module modules/mod_ssl.so

# Include SSL config
Include etc/extra/httpd-ssl.conf
```

**Tìm các dòng này trong file:**
```apache
#LoadModule ssl_module modules/mod_ssl.so
#Include etc/extra/httpd-ssl.conf
```

**Sửa thành:**
```apache
LoadModule ssl_module modules/mod_ssl.so
Include etc/extra/httpd-ssl.conf
```

Lưu file.

### Bước 6: Update .env cho HTTPS

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage

# Backup .env
cp .env .env.backup

# Update APP_URL
nano .env
```

Tìm dòng:
```env
APP_URL=http://manager.zena.com.vn
```

Đổi thành:
```env
APP_URL=https://manager.zena.com.vn
```

Nếu có `SANCTUM_STATEFUL_DOMAINS`, thêm domain HTTPS:
```env
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,manager.zena.com.vn
```

Lưu file.

### Bước 7: Clear Laravel Cache

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage

php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### Bước 8: Restart Apache

Trong XAMPP Control Panel:
1. **Stop** Apache
2. **Start** Apache

Hoặc từ terminal:
```bash
sudo /Applications/XAMPP/xamppfiles/xampp stopapache
sudo /Applications/XAMPP/xamppfiles/xampp startapache
```

### Bước 9: Test HTTPS

Mở trình duyệt và truy cập:
```
https://manager.zena.com.vn
```

✅ **Kết quả mong đợi:**
- Không có lỗi certificate
- Trang load thành công
- Khóa màu xanh lá cây trong address bar
- "Bảo mật" hoặc "Secure" được hiển thị

---

## 🔍 Troubleshooting

### Lỗi: "mod_ssl is not loaded"

**Giải pháp:**
1. Kiểm tra file `httpd.conf` có enable mod_ssl chưa
2. Restart Apache

### Lỗi: "SSL: error:0A000086:SSL routines:ssl_check_srvr_ecc_cert_and_alg:bad ecc cert"

**Nguyên nhân:** Certificate files không đúng tên

**Giải pháp:**
```bash
cd /Applications/XAMPP/xamppfiles/etc/ssl
ls -lh
# Kiểm tra tên files thực tế

# Nếu files tên là manager.zena.com.vn+2.pem thì:
# Sửa httpd-vhosts.conf để dùng đúng tên file
# Hoặc tạo symlink
```

### Lỗi: "AH01909: localhost:443:0 server certificate does NOT include an ID which matches the server name"

**Nguyên nhân:** Certificate không khớp với ServerName

**Giải pháp:**
Tạo lại certificate với đúng domain:
```bash
cd /Applications/XAMPP/xamppfiles/etc/ssl
mkcert manager.zena.com.vn www.manager.zena.com.vn localhost
```

### Lỗi: "AH02572: Failed to configure at least one certificate and key"

**Nguyên nhân:** Certificate files không tồn tại hoặc path sai

**Giải pháp:**
```bash
# Kiểm tra files tồn tại
ls -lh /Applications/XAMPP/xamppfiles/etc/ssl/manager.zena.com.vn*

# Kiểm tra path trong httpd-vhosts.conf là đúng
cat /Applications/XAMPP/xamppfiles/etc/extra/httpd-vhosts.conf | grep SSLCertificate
```

### Lỗi: "Connection refused" trên port 443

**Nguyên nhân:** Apache không listen port 443

**Giải pháp:**
1. Kiểm tra `Listen 443` trong `httpd.conf` hoặc `httpd-ssl.conf`
2. Kiểm tra firewall:
   ```bash
   sudo lsof -i :443
   ```

### Redirect loop (vô hạn)

**Nguyên nhân:** Cả HTTP và HTTPS đều redirect

**Giải pháp:**
Xóa phần redirect trong HTTP virtual host nếu không cần.

---

## 📝 Kiểm tra Configuration

### Test 1: Kiểm tra certificate

```bash
openssl s_client -connect manager.zena.com.vn:443 -servername manager.zena.com.vn
```

### Test 2: Kiểm tra Apache modules

```bash
/Applications/XAMPP/xamppfiles/bin/httpd -M | grep ssl
```

Output phải có:
```
ssl_module (shared)
```

### Test 3: Kiểm tra virtual hosts

```bash
curl -kI https://manager.zena.com.vn
```

Response phải là 200 hoặc 302.

---

## 🗑️ Để Rollback về HTTP

Nếu muốn tắt HTTPS:

```bash
# 1. Đổi APP_URL về HTTP
cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage
nano .env
# Đổi: APP_URL=http://manager.zena.com.vn

# 2. Xóa SSL virtual host
sudo nano /Applications/XAMPP/xamppfiles/etc/extra/httpd-vhosts.conf
# Xóa phần <VirtualHost *:443>

# 3. Clear cache
php artisan config:clear

# 4. Restart Apache
```

---

## ✅ Done!

Sau khi hoàn tất, bạn có thể truy cập an toàn qua HTTPS:

**https://manager.zena.com.vn**

🎉 **Chúc mừng! Bạn đã setup HTTPS thành công!**

