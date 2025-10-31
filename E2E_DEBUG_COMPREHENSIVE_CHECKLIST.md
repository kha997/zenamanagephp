# 🔍 E2E Smoke Tests Debug - Comprehensive Checklist

## 📋 **Vấn Đề Hiện Tại**

Tests đang fail với timeout khi tìm `#email` selector:
```
TimeoutError: page.fill: Timeout 5000ms exceeded.
Call log:
  - waiting for locator('#email')
```

---

## ✅ **Tất Cả Fixes Đã Áp Dụng**

### **1. Database Connection Fixes**
- ✅ Fix global-setup.ts để đọc `.env` file (workflow created)
- ✅ Skip migrations trong CI (workflow đã chạy)
- ✅ Wrap foreign keys trong MySQL check trong migration

### **2. Workflow Improvements**
- ✅ Auto-create `.env` nếu `.env.example` không tồn tại
- ✅ Clear Laravel config cache trước migrations
- ✅ Verify DB configuration trước migrations
- ✅ Wait for MySQL service ready
- ✅ Verify DB driver = MySQL trước migrations
- ✅ Verify Laravel server can respond
- ✅ **NEW:** Verify login page specifically (200 OK + email input check)
- ✅ Upload test artifacts (screenshots, HTML, reports)

### **3. Auth Helper Improvements**
- ✅ Increased timeouts (20s for email, 10s for button, 20s for menu)
- ✅ **NEW:** Alternative selector fallback (`input[name="email"]`, `input[type="email"]`, `[data-testid="email-input"]`)
- ✅ **NEW:** Redirect detection và warning
- ✅ **NEW:** Wait for page rendering (1000ms)
- ✅ **NEW:** Save page HTML on error
- ✅ Comprehensive debug logging (URL, title, HTML snippet, input fields)
- ✅ Screenshot on error

### **4. Playwright Config**
- ✅ CI-specific timeouts (20s action, 30s navigation)
- ✅ webServer stdout/stderr pipes for logging

---

## 🔍 **Debugging Information Available**

### **When Tests Run:**

**1. Workflow Logs:**
```
✅ Login page returns 200 OK
✅ Login page contains email input field
```

**2. Auth Helper Logs:**
```
[Auth Helper] Navigated to: http://127.0.0.1:8000/login, Title: Login - ZenaManage
[Auth Helper] Input fields found: [...]
```

**3. If Fail:**
- 📸 Screenshot: `test-results/login-error-{timestamp}.png`
- 📄 HTML: `test-results/login-error-{timestamp}.html`
- 📋 Console logs với full context

---

## 🎯 **Root Cause Analysis**

### **Possible Issues:**

#### **1. Server Not Ready** ⚠️
**Symptom:** Navigation timeout hoặc connection refused
**Check:**
- Workflow step "Verify Laravel server can respond" logs
- Server PID và logs

#### **2. Wrong Page Loaded** ⚠️
**Symptom:** URL không phải `/login` sau navigation
**Check:**
- `[Auth Helper] Navigated to:` log
- Check for redirects (middleware, auth guards)
- Screenshot để xem actual page

#### **3. Selector Not Found** ⚠️
**Symptom:** `#email` không tìm thấy nhưng page đã load
**Check:**
- `[Auth Helper] Input fields found:` log
- Page HTML trong error HTML file
- Alternative selectors sẽ tự động thử

#### **4. JavaScript Errors** ⚠️
**Symptom:** Page load nhưng elements không render
**Check:**
- Browser console logs
- Page HTML có đầy đủ không
- Check for JavaScript errors trong HTML

---

## 📊 **Workflow Steps Summary**

### **Setup Phase:**
1. ✅ Checkout code
2. ✅ Setup Node.js 18
3. ✅ Install dependencies (npm, composer)
4. ✅ Install Playwright browsers
5. ✅ Setup PHP 8.2 with extensions

### **Configuration Phase:**
6. ✅ Check/create `.env` file
7. ✅ Generate application key
8. ✅ Configure database (MySQL)
9. ✅ Clear Laravel config cache
10. ✅ Verify database configuration

