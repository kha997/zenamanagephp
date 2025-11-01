# 🔍 E2E Smoke Tests Debug - Phân Tích Nguyên Nhân Lỗi

## 📋 **Tổng Quan**

Workflow `E2E Smoke Tests Debug` đã fail sau **5 phút 32 giây**. Tất cả jobs đã thất bại.

## 🎯 **Nguyên Nhân Có Thể**

### **1. ❌ THIẾU FILE `.env.example`** ⚠️ **RẤT CÓ THỂ**

**Vấn đề:**
```yaml
- name: Copy environment file
  run: cp .env.example .env
```

**Nguyên nhân:** File `.env.example` không tồn tại trong repository (đã verify bằng glob_file_search).

**Hậu quả:**
- Step `cp .env.example .env` sẽ fail
- Tất cả các step sau sẽ fail vì không có file `.env`
- PHP artisan commands sẽ không có config

**Giải pháp:**
- Tạo file `.env.example` với cấu hình cơ bản
- Hoặc tạo file `.env` trực tiếp trong workflow

---

### **2. ❌ CONFLICT GIỮA WORKFLOW VÀ GLOBAL SETUP** 🔄 **CÓ THỂ**

**Vấn đề:**
- Workflow tự chạy `migrate:fresh` và `db:seed --class=E2EDatabaseSeeder` (lines 73-78)
- Playwright `globalSetup` cũng chạy `migrate:fresh` và seed E2EDatabaseSeeder (lines 177-189 trong `tests/E2E/setup/global-setup.ts`)

**Nguyên nhân:**
- Double execution có thể gây race condition
- Database có thể bị reset 2 lần
- Có thể conflict với webServer start

**Hậu quả:**
- Tests chạy trước khi database được setup hoàn toàn
- Database connection errors
- Migration errors

**Giải pháp:**
- Remove duplicate migration/seeding từ workflow HOẶC
- Disable globalSetup cho smoke tests debug workflow

---

### **3. ❌ THIẾU GITHUB SECRETS** 🔐 **RẤT CÓ THỂ**

**Vấn đề:**
```yaml
- name: Test environment variables
  run: |
    echo "SMOKE_ADMIN_EMAIL: ${{ secrets.SMOKE_ADMIN_EMAIL }}"
    echo "SMOKE_ADMIN_PASSWORD: ${{ secrets.SMOKE_ADMIN_PASSWORD }}"
```

**Nguyên nhân:**
- Secrets `SMOKE_ADMIN_EMAIL` và `SMOKE_ADMIN_PASSWORD` có thể chưa được set trong GitHub repository settings
- Nếu secrets không tồn tại, giá trị sẽ là empty string

**Hậu quả:**
- Tests sẽ không thể login được
- `auth.login(process.env.SMOKE_ADMIN_EMAIL!, ...)` sẽ fail vì empty email
- Authentication tests fail

**Giải pháp:**
- Verify secrets trong GitHub Settings → Secrets and variables → Actions
- Set values:
  - `SMOKE_ADMIN_EMAIL`: email của admin user (theo E2EDatabaseSeeder là `admin@zena.local`)
  - `SMOKE_ADMIN_PASSWORD`: password của admin user (theo E2EDatabaseSeeder là `password`)

---

### **4. ❌ MISMATCH GIỮA SEEDER VÀ ENV VARS** 🔀 **CÓ THỂ**

**Vấn đề:**
- `E2EDatabaseSeeder` tạo user với:
  - Email: `admin@zena.local`
  - Password: `password`
- Nhưng tests dùng env vars:
  - `process.env.SMOKE_ADMIN_EMAIL`
  - `process.env.SMOKE_ADMIN_PASSWORD`

**Nguyên nhân:**
- Nếu secrets có giá trị khác với seeder, login sẽ fail
- Nếu secrets empty, tests sẽ fail

**Hậu quả:**
- Authentication tests fail
- Cannot login với credentials từ env vars

**Giải pháp:**
- Đảm bảo secrets match với seeder:
  ```
  SMOKE_ADMIN_EMAIL=admin@zena.local
  SMOKE_ADMIN_PASSWORD=password
  ```

---

### **5. ❌ PHP/LARAVEL SERVER KHÔNG START** 🚀 **CÓ THỂ**

**Vấn đề:**
```typescript
webServer: {
  command: 'php artisan serve --host=127.0.0.1 --port=8000',
  url: 'http://127.0.0.1:8000',
  reuseExistingServer: !process.env.CI,
  timeout: 120 * 1000,
}
```

**Nguyên nhân:**
- Server có thể không start kịp trong CI
- Port 8000 có thể bị occupied
- PHP artisan serve có thể fail nếu:
  - Missing dependencies
  - Database connection errors
  - Config errors
  - .env file missing

