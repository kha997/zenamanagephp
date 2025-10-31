# 🚨 URGENT: Fix PHP Version - Apache đang load nhiều PHP modules!

## ⚠️ Vấn đề phát hiện

Apache đang load **3 PHP modules cùng lúc**:
```
LoadModule php4_module        modules/libphp4.so
LoadModule php5_module        modules/libphp5.so
LoadModule php8_module modules/libphp8.2.so
```

Điều này gây **conflict** và Apache có thể dùng PHP module đầu tiên (php4) thay vì PHP 8.2!

---

## ✅ Giải pháp ngay lập tức

### Cách 1: Chạy script tự động (KHUYẾN NGHỊ)

```bash
sudo bash fix-php-version.sh
```

Script sẽ:
- ✅ Backup httpd.conf
- ✅ Comment php4 và php5 modules
- ✅ Đảm bảo php8_module active
- ✅ Restart Apache (bạn tự làm từ XAMPP)

### Cách 2: Sửa thủ công

1. Mở file với quyền sudo:
   ```bash
   sudo nano /Applications/XAMPP/xamppfiles/etc/httpd.conf
   ```

2. Tìm và comment các dòng:
   ```apache
   #LoadModule php4_module        modules/libphp4.so
   #LoadModule php5_module        modules/libphp5.so
   LoadModule php8_module modules/libphp8.2.so
   ```

3. Save file (Ctrl+X, Y, Enter)

4. Restart Apache từ XAMPP Control Panel

---

## 🚀 Sau khi sửa

### 1. Restart Apache
Từ XAMPP Control Panel:
- Stop Apache
- Start Apache

### 2. Test PHP version
Truy cập: `https://manager.zena.com.vn/phpinfo.php`

**Kết quả mong đợi:** PHP Version >= 8.2.0

### 3. Test Dashboard
Truy cập: `https://manager.zena.com.vn/app/dashboard`

**Kết quả mong đợi:** Dashboard load thành công, không còn lỗi Composer

### 4. Xóa phpinfo.php (Security)
```bash
rm public/phpinfo.php
```

---

## ✅ Verification

Sau khi restart Apache, check:

```bash
# Check active PHP modules
grep -E "^LoadModule.*php" /Applications/XAMPP/xamppfiles/etc/httpd.conf | grep -v "^#"
```

**Chỉ nên thấy:**
```
LoadModule php8_module modules/libphp8.2.so
```

---

## 📋 Summary

**Problem:** Apache load nhiều PHP modules → conflict
**Solution:** Comment các modules cũ, chỉ giữ php8_module
**Action:** Chạy `sudo bash fix-php-version.sh` → Restart Apache → Test

---

## ⚡ Quick Fix

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage
sudo bash fix-php-version.sh
# Sau đó restart Apache từ XAMPP Control Panel
```

