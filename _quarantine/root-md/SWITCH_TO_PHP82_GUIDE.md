# 🔧 Hướng dẫn chuyển Apache sang PHP 8.2

## 📋 Kiểm tra hiện trạng

### Option 1: Kiểm tra qua phpinfo (KHUYẾN NGHỊ)

1. Truy cập: `https://manager.zena.com.vn/phpinfo.php`
2. Xem dòng "PHP Version" → phải >= 8.2.0

### Option 2: Kiểm tra qua terminal

```bash
# Check libphp.so đang link tới file nào
ls -la /Applications/XAMPP/xamppfiles/modules/libphp.so
```

---

## ✅ Giải pháp 1: Switch sang PHP 8.2 (Nhưng cần sudo)

**Nếu có file libphp8.2.so:**

```bash
# Step 1: Backup hiện tại
sudo cp /Applications/XAMPP/xamppfiles/modules/libphp.so /Applications/XAMPP/xamppfiles/modules/libphp8.0-backup.so

# Step 2: Link sang PHP 8.2
sudo rm /Applications/XAMPP/xamppfiles/modules/libphp.so
sudo ln -s /Applications/XAMPP/xamppfiles/modules/libphp8.2.so /Applications/XAMPP/xamppfiles/modules/libphp.so

# Step 3: Restart Apache từ XAMPP Control Panel
```

**Lưu ý:** Cần sudo password.

---

## ✅ Giải pháp 2: Thay đổi LoadModule trong httpd.conf (KHÔNG CẦN SUDO)

**Nếu httpd.conf đang load:**
```apache
LoadModule php8_module modules/libphp8.2.so
```

Thì chỉ cần đảm bảo:
1. File `libphp8.2.so` tồn tại
2. Restart Apache

**Nếu đang load:**
```apache
LoadModule php_module modules/libphp.so
```

Thì có thể:
- Giữ nguyên và đổi libphp.so link sang 8.2 (cần sudo)
- HOẶC đổi LoadModule trực tiếp (không cần sudo nhưng cần edit httpd.conf)

---

## ✅ Giải pháp 3: Downgrade Composer requirements (TẠM THỜI)

**Nếu không thể nâng PHP lên 8.2:**

Chỉnh sửa `composer.json`:
```json
"require": {
    "php": "^8.0|^8.1|^8.2"
}
```

Sau đó:
```bash
composer update --no-interaction
```

**⚠️ KHÔNG KHUYẾN NGHỊ** - Có thể gây compatibility issues.

---

## 🎯 Giải pháp khuyến nghị

### **Bước 1: Kiểm tra httpd.conf**

Mở file:
```bash
sudo nano /Applications/XAMPP/xamppfiles/etc/httpd.conf
```

Tìm dòng:
```apache
LoadModule php8_module modules/libphp8.2.so
```

**Nếu đã có dòng này:**
- ✅ Chỉ cần restart Apache
- ✅ PHP đã được config đúng

**Nếu là:**
```apache
LoadModule php_module modules/libphp.so
```

Thì cần:
1. Đổi thành: `LoadModule php8_module modules/libphp8.2.so`
2. Hoặc switch libphp.so link (cần sudo)

### **Bước 2: Test**

1. Truy cập: `https://manager.zena.com.vn/phpinfo.php`
2. Kiểm tra PHP version >= 8.2
3. Xóa file phpinfo.php sau khi test (security)

---

## 📊 Check hiện trạng

Chạy các lệnh sau để check:

```bash
# 1. Check Apache config
grep "LoadModule.*php" /Applications/XAMPP/xamppfiles/etc/httpd.conf

# 2. Check libphp.so
ls -la /Applications/XAMPP/xamppfiles/modules/libphp*.so

# 3. Check PHP CLI (không liên quan đến Apache)
php -v
```

---

## 🔍 Next Steps

1. **Check hiện trạng** - Xem Apache đang load PHP nào
2. **Chọn giải pháp** - Dựa vào kết quả check
3. **Apply changes** - Restart Apache
4. **Verify** - Test phpinfo.php và dashboard

