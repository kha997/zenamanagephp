# 🚨 Fix PHP Version - Dashboard 500 Error

## Vấn đề

Dashboard 500 error do PHP version mismatch:
- **Yêu cầu**: PHP >= 8.2.0
- **Apache đang dùng**: PHP 8.0.28
- **File có sẵn**: `/Applications/XAMPP/xamppfiles/modules/libphp8.2.so`

## ✅ Giải pháp

### Bước 1: Backup libphp.so hiện tại

```bash
sudo cp /Applications/XAMPP/xamppfiles/modules/libphp.so /Applications/XAMPP/xamppfiles/modules/libphp8.0-backup.so
```

### Bước 2: Link libphp8.2.so thành libphp.so

```bash
sudo rm /Applications/XAMPP/xamppfiles/modules/libphp.so
sudo ln -s /Applications/XAMPP/xamppfiles/modules/libphp8.2.so /Applications/XAMPP/xamppfiles/modules/libphp.so
```

### Bước 3: Restart Apache

Từ XAMPP Control Panel:
- Stop Apache
- Start Apache

### Bước 4: Verify PHP version

Tạo file test: `public/phpinfo.php`
```php
<?php phpinfo(); ?>
```

Truy cập: `https://manager.zena.com.vn/phpinfo.php`
Kiểm tra PHP version phải >= 8.2.0

### Bước 5: Test dashboard

Truy cập: `https://manager.zena.com.vn/app/dashboard`

## 🔍 Verification

```bash
# Check PHP CLI version
php -v
# Must show: PHP 8.2.29 or higher

# Check Apache PHP module
ls -la /Applications/XAMPP/xamppfiles/modules/libphp.so
# Must link to libphp8.2.so
```

