# 🔍 E2E Smoke Tests Debug - Log Analysis Guide

## 📍 **Cách Kiểm Tra Logs Trên GitHub Actions**

### **Bước 1: Truy Cập Workflow Run**
1. Vào repository trên GitHub
2. Click tab **Actions**
3. Tìm workflow run **"E2E Smoke Tests Debug"** (status: ❌ failed)
4. Click vào workflow run đó

### **Bước 2: Xác Định Step Đầu Tiên Fail**
1. Scroll xuống phần **Jobs**
2. Click vào job **"debug"** (có icon ❌)
3. Scroll qua từng step từ trên xuống
4. Step đầu tiên có icon ❌ hoặc ⚠️ là **step đầu tiên fail**

### **Bước 3: Xem Chi Tiết Lỗi**
1. Click vào step đã fail
2. Mở phần logs để xem error message
3. Tìm keywords như:
   - `Error:`, `Failed:`, `Fatal error:`
   - `No such file or directory`
   - `Command failed`
   - `Timeout`

---

## 🔍 **Các Steps Trong Workflow (Theo Thứ Tự)**

Dựa vào workflow file, đây là các steps theo thứ tự chạy:

| Step # | Step Name | Có Thể Fail Vì | Priority Check |
|--------|-----------|----------------|----------------|
| 1 | Checkout code | ❌ Không có code | ✅ Thường pass |
| 2 | Setup Node.js | ❌ Node version issue | ⚠️ Kiểm tra node version |
| 3 | Install dependencies | ❌ package-lock.json out of sync | ⚠️ **RẤT CÓ THỂ** |
| 4 | Install Playwright browsers | ❌ Network timeout, permissions | ⚠️ Có thể fail |
| 5 | Setup PHP | ❌ PHP version, extensions | ⚠️ Kiểm tra PHP |
| 6 | Install Composer dependencies | ❌ composer.lock issue, memory | ⚠️ Có thể fail |
| 7 | **Copy environment file** | ❌ **.env.example không tồn tại** | 🔴 **RẤT CÓ THỂ - CRITICAL** |
| 8 | Generate application key | ❌ PHP error, missing .env | 🔴 **Nếu step 7 fail** |
| 9 | Configure database for CI | ✅ Thường pass | - |
| 10 | Test MySQL connection | ❌ MySQL not ready | ⚠️ Có thể fail |
| 11 | Create database | ❌ Migration errors | ⚠️ **Nếu MySQL fail** |
| 12 | Seed database | ❌ Seeder errors | ⚠️ **Nếu migrate fail** |
| 13 | Verify database setup | ❌ Tinker command errors | ⚠️ Low priority |
| 14 | Test environment variables | ⚠️ Secrets not set | ⚠️ **Check secrets** |
| 15 | Run smoke tests | ❌ Tests fail, server not ready | 🔴 **Nếu server không start** |

---

## 🎯 **Step Có Khả Năng Fail Nhất (Theo Thứ Tự)**

### **1. 🔴 Step 7: "Copy environment file"** (Line 50-51)
```yaml
- name: Copy environment file
  run: cp .env.example .env
```

**Nguyên nhân fail:**
- File `.env.example` không tồn tại
- Error: `cp: cannot stat '.env.example': No such file or directory`

**Cách kiểm tra:**
- Trong logs, tìm: `cp: cannot stat`
- Check repository có file `.env.example` không

**Hậu quả cascade:**
- Step 8 (key:generate) sẽ fail vì không có .env
- Step 10-15 sẽ fail vì không có database config

---

### **2. 🟠 Step 10: "Test MySQL connection"** (Line 68-71)
```yaml
- name: Test MySQL connection
  run: |
    echo "Testing MySQL connection..."
    mysql -h 127.0.0.1 -u e2e_user -pe2e_password -e "SELECT 1 as test;"
```

**Nguyên nhân fail:**
- MySQL service chưa ready
- MySQL client chưa install
- Connection timeout

**Error message thường thấy:**
- `ERROR 2002: Can't connect to MySQL server`
- `ERROR 1045: Access denied`
- `command not found: mysql`

**Hậu quả cascade:**
- Step 11-12 (migrate, seed) sẽ fail

---

### **3. 🟠 Step 14: "Test environment variables"** (Line 87-90)
```yaml
- name: Test environment variables
  run: |
    echo "SMOKE_ADMIN_EMAIL: ${{ secrets.SMOKE_ADMIN_EMAIL }}"
    echo "SMOKE_ADMIN_PASSWORD: ${{ secrets.SMOKE_ADMIN_PASSWORD }}"
```

**Nguyên nhân fail:**
- Secrets không được set (sẽ hiển thị empty, không fail)
- Nhưng step 15 sẽ fail vì không có credentials

**Cách kiểm tra:**
- Trong logs, check xem secrets có giá trị không
- Nếu thấy: `SMOKE_ADMIN_EMAIL: ` (empty) → secrets chưa set

---

### **4. 🔴 Step 15: "Run smoke tests"** (Line 92-96)
```yaml
- name: Run smoke tests
  run: npm run test:e2e:smoke
```

