# Header Debug Checklist

## Kiểm tra Console Browser (F12)

1. Mở trang dashboard (`/app/dashboard`)
2. Mở Developer Tools (F12)
3. Kiểm tra Console tab:
   - Có log "Initializing header..." → React đang load
   - Có log "Header config:" → Data đang được pass
   - Có log "Loaded menu items:" → Menu items đã load
   - Có lỗi nào không?

## Kiểm tra Header Hiển Thị

1. **Nút thông báo (Notifications Bell)**:
   - Icon chuông 🔔 ở góc phải
   - Click vào phải có dropdown

2. **User Menu**:
   - Avatar/Initials ở góc phải
   - Click vào phải có dropdown (Profile, Settings, Logout)

3. **Theme Toggle**:
   - Icon mặt trăng/trời ở góc phải
   - Click để chuyển dark/light mode

4. **Search**:
   - Icon tìm kiếm ở góc phải
   - Nhấn Ctrl+K để mở search overlay

## Nếu Không Thấy Header

1. **Kiểm tra React có mount không**:
```javascript
// Trong Console
document.getElementById('header-mount')
// Phải trả về <div> element
```

2. **Kiểm tra React component**:
```javascript
// Trong Console
window.initHeader
// Phải trả về function
```

3. **Kiểm tra CSS**:
- Mở DevTools → Elements tab
- Tìm `#header-mount`
- Xem có element con nào không
- Check CSS có `display: none` không

## Cách Fix Nếu Lỗi

### Nếu không có log "Initializing header...":

**Vấn đề**: React chưa load
**Giải pháp**: Check file `public/build/assets/app-Bf4Wo0y4.js` có load không

### Nếu có log nhưng header trống:

**Vấn đề**: CSS hoặc data không đúng
**Giải pháp**: 
1. Check CSS classes (`header-shell`, `header-actions`, etc.)
2. Check data: userData, menuItems

### Nếu header có nhưng không thấy nút notification/user menu:

**Vấn đề**: Component không render hoặc bị ẩn
**Giải pháp**:
1. Check trong Elements tab xem có elements con không
2. Check CSS có `display: none` hay `visibility: hidden` không
3. Check z-index có đủ cao không

## Test Manual

Chạy các lệnh này trong Console để test:

```javascript
// Test 1: Check mount point
document.getElementById('header-mount')

// Test 2: Check React mount
document.querySelector('#header-mount').children

// Test 3: Check user data
window.Laravel?.user

// Test 4: Force re-render header
if (window.initHeader) {
    window.initHeader({
        user: window.Laravel?.user || null,
        tenant: null,
        menuItems: [],
        notifications: [],
        unreadCount: 0,
        breadcrumbs: [],
        logoutUrl: '/logout',
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || ''
    });
}
```

## Expected Output

Header phải có cấu trúc như này:

```
<header class="header-shell">
  <div class="header-container">
    <!-- Left: Logo + Hamburger -->
    <div>
      <button class="hamburger">...</button>
      <div class="header-logo">ZenaManage</div>
    </div>
    
    <!-- Center: Nav (desktop only) -->
    <nav class="header-nav">...</nav>
    
    <!-- Right: Actions -->
    <div class="header-actions">
      <button class="header-action-btn">Theme</button>
      <button class="header-action-btn">Search</button>
      <button class="header-action-btn">Notifications</button>
      <div class="header-user-menu">
        <button class="header-user-avatar">User</button>
      </div>
    </div>
  </div>
</header>
```

## Common Issues

### Issue 1: Header chỉ hiện "ZenaManage" logo
**Nguyên nhân**: Menu items rỗng
**Fix**: Check `config/menu.json` có data không

### Issue 2: Không thấy notification bell
**Nguyên nhân**: CSS ẩn hoặc component không render
**Fix**: Check CSS và console errors

### Issue 3: Không thấy user menu dropdown
**Nguyên nhân**: Click handler hoặc CSS
**Fix**: Check UserMenu component có error không