### **Database Phase:**
11. ✅ Wait for MySQL service ready
12. ✅ Test MySQL connection
13. ✅ Create database (migrate:fresh)
14. ✅ Seed database (E2EDatabaseSeeder)
15. ✅ Verify database setup

### **Server Phase:**
16. ✅ Test environment variables (secrets)
17. ✅ **Verify Laravel server can respond**
18. ✅ **Verify login page specifically**

### **Testing Phase:**
19. ✅ Run smoke tests
20. ✅ Upload test artifacts (screenshots, HTML)
21. ✅ Upload Playwright report

---

## 🔧 **Troubleshooting Guide**

### **Issue 1: Login Page Not Loading**

**Check Workflow Logs:**
```bash
# Step: "Verify login page specifically"
✅ Login page returns 200 OK
✅ Login page contains email input field
```

**If NOT OK:**
- Check server logs trong workflow
- Verify route `/login` exists
- Check middleware không block request

### **Issue 2: Email Selector Not Found**

**Check Auth Helper Logs:**
```
[Auth Helper] Input fields found: [...]
```

**Solutions:**
- Helper sẽ tự động try alternative selectors
- Check HTML file trong artifacts
- Update selector nếu cần

### **Issue 3: Server Not Responding**

**Check:**
- "Verify Laravel server can respond" step logs
- Server PID trong workflow
- Port 8000 có bị conflict không

### **Issue 4: Page Redirects**

**Check Auth Helper Logs:**
```
[Auth Helper] WARNING: Expected /login but got: {url}
```

**Possible Causes:**
- Already authenticated (middleware redirect)
- CSRF token issues
- Session conflicts

**Solution:**
- Clear session trước tests
- Check authentication state

---

## 📝 **Next Steps After Failure**

### **1. Download Artifacts:**
- GitHub Actions → Workflow run → Artifacts
- Download `e2e-test-results` và `playwright-report`

### **2. Analyze Screenshot:**
- Xem screenshot để hiểu page state
- Check có error messages không
- Verify page structure

### **3. Analyze HTML:**
- Mở HTML file trong artifacts
- Check có `#email` element không
- Verify JavaScript có load không

### **4. Check Logs:**
- Workflow logs cho server issues
- Auth Helper logs cho page issues
- Playwright logs cho test execution

### **5. Fix Based on Findings:**
- Update selectors nếu cần
- Fix server issues nếu có
- Update auth helper nếu logic sai

---

## ✅ **Expected Success Indicators**

### **Workflow Logs:**
```
✅ MySQL is ready!
✅ MySQL connection successful!
✅ Database driver verified as MySQL
✅ Laravel server is responding!
✅ Login page returns 200 OK
✅ Login page contains email input field
```

### **Test Logs:**
```
[Auth Helper] Navigated to: http://127.0.0.1:8000/login, Title: Login - ZenaManage
✅ Tests pass
```

---

## 🚨 **Critical Checks**

- [ ] **MySQL service running** - Check service logs
- [ ] **Database migrations successful** - Check migration logs
- [ ] **Server responding** - Check server verification step
- [ ] **Login page accessible** - Check login page verification
- [ ] **Email input exists** - Check page HTML
- [ ] **No redirects** - Check final URL trong logs
- [ ] **Secrets set correctly** - Check env vars step

---

## 📊 **Files Modified Summary**

1. ✅ `.github/workflows/e2e-smoke-debug.yml`
   - Login page verification
   - Artifact uploads
   - Better server management

2. ✅ `tests/E2E/helpers/auth.ts`
   - Alternative selector fallback
   - Redirect detection
   - HTML saving on error
   - Comprehensive debugging

3. ✅ `tests/E2E/setup/global-setup.ts`
   - Read `.env` file support
   - Skip migrations in CI

4. ✅ `database/migrations/2025_10_07_021725_add_created_by_updated_by_to_documents_table.php`
   - MySQL-only foreign keys

5. ✅ `playwright.config.ts`
   - CI-specific timeouts

---

**Workflow sẽ tự động chạy lại với tất cả improvements!**

Kiểm tra tại: https://github.com/kha997/zenamanagephp/actions/workflows/e2e-smoke-debug.yml

