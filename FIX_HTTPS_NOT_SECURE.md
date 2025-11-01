# 🔒 Fix "Not Secure" cho manager.zena.com.vn

## Vấn đề hiện tại
- ✅ Certificate đã được tạo bởi mkcert
- ❌ mkcert CA chưa được trust bởi hệ thống
- ❌ Trình duyệt hiển thị "Not Secure"

## ✅ Giải pháp (CHẠY LỆNH NÀY NGAY)

Mở Terminal và chạy:

```bash
# Step 1: Install mkcert CA vào system trust store
mkcert -install
```

Lệnh này sẽ yêu cầu sudo password. Nhập password của bạn.

## 📋 Các bước chi tiết

### Bước 1: Install mkcert CA

```bash
mkcert -install
```

**Output mong đợi:**
```
Created a new local CA at "/Users/[username]/Library/Application Support/mkcert" 💥
The local CA is now installed in the system trust store! ⚡️
The local CA is now installed in the Firefox and/or Chrome/Chromium trust store! ⚡️
```

### Bước 2: Verify certificate files tồn tại

```bash
ls -lh /Applications/XAMPP/xamppfiles/etc/ssl/manager.zena.com.vn*
```

Output phải có:
```
manager.zena.com.vn.pem
manager.zena.com.vn-key.pem
```

### Bước 3: Verify Apache config

```bash
grep -A 20 "VirtualHost \*:443" /Applications/XAMPP/xamppfiles/etc/extra/httpd-vhosts.conf | grep -E "(ServerName|SSLCertificate)"
```

Must show:
```
ServerName manager.zena.com.vn
SSLCertificateFile "/Applications/XAMPP/xamppfiles/etc/ssl/manager.zena.com.vn.pem"
SSLCertificateKeyFile "/Applications/XAMPP/xamppfiles/etc/ssl/manager.zena.com.vn-key.pem"
```

### Bước 4: Restart Apache

Từ XAMPP Control Panel:
- Stop Apache
- Start Apache

Hoặc từ terminal:
```bash
sudo /Applications/XAMPP/xamppfiles/xampp restartapache
```

### Bước 5: Test HTTPS

Mở trình duyệt mới (hoặc **restart browser**) và truy cập:
```
https://manager.zena.com.vn
```

**Kết quả mong đợi:**
- ✅ Không có "Not Secure" 
- ✅ Khóa màu xanh lá
- ✅ "Bảo mật" trong address bar

## ⚠️ Lưu ý quan trọng

### Quan trọng: Restart Browser sau khi install CA

Sau khi chạy `mkcert -install`, bạn **PHẢI** restart trình duyệt để trình duyệt nhận diện CA mới.

1. Đóng tất cả cửa sổ Chrome/Edge/Safari
2. Mở lại trình duyệt
3. Truy cập https://manager.zena.com.vn

### Nếu vẫn "Not Secure" sau khi restart browser

Kiểm tra certificate:

```bash
# Xem chi tiết certificate
openssl s_client -connect manager.zena.com.vn:443 -servername manager.zena.com.vn < /dev/null 2>/dev/null | openssl x509 -noout -issuer -subject -dates

# Kiểm tra Apache đang chạy
sudo /Applications/XAMPP/xamppfiles/xampp status

# Kiểm tra Apache error log
tail -20 /Applications/XAMPP/xamppfiles/logs/manager-zena-ssl-error.log
```

## 🗑️ Nếu cần tạo lại certificate

Nếu certificate bị lỗi, tạo lại:

```bash
cd /Applications/XAMPP/xamppfiles/etc/ssl

# Backup old certificates
mkdir -p backup
mv manager.zena.com.vn* backup/

# Tạo lại certificate
mkcert manager.zena.com.vn www.manager.zena.com.vn localhost

# Rename files
ls -lh manager.zena.com.vn*

# Files có thể tên là manager.zena.com.vn+2.pem
# Nếu vậy, rename:
mv manager.zena.com.vn+2.pem manager.zena.com.vn.pem
mv manager.zena.com.vn+2-key.pem manager.zena.com.vn-key.pem

# Restart Apache
sudo /Applications/XAMPP/xamppfiles/xampp restartapache
```

## 🔍 Troubleshooting

### Vấn đề 1: "mkcert: command not found"

**Giải pháp:**
```bash
brew install mkcert
mkcert -install
```

### Vấn đề 2: "The local CA is now installed" nhưng vẫn Not Secure

**Giải pháp:**
1. Restart browser
2. Clear browser cache: Chrome → Settings → Privacy → Clear browsing data
3. Hard reload: Cmd+Shift+R (Mac) hoặc Ctrl+F5 (Windows)

### Vấn đề 3: Apache không start sau khi enable SSL

**Giải pháp:**
```bash
# Check Apache config
/Applications/XAMPP/xamppfiles/bin/httpd -t

# Check mod_ssl
/Applications/XAMPP/xamppfiles/bin/httpd -M | grep ssl

# If mod_ssl not loaded, check httpd.conf
grep -i "LoadModule.*ssl" /Applications/XAMPP/xamppfiles/etc/httpd.conf
```

### Vấn đề 4: "Permission denied" khi access /Applications

**Giải pháp:**
```bash
sudo chown -R $(whoami):admin /Applications/XAMPP/xamppfiles/etc/ssl/
chmod 644 /Applications/XAMPP/xamppfiles/etc/ssl/*.pem
```

## ✅ Checklist hoàn tất

- [ ] Đã chạy `mkcert -install` (có output thành công)
- [ ] Certificate files tồn tại trong /Applications/XAMPP/xamppfiles/etc/ssl/
- [ ] Apache config có virtual host SSL cho port 443
- [ ] mod_ssl đã được enable trong httpd.conf
- [ ] Apache đã restart
- [ ] Browser đã restart (đóng hết và mở lại)
- [ ] Truy cập https://manager.zena.com.vn không còn "Not Secure"

## 🎉 Done!

Sau khi hoàn tất, bạn sẽ có:
- ✅ HTTPS hoạt động an toàn
- ✅ Không còn cảnh báo certificate
- ✅ Khóa màu xanh trong address bar

