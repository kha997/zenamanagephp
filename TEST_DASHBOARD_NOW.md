# 🧪 Manual Testing Guide - Dashboard

## ✅ STATUS: Dashboard đã rebuild xong, sẵn sàng test

## 🎯 STEPS TO TEST (5-10 minutes)

### 1. Login
```
URL: https://manager.zena.com.vn/login
Username: admin@zena.test
Password: password
```

### 2. Navigate to Dashboard
```
https://manager.zena.com.vn/app/dashboard
```

### 3. Verify Checklist (đơn giản hóa)

#### ✅ MUST CHECK (Critical)
- [ ] Page loads không error
- [ ] Header hiển thị đúng
- [ ] Primary Navigator hiển thị đúng
- [ ] KPIs có data (không null/NaN)
- [ ] Projects widget có data
- [ ] No console errors (F12 → Console)

#### 📱 Responsive
- [ ] Desktop: 1920x1080 OK
- [ ] Tablet: 768x1024 OK  
- [ ] Mobile: 375x667 OK

#### 🎨 UI Components
- [ ] Quick Actions buttons work
- [ ] Alert bar dismiss works
- [ ] Charts render (nếu có)

### 4. Report
Nếu có lỗi → screenshot và mô tả ngắn gọn

---

## 📊 KẾT QUẢ

**Date**: _______________
**Tester**: _______________  
**Status**: ⏳ Pending

**Issues**:
1. ___________________________
2. ___________________________

**Screenshots**: Attach here

---

## 🚀 NEXT STEPS (Sau khi test xong)

### Option A: Dashboard OK → Start Projects Rebuild
1. Create API endpoints for Projects
2. Rebuild Projects view với Smart Filters
3. Test Projects page

### Option B: Dashboard có issues → Fix Dashboard
1. Debug issues
2. Fix và retest
3. Lock Dashboard behavior

---

**NOTE**: Nếu Dashboard works tốt, tiếp tục với Projects rebuild.
Nếu có issues, fix trước khi move forward.

