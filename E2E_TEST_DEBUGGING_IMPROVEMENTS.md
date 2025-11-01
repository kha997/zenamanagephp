# 🔍 E2E Test Debugging Improvements

## 📋 **Vấn Đề**

Tests đang fail với timeout khi tìm `#email` selector:
```
TimeoutError: page.fill: Timeout 5000ms exceeded.
Call log:
  - waiting for locator('#email')
```

**Nguyên nhân có thể:**
1. Server chưa start kịp hoặc không accessible
2. Login page không load được hoặc redirect
3. Timeout quá ngắn cho CI environment
4. Selector không tồn tại trên page

---

## ✅ **Fixes Đã Áp Dụng**

### **1. Auth Helper Improvements** (`tests/E2E/helpers/auth.ts`)

#### **a. Increased Timeouts:**
- Email selector wait: 5000ms → **20000ms** trong CI
- Login button wait: **10000ms**
- User menu wait: 10000ms → **20000ms**

#### **b. Better Error Handling:**
- Wrapped trong try/catch để capture errors
- **Screenshot on error** - tự động chụp screenshot khi fail
- **Debug logging** - log URL, title, page content

#### **c. Debug Information:**
Khi `#email` không tìm thấy, helper sẽ log:
- Current page URL
- Page title
- Check if page contains 'email' text
- **List all input fields** với id, name, type, placeholder

#### **d. Navigation Strategy:**
- Changed `waitUntil: 'networkidle'` → `'domcontentloaded'` (faster)
- Increased navigation timeout: 30000ms

---

### **2. Playwright Config Improvements** (`playwright.config.ts`)

#### **CI-Specific Timeouts:**
```typescript
actionTimeout: process.env.CI ? 20000 : 5000,
navigationTimeout: process.env.CI ? 30000 : 15000,
```

- **Local:** Fast timeouts (5s action, 15s navigation)
- **CI:** Extended timeouts (20s action, 30s navigation)

---

### **3. Workflow Improvements** (`.github/workflows/e2e-smoke-debug.yml`)

#### **Server Verification:**
- Improved server check step
- Keep server running for Playwright to use
- Better error messages with server logs

#### **Environment Variables:**
- Set `CI=true` explicitly
- Ensure secrets are passed correctly

---

## 🔍 **Debugging Information**

Khi test fail, bạn sẽ thấy:

### **1. Console Logs:**
```
[Auth Helper] Navigated to: http://127.0.0.1:8000/login, Title: Login - ZenaManage
[Auth Helper] #email selector not found on page: http://127.0.0.1:8000/login
[Auth Helper] Page title: Login - ZenaManage
[Auth Helper] Page contains 'email': true
[Auth Helper] Input fields found: [
  { "id": "email-input", "name": "email", "type": "email", "placeholder": "Email address" },
  ...
]
```

### **2. Screenshots:**
- Tự động chụp screenshot khi fail
- Saved to: `test-results/login-error-{timestamp}.png`
- Full page screenshot để xem toàn cảnh

### **3. Page Content Analysis:**
Helper sẽ list tất cả input fields trên page, giúp:
- Verify selectors có đúng không
- Find alternative selectors
- Understand page structure

---

## 🎯 **Next Steps When Debugging**

### **Step 1: Check Screenshot**
- Xem screenshot trong `test-results/` folder
- Verify page có load được không
- Check có error messages trên page không

### **Step 2: Check Console Logs**
- Xem `[Auth Helper]` logs
- Verify page URL và title
- Check input fields list

### **3. Verify Server**
- Check workflow logs cho "Verify Laravel server can respond" step
- Verify server logs không có errors
- Check server responds với curl manually

### **4. Check Selectors**
Nếu `#email` không tồn tại:
- Xem input fields list trong logs
- Update selector nếu cần (có thể dùng `input[name="email"]`)
- Verify login.blade.php có `id="email"`

---

## 📊 **Expected Behavior**

### **Success Flow:**
1. ✅ Navigate to `/login` → URL: `http://127.0.0.1:8000/login`
2. ✅ Wait for `#email` selector (max 20s)
3. ✅ Fill email và password
4. ✅ Wait for `#loginButton` (max 10s)
5. ✅ Click button và wait for user menu (max 20s)
6. ✅ Test passes

### **Failure Flow (với debugging):**
1. ⚠️ Navigate to `/login`
2. ❌ Timeout waiting for `#email`
3. 📸 Screenshot captured
4. 📋 Debug info logged (URL, title, inputs)
5. 🔍 Error thrown với context

---

## 🔧 **Troubleshooting**

### **Issue: Selector Not Found**
**Symptom:** `#email` not found after 20s
**Possible Causes:**
- Server not running
- Page redirecting somewhere else
- Different page structure than expected
- JavaScript errors preventing page load

**Solution:**
1. Check screenshot để xem page actual state
2. Check input fields list để find correct selector
3. Update selector nếu cần

### **Issue: Server Not Responding**
**Symptom:** Navigation timeout
**Possible Causes:**
- Server failed to start
- Port conflict
- Database connection issues

**Solution:**
1. Check workflow logs cho server verification step
2. Check server logs trong workflow
3. Verify MySQL service is running

### **Issue: Page Redirects**
**Symptom:** URL is not `/login` after navigation
**Possible Causes:**
- Already logged in (redirect to dashboard)
- Authentication middleware redirecting
- CSRF token issues

**Solution:**
1. Check final URL trong logs
2. Verify authentication state
3. Clear session/cookies nếu cần

---

## 📝 **Files Modified**

1. ✅ `tests/E2E/helpers/auth.ts` - Comprehensive debugging và error handling
2. ✅ `playwright.config.ts` - CI-specific timeouts
3. ✅ `.github/workflows/e2e-smoke-debug.yml` - Server verification improvements

---

## ✅ **Benefits**

1. **Better Visibility:** Debug logs giúp hiểu rõ vấn đề
2. **Faster Debugging:** Screenshots và input fields list giúp debug nhanh
3. **CI Compatibility:** Extended timeouts for slower CI environments
4. **Graceful Failures:** Better error messages với context

---

**Workflow sẽ tự động chạy lại với code mới có debugging!**

Kiểm tra tại: https://github.com/kha997/zenamanagephp/actions/workflows/e2e-smoke-debug.yml

