# Header Admin Routes Test Checklist

**Ngày:** 2025-01-XX  
**Mục tiêu:** Test header trên admin routes (`/admin/*`)

---

## ✅ Code Verification

### 1. Admin Layout Setup
- [x] `admin.blade.php` đã dùng `<x-shared.header-wrapper variant="admin">`
- [x] Props được truyền đúng: user, tenant, navigation, notifications, unreadCount, alertCount, theme, breadcrumbs

### 2. HeaderService Navigation
- [x] `HeaderService::getNavigation($user, 'admin')` trả về admin navigation items
- [x] Admin navigation bao gồm: Dashboard, Users, Tenants, Projects, Security, Alerts, Activities, Analytics, Maintenance, Settings

### 3. Header Wrapper Logic
- [x] `variant="admin"` được xử lý đúng
- [x] Admin routes được resolve đúng:
  - Logout: `admin.logout` (fallback `logout`)
  - Dashboard: `admin.dashboard`
  - Settings: `admin.settings.index` hoặc `admin.settings`
  - Profile: `admin.profile`
  - Alerts: `admin.alerts.index`
- [x] Alert badge hiển thị khi `alertCount > 0` và `variant="admin"`

### 4. Routes Verification
- [x] `admin.dashboard` - ✅ Exists
- [x] `admin.profile` - ✅ Exists
- [x] `admin.settings` - ✅ Exists
- [x] `admin.settings.index` - ✅ Exists
- [x] `admin.alerts.index` - ✅ Exists
- [x] `admin.users.index` - ✅ Exists
- [x] `admin.tenants.index` - ✅ Exists
- [x] `admin.projects.index` - ✅ Exists
- [x] `admin.security.index` - ✅ Exists
- [x] `admin.activities.index` - ✅ Exists
- [x] `admin.analytics.index` - ✅ Exists
- [x] `admin.maintenance.index` - ✅ Exists

---

## 🧪 Browser Test Checklist

### Test 1: Admin Dashboard (`/admin/dashboard`)
- [ ] Header hiển thị đúng
- [ ] Logo hiển thị và link đến `admin.dashboard`
- [ ] Navigation menu hiển thị các items: Dashboard, Users, Tenants, Projects, Security, Alerts, Activities, Analytics, Maintenance, Settings
- [ ] Active state: Dashboard item được highlight
- [ ] Notifications bell icon hiển thị
- [ ] User menu hiển thị (avatar/initial)
- [ ] Alerts badge hiển thị (nếu có)
- [ ] Mobile menu button hiển thị trên mobile
- [ ] Breadcrumbs hiển thị (nếu có)

### Test 2: Admin Users (`/admin/users`)
- [ ] Header hiển thị đúng
- [ ] Active state: Users item được highlight
- [ ] Click vào Users trong nav → navigate đến `/admin/users`
- [ ] Click vào logo → navigate về `/admin/dashboard`

### Test 3: Admin Settings (`/admin/settings`)
- [ ] Header hiển thị đúng
- [ ] Active state: Settings item được highlight
- [ ] Click vào Settings trong nav → navigate đến `/admin/settings`
- [ ] Click vào Settings trong user menu → navigate đến `/admin/settings`

### Test 4: Notifications Dropdown
- [ ] Click vào notifications bell → dropdown mở
- [ ] Dropdown hiển thị danh sách notifications
- [ ] Unread count badge hiển thị đúng
- [ ] "View all notifications" link hoạt động (nếu route exists)
- [ ] Click outside → dropdown đóng
- [ ] Press Escape → dropdown đóng

### Test 5: User Menu
- [ ] Click vào user avatar → menu mở
- [ ] Menu hiển thị: User name, email, Settings, Profile, Sign out
- [ ] Click Settings → navigate đến `/admin/settings`
- [ ] Click Profile → navigate đến `/admin/profile`
- [ ] Click Sign out → logout và redirect
- [ ] Click outside → menu đóng
- [ ] Press Escape → menu đóng

### Test 6: Mobile Menu
- [ ] Trên mobile (< 1024px), hamburger button hiển thị
- [ ] Click hamburger → mobile menu mở
- [ ] Mobile menu hiển thị tất cả navigation items
- [ ] Click navigation item → navigate và menu đóng
- [ ] Click outside → menu đóng
- [ ] Press Escape → menu đóng

### Test 7: Alerts Badge (Admin Only)
- [ ] Nếu `alertCount > 0` → badge hiển thị trên alerts icon
- [ ] Click alerts icon → navigate đến `/admin/alerts`
- [ ] Badge hiển thị số đúng

### Test 8: Responsive Design
- [ ] Desktop (> 1024px): Full navigation menu hiển thị
- [ ] Tablet (768px - 1024px): Hamburger menu hiển thị
- [ ] Mobile (< 768px): Hamburger menu hiển thị, layout compact
- [ ] Tất cả elements responsive và không bị overlap

### Test 9: Dark Mode
- [ ] Header hỗ trợ dark mode classes
- [ ] Colors adapt correctly trong dark mode
- [ ] Borders và shadows visible trong dark mode

### Test 10: Accessibility
- [ ] ARIA labels đúng
- [ ] Keyboard navigation hoạt động (Tab, Enter, Escape)
- [ ] Focus states visible
- [ ] Screen reader friendly

---

## 🐛 Potential Issues to Check

### Issue 1: Route Not Found
- **Symptom:** Click navigation item → 404 error
- **Check:** Verify route exists in `routes/web.php`
- **Fix:** Add route hoặc update navigation config

### Issue 2: Active State Not Working
- **Symptom:** Navigation item không highlight khi active
- **Check:** Verify `Route::has()` và `request()->routeIs()` logic
- **Fix:** Update active state logic trong header-wrapper

### Issue 3: Notifications Not Loading
- **Symptom:** Notifications dropdown empty hoặc error
- **Check:** Verify `HeaderService::getNotifications()` returns data
- **Fix:** Check notifications data structure

### Issue 4: Logout Not Working
- **Symptom:** Click Sign out → không logout
- **Check:** Verify CSRF token và logout route
- **Fix:** Check logout form submission logic

### Issue 5: Mobile Menu Not Working
- **Symptom:** Hamburger button không mở menu
- **Check:** Verify Alpine.js initialized
- **Fix:** Check Alpine.js x-data và x-show directives

---

## 📝 Test Results

### Code Verification: ✅ PASSED
- [x] Admin layout setup đúng
- [x] HeaderService navigation đúng
- [x] Header wrapper logic đúng
- [x] Routes verification đúng

### Browser Test: ⏳ PENDING
- [ ] Cần test trên browser thực tế
- [ ] Cần verify UI/UX
- [ ] Cần test interactions

---

## 🚀 Next Steps

1. **Start Laravel Server:**
   ```bash
   php artisan serve
   ```

2. **Access Admin Dashboard:**
   - URL: `http://127.0.0.1:8000/admin/dashboard`
   - Login với super_admin user

3. **Test Each Checklist Item:**
   - Go through all test cases above
   - Document any issues found
   - Take screenshots if needed

4. **Fix Issues:**
   - Address any bugs found
   - Update code if needed
   - Re-test

---

## ✅ Summary

**Code Level:** ✅ **READY**  
**Browser Test:** ⏳ **PENDING**

Header đã được thiết kế lại và code verification passed. Cần test trên browser để verify UI/UX và interactions.

