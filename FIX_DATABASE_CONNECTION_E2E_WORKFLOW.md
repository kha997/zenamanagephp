# ✅ Fix Database Connection Issue in E2E Workflow

## 🔍 **Vấn Đề**

Workflow `e2e-smoke-debug.yml` fail với lỗi:

```
SQLSTATE[HY000]: General error: 1 no such table: information_schema.KEY_COLUMN_USAGE
(Connection: sqlite, SQL: SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE...)
```

**Nguyên nhân:**
- Workflow đã config MySQL trong `.env` file
- Nhưng Playwright `global-setup.ts` chỉ đọc `.env.e2e` file (không tồn tại)
- Khi không tìm thấy `DB_CONNECTION`, global setup tự động set thành SQLite
- Migration chạy với SQLite nhưng cố query `information_schema` (chỉ có trong MySQL)
- → **Lỗi xảy ra**

---

## ✅ **Giải Pháp**

### **1. Fix Global Setup để Đọc .env File**

**File:** `tests/E2E/setup/global-setup.ts`

**Thay đổi:**
- ✅ Đọc cả `.env` file (workflow tạo) và `.env.e2e` file (local tests)
- ✅ Check `process.env` cho DB config (CI/workflow)
- ✅ Chỉ set SQLite default nếu KHÔNG có DB config nào được set
- ✅ Log DB connection type để debug dễ hơn

**Trước:**
```typescript
// Chỉ đọc .env.e2e
const envFileVars = parseEnvFile(ENV_FILE_PATH);
if (!envFileVars.DB_CONNECTION) {
  overrides.DB_CONNECTION = 'sqlite'; // ❌ Always override
}
```

**Sau:**
```typescript
// Đọc cả .env và .env.e2e
const envFileVars = parseEnvFile(envFilePath); // .env
const envE2EFileVars = parseEnvFile(ENV_FILE_PATH); // .env.e2e

// Chỉ override nếu KHÔNG có config nào
if (!mergedEnvVars.DB_CONNECTION && !process.env.DB_CONNECTION) {
  console.log('⚠️  No DB_CONNECTION found, defaulting to SQLite');
  overrides.DB_CONNECTION = 'sqlite';
} else {
  // ✅ Use config từ .env hoặc process.env
  overrides.DB_CONNECTION = mergedEnvVars.DB_CONNECTION || process.env.DB_CONNECTION;
  // ... copy all DB config vars
}
```

---

### **2. Skip Migrations trong Global Setup khi đang trong CI**

**Thay đổi:**
- ✅ Trong CI, workflow đã chạy migrations rồi
- ✅ Global setup chỉ chạy migrations nếu:
  - Local development (không phải CI) HOẶC
  - `E2E_RUN_MIGRATIONS=true` được set

**Code:**
```typescript
const shouldRunMigrations = !process.env.CI || process.env.E2E_RUN_MIGRATIONS === 'true';

if (shouldRunMigrations) {
  runArtisan('migrate:fresh', artisanEnv);
  runArtisan('db:seed --class="Database\\Seeders\\E2EDatabaseSeeder"', artisanEnv);
} else {
  console.log('⏭️  Skipping migrations (already run by workflow)');
}
```

---

## 📋 **Cách Hoạt Động Sau Khi Fix**

### **Trong CI/Workflow:**
1. Workflow tạo `.env` với MySQL config
2. Workflow chạy `migrate:fresh` và `db:seed`
3. Playwright tests chạy → global setup chạy
4. Global setup đọc `.env` → thấy MySQL config
5. Global setup SKIP migrations (vì `process.env.CI=true`)
6. Tests chạy với MySQL database ✅

### **Trong Local Development:**
1. Developer có `.env.e2e` với SQLite config
2. Hoặc không có file → default SQLite
3. Global setup chạy → detect SQLite config
4. Global setup chạy migrations và seeding
5. Tests chạy với SQLite database ✅

---

## 🔍 **Debugging**

Global setup giờ sẽ log DB connection info:

```
🧹 Clearing cached configuration before E2E run...
   📊 DB Connection: mysql
   🗄️  MySQL Host: 127.0.0.1:3306
   📂 Database: zenamanage_e2e
```

Hoặc nếu SQLite:
```
🧹 Clearing cached configuration before E2E run...
   📊 DB Connection: sqlite
   📂 SQLite DB: /path/to/database/database.sqlite
```

---

## ✅ **Test Plan**

### **1. Test trong CI:**
```bash
# Workflow sẽ:
1. Create .env with MySQL config ✅
2. Run migrations with MySQL ✅
3. Global setup reads .env → MySQL ✅
4. Global setup skips migrations ✅
5. Tests run with MySQL ✅
```

### **2. Test Local:**
```bash
# Developer runs:
npm run test:e2e:smoke:headed

# Should:
1. Global setup reads .env.e2e → SQLite ✅
2. Global setup runs migrations ✅
3. Tests run with SQLite ✅
```

---

## 🚨 **Potential Issues & Solutions**

### **Issue 1: Global Setup chạy migrations 2 lần**
**Solution:** ✅ Đã fix - skip migrations trong CI

### **Issue 2: Migration dùng SQLite syntax trên MySQL**
**Solution:** ✅ Migration đã có `SqliteCompatibleMigration` trait

### **Issue 3: .env.e2e override .env config**
**Solution:** ✅ Merge order: `.env` first, `.env.e2e` overrides (cho local dev)

---

## 📝 **Files Changed**

1. ✅ `tests/E2E/setup/global-setup.ts`
   - Fix `buildArtisanEnv()` to read `.env` file
   - Fix to not override DB config if already set
   - Skip migrations in CI

---

## 🎯 **Expected Result**

✅ **Workflow sẽ pass vì:**
- Global setup đọc đúng MySQL config từ `.env`
- Migrations chạy với MySQL (không phải SQLite)
- Không có lỗi `information_schema.KEY_COLUMN_USAGE` nữa
- Tests chạy với MySQL database

---

**Ngày fix:** $(date)  
**Issue:** Database connection mismatch between workflow and global setup  
**Status:** ✅ Fixed