**Nguyên nhân fail:**
- Server không start kịp (webServer timeout)
- Tests fail (authentication errors)
- Missing env vars (SMOKE_ADMIN_EMAIL, SMOKE_ADMIN_PASSWORD)

**Error messages thường thấy:**
- `Error: page.goto: net::ERR_CONNECTION_REFUSED`
- `Timeout 120000ms exceeded`
- `Assertion error: Expected true but got false`

---

## 📋 **Checklist Debug Nhanh**

### **1. Kiểm Tra Logs GitHub Actions**
- [ ] Vào GitHub Actions tab
- [ ] Tìm workflow run failed
- [ ] Xác định step đầu tiên có icon ❌
- [ ] Copy error message

### **2. Kiểm Tra File .env.example**
```bash
# Trong repository local
ls -la .env.example
```
- [ ] File `.env.example` có tồn tại không?
- [ ] Nếu không → đây là nguyên nhân chính

### **3. Kiểm Tra GitHub Secrets**
- [ ] Vào Settings → Secrets and variables → Actions
- [ ] Check `SMOKE_ADMIN_EMAIL` có được set không
- [ ] Check `SMOKE_ADMIN_PASSWORD` có được set không
- [ ] Values phải là:
  - `SMOKE_ADMIN_EMAIL=admin@zena.local`
  - `SMOKE_ADMIN_PASSWORD=password`

### **4. Kiểm Tra Workflow File**
- [ ] Workflow có syntax errors không?
- [ ] Tất cả steps có đúng thứ tự không?

---

## 🔧 **Script Để Debug Local**

Chạy script này để simulate workflow local:

```bash
# Tạo file debug script
cat > debug-workflow.sh << 'EOF'
#!/bin/bash
set -e

echo "🔍 Debugging E2E Smoke Tests Workflow..."

# Step 1: Check .env.example
echo "📁 Checking .env.example..."
if [ ! -f .env.example ]; then
  echo "❌ ERROR: .env.example not found!"
  exit 1
else
  echo "✅ .env.example exists"
fi

# Step 2: Check dependencies
echo "📦 Checking dependencies..."
if [ ! -f package-lock.json ]; then
  echo "⚠️  WARNING: package-lock.json not found"
fi

if [ ! -f composer.lock ]; then
  echo "⚠️  WARNING: composer.lock not found"
fi

# Step 3: Check env vars
echo "🔐 Checking environment variables..."
if [ -z "$SMOKE_ADMIN_EMAIL" ]; then
  echo "❌ ERROR: SMOKE_ADMIN_EMAIL not set!"
  export SMOKE_ADMIN_EMAIL="admin@zena.local"
  echo "  → Set to default: admin@zena.local"
fi

if [ -z "$SMOKE_ADMIN_PASSWORD" ]; then
  echo "❌ ERROR: SMOKE_ADMIN_PASSWORD not set!"
  export SMOKE_ADMIN_PASSWORD="password"
  echo "  → Set to default: password"
fi

# Step 4: Copy env file
echo "📋 Copying .env.example to .env..."
cp .env.example .env

# Step 5: Generate key
echo "🔑 Generating application key..."
php artisan key:generate

# Step 6: Test database connection (if MySQL available)
echo "🗄️  Testing database connection..."
if command -v mysql &> /dev/null; then
  echo "  → MySQL client found"
else
  echo "  ⚠️  MySQL client not found, skipping"
fi

echo "✅ Debug checks completed!"
EOF

chmod +x debug-workflow.sh
./debug-workflow.sh
```

---

## 🚨 **Các Error Messages Phổ Biến**

### **Error 1: Missing .env.example**
```
cp: cannot stat '.env.example': No such file or directory
Error: Process completed with exit code 1.
```
**Fix:** Tạo file `.env.example`

### **Error 2: MySQL Connection Failed**
```
ERROR 2002 (HY000): Can't connect to MySQL server on '127.0.0.1' (111)
```
**Fix:** Đảm bảo MySQL service ready trước khi test

### **Error 3: Secrets Not Set**
```
SMOKE_ADMIN_EMAIL: 
SMOKE_ADMIN_PASSWORD: 
```
**Fix:** Set secrets trong GitHub Settings

### **Error 4: Server Not Ready**
```
Error: page.goto: net::ERR_CONNECTION_REFUSED at http://127.0.0.1:8000
```
**Fix:** Đảm bảo Laravel server start trước khi tests chạy

---

## ✅ **Next Steps Sau Khi Xác Định Step Fail**

1. **Nếu Step 7 fail (Copy env):**
   - Tạo file `.env.example`
   - Hoặc sửa workflow để không cần copy

2. **Nếu Step 10 fail (MySQL):**
   - Add wait step cho MySQL
   - Kiểm tra MySQL service health

3. **Nếu Step 14 fail (Secrets):**
   - Set secrets trong GitHub Settings
   - Verify secrets match với seeder

4. **Nếu Step 15 fail (Tests):**
   - Check server logs
   - Verify tests có thể chạy local không
   - Check authentication credentials

---

**Tạo ngày:** $(date)  
**Workflow:** `.github/workflows/e2e-smoke-debug.yml`

