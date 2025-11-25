# Hướng dẫn Setup Domain Manager Zena

## 🎯 Mục tiêu
Setup domain `manager.zena.com.vn` để sử dụng ứng dụng ZenaManage qua domain thay vì localhost.

## 📋 Các bước thực hiện

### 1. Thêm domain vào /etc/hosts

Mở Terminal và chạy lệnh sau:

```bash
sudo nano /etc/hosts
```

Thêm dòng sau vào cuối file:

```
127.0.0.1 manager.zena.com.vn
```

Lưu và đóng file (Ctrl+X, sau đó Y, Enter).

### 2. Cập nhật file .env

Mở file `.env` và thay đổi:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage
nano .env
```

Tìm dòng `APP_URL` và thay đổi thành:

```env
APP_URL=http://manager.zena.com.vn
```

Nếu có `SANCTUM_STATEFUL_DOMAINS`, hãy thêm domain mới vào:

```env
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,manager.zena.com.vn
```

### 3. Clear cache Laravel

Chạy các lệnh sau để clear cache:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage

php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### 4. Restart Apache từ XAMPP Control Panel

1. Mở **XAMPP Control Panel**
2. Stop Apache nếu đang chạy
3. Start lại Apache

### 5. Kiểm tra Virtual Host đã được enable

Kiểm tra file cấu hình Apache:

```bash
cat /Applications/XAMPP/xamppfiles/etc/httpd.conf | grep httpd-vhosts
```

Nếu dòng này bị comment, hãy mở file và bỏ comment:

```bash
sudo nano /Applications/XAMPP/xamppfiles/etc/httpd.conf
```

Tìm dòng:
```
#Include etc/extra/httpd-vhosts.conf
```

Sửa thành:
```
Include etc/extra/httpd-vhosts.conf
```

Lưu file và restart Apache.

### 6. Kiểm tra mod_rewrite

Kiểm tra xem mod_rewrite đã được enable chưa:

```bash
cat /Applications/XAMPP/xamppfiles/etc/httpd.conf | grep -i rewrite
```

Tìm dòng:
```
#LoadModule rewrite_module modules/mod_rewrite.so
```

Nếu bị comment, sửa thành:
```
LoadModule rewrite_module modules/mod_rewrite.so
```

Lưu file và restart Apache.

### 7. Truy cập domain mới

Mở trình duyệt và truy cập:

```
http://manager.zena.com.vn
```

Hoặc với www:

```
http://www.manager.zena.com.vn
```

## ✅ Kiểm tra

### Test 1: Ping domain

```bash
ping manager.zena.com.vn
```

Kết quả mong đợi: `127.0.0.1`

### Test 2: Kiểm tra virtual host

```bash
curl -I http://manager.zena.com.vn
```

Response code phải là 200 hoặc 302 (redirect).

### Test 3: Kiểm tra Laravel route

Truy cập: `http://manager.zena.com.vn`

Nếu thấy trang login hoặc dashboard của ZenaManage, setup đã thành công!

## 🔧 Troubleshooting

### Vấn đề: Không thể truy cập domain

**Giải pháp:**
1. Kiểm tra Apache đang chạy: `ps aux | grep httpd`
2. Kiểm tra log: `/Applications/XAMPP/xamppfiles/logs/manager-zena-error.log`
3. Kiểm tra virtual host: `cat /Applications/XAMPP/xamppfiles/etc/extra/httpd-vhosts.conf`
4. Clear cache lại và restart Apache

### Vấn đề: 403 Forbidden

**Giải pháp:**
1. Kiểm tra quyền file: `ls -la /Applications/XAMPP/xamppfiles/htdocs/zenamanage/public`
2. Đảm bảo AllowOverride All trong Directory config
3. Kiểm tra SELinux (nếu có): `sudo chcon -R -t httpd_sys_content_t /Applications/XAMPP/xamppfiles/htdocs/zenamanage/`

### Vấn đề: 500 Internal Server Error

**Giải pháp:**
1. Kiểm tra log: `/Applications/XAMPP/xamppfiles/logs/manager-zena-error.log`
2. Enable debug trong `.env`: `APP_DEBUG=true`
3. Kiểm tra permission của storage và bootstrap/cache:
   ```bash
   chmod -R 775 storage bootstrap/cache
   chown -R www-data:www-data storage bootstrap/cache
   ```

### Vấn đề: Routes không hoạt động (404)

**Giải pháp:**
1. Đảm bảo `.htaccess` trong public folder có đúng nội dung
2. Kiểm tra mod_rewrite đã được enable
3. Chạy: `php artisan route:cache`

## 🗑️ Để xóa domain (Rollback)

Nếu muốn quay lại sử dụng localhost:

```bash
# 1. Xóa entry trong /etc/hosts
sudo nano /etc/hosts
# Xóa dòng: 127.0.0.1 manager.zena.com.vn

# 2. Sửa lại .env
cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage
nano .env
# Đổi APP_URL về: APP_URL=http://localhost

# 3. Clear cache
php artisan config:clear
php artisan cache:clear

# 4. Restart Apache
```

Hoặc chạy script tự động:

```bash
sudo ./remove-domain-manager.sh
```

## 📝 Ghi chú

- Domain này chỉ hoạt động trên máy local của bạn
- Để sử dụng trên các máy khác, cần setup DNS server thật
- Có thể sử dụng nhiều domain khác nhau cho các môi trường khác nhau (dev, staging, production)

## 🎉 Hoàn tất!

Nếu mọi thứ đã OK, bạn có thể truy cập ZenaManage qua:

**http://manager.zena.com.vn**

Chúc bạn sử dụng vui vẻ! 🚀

