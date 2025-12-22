# ZenaManage System - Hướng Dẫn Khởi Động

## 🚀 Khởi Động Hệ Thống

### Phương Pháp 1: Sử dụng Script Tự Động (Khuyến Nghị)

```bash
# Khởi động toàn bộ hệ thống
./start-system.sh

# Dừng hệ thống
./stop-system.sh
```

### Phương Pháp 2: Khởi Động Thủ Công

#### 1. Cài Đặt Dependencies
```bash
# Cài đặt PHP dependencies
composer install --no-dev --optimize-autoloader

# Cài đặt Node.js dependencies
npm install --legacy-peer-deps
```

#### 2. Thiết Lập Cấu Hình
```bash
# Tạo application key
php artisan key:generate

# Cache cấu hình
php artisan config:cache
```

#### 3. Khởi Động Services
```bash
# Terminal 1: Laravel Server
php artisan serve --host=0.0.0.0 --port=8000

# Terminal 2: Vite Dev Server
npm run dev
```

## 📊 Truy Cập Hệ Thống

- **Laravel Application**: http://localhost:8000
- **Dashboard**: http://localhost:8000/app/dashboard
- **Vite Dev Server**: http://localhost:3000
- **API Documentation**: http://localhost:8000/api/documentation

## 🔧 Yêu Cầu Hệ Thống

- **PHP**: 8.0+ (Hiện tại: 8.2.29)
- **Composer**: 2.0+
- **Node.js**: 16+ (Hiện tại: 22.15.0)
- **MySQL**: 5.7+ hoặc 8.0+
- **Redis**: 6.0+ (Tùy chọn)

## 📁 Cấu Trúc Dự Án

```
zenamanage/
├── app/                    # Laravel Application
├── resources/              # Views, Assets, Lang
├── routes/                 # Route Definitions
├── database/               # Migrations, Seeders
├── public/                 # Public Assets
├── frontend/               # React Frontend
├── start-system.sh         # Script khởi động
├── stop-system.sh          # Script dừng hệ thống
└── .env                    # Environment Configuration
```

## 🛠️ Các Lệnh Hữu Ích

### Laravel Commands
```bash
# Kiểm tra trạng thái migrations
php artisan migrate:status

# Chạy migrations
php artisan migrate

# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Tạo user admin
php artisan make:user --admin
```

### Frontend Commands
```bash
# Build production
npm run build

# Run tests
npm test

# Lint code
npm run lint
```

## 🔍 Kiểm Tra Trạng Thái

### Kiểm Tra Ports
```bash
# Kiểm tra port 8000 (Laravel)
lsof -i :8000

# Kiểm tra port 3000 (Vite)
lsof -i :3000
```

### Kiểm Tra Processes
```bash
# Kiểm tra PHP processes
ps aux | grep php

# Kiểm tra Node.js processes
ps aux | grep node
```

## 🚨 Xử Lý Sự Cố

### Port Đã Được Sử Dụng
```bash
# Tìm process sử dụng port
lsof -i :8000

# Kill process
kill -9 <PID>
```

### Database Connection Error
```bash
# Kiểm tra cấu hình database trong .env
# Đảm bảo MySQL đang chạy
# Kiểm tra credentials
```

### Cache Issues
```bash
# Clear tất cả cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Rebuild cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 📝 Logs

- **Laravel Logs**: `storage/logs/laravel.log`
- **Nginx Logs**: `/var/log/nginx/` (nếu sử dụng)
- **MySQL Logs**: `/var/log/mysql/` (nếu sử dụng)

## 🔐 Security

- Đảm bảo file `.env` không được commit vào Git
- Sử dụng HTTPS trong production
- Cấu hình firewall phù hợp
- Regular security updates

## 📞 Hỗ Trợ

Nếu gặp vấn đề, vui lòng:
1. Kiểm tra logs
2. Xem lại cấu hình
3. Tham khảo documentation
4. Liên hệ team development

---

**Lưu ý**: Script `start-system.sh` sẽ tự động kiểm tra và khởi động tất cả services cần thiết. Khuyến nghị sử dụng script này để đảm bảo hệ thống khởi động đúng cách.
