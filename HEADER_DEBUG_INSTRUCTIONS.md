# Header Debug Instructions

## 🔍 Debug Steps

### Bước 1: Mở dashboard
```
http://localhost:8000/app/dashboard
```

### Bước 2: Mở DevTools (F12)

### Bước 3: Kiểm tra Console tab

Bạn sẽ thấy các logs:

✅ **Nếu thấy**:
```
🔍 Debug: DOMContentLoaded fired
🔍 Debug: Mount element: <div id="header-mount">...</div>
🔍 Debug: User data: {id: "1", name: "...", ...}
✅ Debug: Calling initHeader...
Initializing header... {user: {...}, tenant: {...}}
🔍 HeaderShell rendering: {...}
```

→ React đang mount! Header sẽ xuất hiện.

---

❌ **Nếu thấy**:
```
🔍 Debug: DOMContentLoaded fired
🔍 Debug: Mount element: null
❌ Header mount element not found!
```

→ Mount element không tồn tại! Check HTML structure.

---

❌ **Nếu thấy**:
```
🔍 Debug: Mount element: <div id="header-mount">...</div>
🔍 Debug: initHeader function: undefined
❌ initHeader function not found!
```

→ React chưa load! Check file `public/build/assets/app-Bf4Wo0y4.js`

---

### Bước 4: Kiểm tra Elements tab

1. Mở **Elements** tab trong DevTools
2. Tìm element `<div id="header-mount">`
3. Bạn sẽ thấy:
   - **Nếu có background xám + border đứt**: Mount element đang chờ React
   - **Nếu có thẻ `<header>` bên trong**: React đã mount thành công!

### Bước 5: Kiểm tra Network tab

1. Mở **Network** tab
2. Reload page (Ctrl+R)
3. Tìm file `app-Bf4Wo0y4.js`
4. Check status:
   - **200 OK**: File load thành công
   - **404**: File không tồn tại → Run `npm run build`

---

## 📸 Screenshots Expected

### Trước khi React mount:
```html
<div id="header-mount" style="...">
    <div style="...">Waiting for React to mount...</div>
</div>
```

### Sau khi React mount:
```html
<div id="header-mount" style="...">
    <header class="header-shell" data-debug="header-shell">
        <div class="header-container">
            ...<!-- logo, nav, actions -->
        </div>
    </header>
</div>
```

---

## 🛠️ Common Issues & Fixes

### Issue 1: "Waiting for React to mount..." không đổi

**Nguyên nhân**: React chưa load hoặc `initHeader` không được gọi

**Fix**:
1. Check Console có lỗi không
2. Check Network tab xem file JS có load không
3. Check `window.initHeader` có tồn tại không

### Issue 2: Mount element không có background xám

**Nguyên nhân**: Inline style chưa apply

**Fix**: Hard refresh (Ctrl+Shift+R)

### Issue 3: React mount nhưng không thấy notification bell và user menu

**Nguyên nhân**: CSS ẩn hoặc component không render

**Fix**:
1. Check trong Elements tab xem có elements không
2. Check CSS có `display: none` không
3. Check Console có log "🔍 HeaderShell rendering" không

---

## 🧪 Test Scripts

### Test 1: Check mount element
```javascript
console.log('Mount element:', document.getElementById('header-mount'));
```

### Test 2: Check React function
```javascript
console.log('initHeader:', typeof window.initHeader);
```

### Test 3: Check user data
```javascript
const mountEl = document.getElementById('header-mount');
const userData = JSON.parse(mountEl?.dataset.user || 'null');
console.log('User data:', userData);
```

### Test 4: Force mount
```javascript
if (window.initHeader) {
    const mountEl = document.getElementById('header-mount');
    window.initHeader({
        user: JSON.parse(mountEl.dataset.user || 'null'),
        tenant: JSON.parse(mountEl.dataset.tenant || 'null'),
        menuItems: JSON.parse(mountEl.dataset.menuItems || '[]'),
        notifications: JSON.parse(mountEl.dataset.notifications || '[]'),
        unreadCount: parseInt(mountEl.dataset.unreadCount || '0'),
        breadcrumbs: JSON.parse(mountEl.dataset.breadcrumbs || '[]'),
        logoutUrl: mountEl.dataset.logoutUrl,
        csrfToken: mountEl.dataset.csrfToken,
    });
}
```

---

## 📊 Expected Results

Sau khi React mount thành công, bạn sẽ thấy:

1. **Header bar** ở trên cùng
2. **Logo** "ZenaManage" bên trái
3. **Navigation items** ở giữa (Dashboard, Projects, etc.)
4. **Theme toggle** button (moon/sun icon)
5. **Search** button
6. **Notifications bell** 🔔 với dropdown
7. **User avatar** với dropdown menu

Tất cả các buttons này khi click đều có dropdown/action.

---

## ❓ Nếu vẫn không thấy

Chụp ảnh màn hình:
1. Console tab với logs
2. Elements tab với `<div id="header-mount">` expanded
3. Network tab với file `app-Bf4Wo0y4.js`

Và gửi cho tôi để debug tiếp!