**Hậu quả:**
- Tests sẽ fail vì không thể connect đến baseURL
- Timeout errors
- Connection refused errors

**Giải pháp:**
- Kiểm tra logs của webServer step
- Đảm bảo server start trước khi tests chạy
- Verify `.env` file có đầy đủ config

---

### **6. ❌ MYSQL SERVICE KHÔNG READY** 🗄️ **CÓ THỂ**

**Vấn đề:**
```yaml
services:
  mysql:
    image: mysql:8.0
    options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3
```

**Nguyên nhân:**
- MySQL service có thể chưa ready khi workflow chạy migrations
- Health check có thể fail
- Connection timeout

**Hậu quả:**
- `migrate:fresh` sẽ fail
- Database connection errors
- Seeder fails

**Giải pháp:**
- Đảm bảo MySQL ready trước khi chạy migrations:
  ```yaml
  - name: Wait for MySQL
    run: |
      until mysql -h 127.0.0.1 -u e2e_user -pe2e_password -e "SELECT 1"; do
        sleep 2
      done
  ```

---

### **7. ❌ MISSING DEPENDENCIES** 📦 **CÓ THỂ**

**Nguyên nhân:**
- `npm ci` có thể fail nếu `package-lock.json` out of sync
- `composer install` có thể fail nếu missing PHP extensions
- Playwright browsers có thể không install được

**Hậu quả:**
- Tests không thể chạy
- Missing modules errors

**Giải pháp:**
- Verify dependencies install thành công
- Check `package-lock.json` và `composer.lock` có commit

---

### **8. ❌ TEST FILES KHÔNG TỒN TẠI HOẶC CÓ LỖI** 🧪 **ÍT CÓ THỂ**

**Nguyên nhân:**
- Test files `*-minimal.spec.ts` có thể có syntax errors
- `MinimalAuthHelper` có thể có bugs
- Selectors có thể không match với UI

**Hậu quả:**
- Tests fail với assertion errors
- Timeout errors
- Element not found errors

**Giải pháp:**
- Verify test files compile successfully
- Run tests locally để verify

---

## 🔧 **CÁCH DEBUG**

### **Step 1: Kiểm tra GitHub Actions Logs**
1. Vào GitHub Actions tab
2. Click vào failed workflow run
3. Xem logs của từng step để tìm step đầu tiên fail

### **Step 2: Verify Secrets**
```bash
# Check if secrets are set (in GitHub Settings)
SMOKE_ADMIN_EMAIL should be: admin@zena.local
SMOKE_ADMIN_PASSWORD should be: password
```

### **Step 3: Verify Files**
```bash
# Check if .env.example exists
ls -la .env.example

# If not, create it:
cp .env .env.example  # (adjust as needed)
```

### **Step 4: Test Locally**
```bash
# Setup
cp .env.example .env
php artisan key:generate

# Run the same commands as workflow
composer install
npm ci
npx playwright install --with-deps

# Setup database
php artisan migrate:fresh
php artisan db:seed --class=E2EDatabaseSeeder

# Run tests
export SMOKE_ADMIN_EMAIL="admin@zena.local"
export SMOKE_ADMIN_PASSWORD="password"
npm run test:e2e:smoke
```

---

## ✅ **GIẢI PHÁP ĐƯỢC ĐỀ XUẤT**

### **Priority 1: CRITICAL**
1. **Tạo file `.env.example`** hoặc sửa workflow để không cần nó
2. **Verify và set GitHub Secrets**:
   - `SMOKE_ADMIN_EMAIL=admin@zena.local`
   - `SMOKE_ADMIN_PASSWORD=password`

### **Priority 2: HIGH**
3. **Fix duplicate migration/seeding**: Remove từ workflow hoặc disable globalSetup
4. **Add wait step** cho MySQL service ready

### **Priority 3: MEDIUM**
5. **Add better error handling** trong workflow
6. **Add debug steps** để verify mỗi step thành công

---

## 📝 **CHECKLIST TRƯỚC KHI CHẠY LẠI**

- [ ] File `.env.example` tồn tại
- [ ] GitHub Secrets được set đúng
- [ ] Secrets match với E2EDatabaseSeeder credentials
- [ ] No duplicate migrations/seeding
- [ ] MySQL service health check works
- [ ] PHP server có thể start
- [ ] Test files compile successfully
- [ ] Dependencies install thành công

---

**Ngày phân tích:** $(date)  
**Workflow:** `.github/workflows/e2e-smoke-debug.yml`  
**Status:** 🔴 FAILED (5m 32s)

