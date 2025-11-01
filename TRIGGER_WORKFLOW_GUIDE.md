# 🚀 Hướng Dẫn Trigger Workflow E2E Smoke Tests Debug

## ✅ **Đã Push Changes**

Các changes đã được commit và push lên branch `feature/repo-cleanup`:
- Commit: `d6bf9508` - "fix: resolve database connection mismatch in E2E workflow"
- Branch: `feature/repo-cleanup`
- Remote: `origin`

## 🔄 **Workflow Sẽ Tự Động Trigger**

Workflow `E2E Smoke Tests Debug` sẽ **tự động chạy** vì:
- ✅ Push event lên branch `feature/repo-cleanup` đã trigger workflow
- ✅ Workflow file: `.github/workflows/e2e-smoke-debug.yml`

---

## 📍 **Cách Kiểm Tra Workflow Status**

### **1. Trên GitHub UI:**

1. Vào repository: `https://github.com/kha997/zenamanagephp`
2. Click tab **Actions**
3. Tìm workflow **"E2E Smoke Tests Debug"**
4. Click vào workflow run mới nhất để xem logs

**Link trực tiếp:**
```
https://github.com/kha997/zenamanagephp/actions/workflows/e2e-smoke-debug.yml
```

### **2. Dùng GitHub CLI (nếu đã cài):**

```bash
# Xem danh sách workflow runs
gh run list --workflow=e2e-smoke-debug.yml --limit 5

# Xem workflow run mới nhất
gh run view --web

# Watch workflow đang chạy
gh run watch
```

---

## 🔄 **Trigger Manual (nếu cần)**

### **Cách 1: Trên GitHub UI**

1. Vào **Actions** tab
2. Chọn workflow **"E2E Smoke Tests Debug"**
3. Click **"Run workflow"** button (bên phải)
4. Chọn branch: `feature/repo-cleanup`
5. Click **"Run workflow"**

### **Cách 2: Dùng GitHub CLI**

```bash
# Trigger workflow manual
gh workflow run e2e-smoke-debug.yml --ref feature/repo-cleanup
```

### **Cách 3: Push một commit trống**

```bash
# Create empty commit và push
git commit --allow-empty -m "chore: trigger E2E smoke tests workflow"
git push origin feature/repo-cleanup
```

---

## 📊 **Theo Dõi Workflow**

### **Expected Timeline:**
- **Setup:** ~2-3 phút (dependencies, MySQL, PHP)
- **Migrations:** ~1-2 phút
- **Tests:** ~2-3 phút
- **Total:** ~5-8 phút

### **Checkpoints:**

✅ **Step 1-6:** Setup (Node, PHP, dependencies)
- Should complete successfully

✅ **Step 7:** Check for .env.example file
- Should auto-create .env nếu file không tồn tại

✅ **Step 8-9:** Generate key & Configure DB
- Should configure MySQL correctly

✅ **Step 10-11:** Wait for MySQL & Test connection
- Should connect to MySQL successfully

✅ **Step 12-13:** Create & Seed database
- Should run migrations với MySQL (không phải SQLite!)
- **Đây là bước quan trọng** - check logs để verify dùng MySQL

✅ **Step 14:** Test environment variables
- Should show warnings nếu secrets chưa set (sẽ dùng defaults)

✅ **Step 15:** Verify Laravel server can start
- Should verify server starts successfully

✅ **Step 16:** Run smoke tests
- Should run tests với MySQL database

---

## 🔍 **Logs Quan Trọng Cần Kiểm Tra**

### **1. Database Connection Log:**
Tìm trong logs:
```
🧹 Clearing cached configuration before E2E run...
   📊 DB Connection: mysql  ← Phải là "mysql", không phải "sqlite"!
   🗄️  MySQL Host: 127.0.0.1:3306
   📂 Database: zenamanage_e2e
```

### **2. Migration Logs:**
Check xem migrations chạy với MySQL:
```
INFO  Running migrations.
2025_10_07_021725_add_created_by_updated_by_to_documents_table ... OK
```

**Nếu thấy lỗi:**
```
SQLSTATE[HY000]: General error: 1 no such table: information_schema.KEY_COLUMN_USAGE
```

→ Có nghĩa là vẫn đang dùng SQLite, cần kiểm tra lại global-setup.ts

### **3. Global Setup Log:**
```
⏭️  Skipping migrations (already run by workflow)
```

→ Nếu thấy message này → Đúng! Migrations đã chạy trong workflow.

---

## 🚨 **Nếu Workflow Vẫn Fail**

### **Check 1: MySQL Connection**
- Verify MySQL service start thành công
- Check "Wait for MySQL to be ready" step

### **Check 2: .env File**
- Verify .env file được tạo với MySQL config
- Check "Check for .env.example file" step

### **Check 3: Global Setup**
- Verify global setup đọc đúng .env file
- Check log: `📊 DB Connection: mysql`

### **Check 4: Secrets**
- Verify secrets được set (hoặc sử dụng defaults)
- Check "Test environment variables" step

---

## ✅ **Expected Success Indicators**

Khi workflow pass, bạn sẽ thấy:

1. ✅ All steps có icon thành công
2. ✅ Logs show: `📊 DB Connection: mysql`
3. ✅ Migrations chạy không có errors
4. ✅ Tests pass
5. ✅ Summary: "All jobs completed successfully"

---

## 📝 **Files Đã Thay Đổi**

1. ✅ `.github/workflows/e2e-smoke-debug.yml` - Improved error handling
2. ✅ `tests/E2E/setup/global-setup.ts` - Fix DB config reading
3. ✅ `FIX_DATABASE_CONNECTION_E2E_WORKFLOW.md` - Documentation
4. ✅ `E2E_DEBUG_WORKFLOW_IMPROVEMENTS.md` - Summary
5. ✅ `.github/workflows/e2e-smoke-debug-analyzer.md` - Debug guide

---

**Workflow đã được trigger!** 🎉

Kiểm tra tại: https://github.com/kha997/zenamanagephp/actions/workflows/e2e-smoke-debug.yml

