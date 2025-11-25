# 🧪 TEST PLAN: invitations/accept.blade.php

**Ngày**: 2025-01-19
**Mục đích**: Verify không có breaking changes sau khi chuyển từ `layouts.auth` sang `layouts.auth-layout`

---

## ✅ PRE-TEST CHECKLIST

### 1. Route Verification
- [x] Route `GET /invitations/accept/{token}` exists
- [x] Controller `InvitationController@accept` exists
- [x] View `invitations.accept` exists và extends `layouts.auth-layout`

### 2. Layout Comparison

#### Before (auth.blade.php):
- Tailwind CDN: `<script src="https://cdn.tailwindcss.com"></script>`
- Alpine.js: `unpkg.com/alpinejs@3.x.x`
- Custom styles: `.btn-primary`, `.form-input`, etc. (sử dụng `@apply`)

#### After (auth-layout.blade.php):
- Vite assets: `@vite(['resources/css/app.css', 'resources/js/app.js'])`
- Custom styles: `.btn-primary`, `.form-input`, etc. (sử dụng `@apply`)
- ⚠️ **Potential Issue**: `@apply` directives chỉ hoạt động nếu Tailwind được compile trong Vite

---

## 🧪 TEST STEPS

### Step 1: Verify Route & Controller
```bash
php artisan route:list | grep invitations
```

### Step 2: Check Tailwind Build
```bash
# Verify Tailwind is configured in Vite
cat vite.config.js | grep tailwind
cat tailwind.config.js
```

### Step 3: Create Test Invitation
```bash
php artisan tinker
# Create test invitation
$invitation = \App\Models\Invitation::create([
    'email' => 'test@example.com',
    'token' => \Illuminate\Support\Str::random(64),
    'organization_id' => 1,
    'invited_by' => 1,
    'expires_at' => now()->addDays(7),
]);
echo $invitation->token;
```

### Step 4: Test View Rendering
1. Visit: `http://localhost:8000/invitations/accept/{token}`
2. Check:
   - ✅ Page loads without errors
   - ✅ Styles are applied correctly
   - ✅ Font Awesome icons display
   - ✅ Alpine.js works (form submission)
   - ✅ No console errors

---

## 🐛 POTENTIAL ISSUES & FIXES

### Issue 1: Tailwind @apply directives không hoạt động
**Symptoms**: Styles không apply, buttons/inputs không có styling

**Root Cause**: `@apply` directives trong `<style>` tag chỉ hoạt động nếu Tailwind được compile trong build process

**Fix Options**:
1. **Option A**: Thêm Tailwind CDN vào `auth-layout.blade.php` (temporary)
2. **Option B**: Move styles vào `resources/css/app.css` và compile với Vite
3. **Option C**: Convert `@apply` directives thành inline Tailwind classes

**Recommended**: Option B (move to CSS file) hoặc Option C (inline classes)

### Issue 2: Alpine.js không load
**Symptoms**: Form không submit, `x-data` không hoạt động

**Root Cause**: Alpine.js không được include trong `auth-layout.blade.php`

**Fix**: Add Alpine.js CDN hoặc đảm bảo `app.js` includes Alpine.js

### Issue 3: Font Awesome icons không hiển thị
**Symptoms**: Icons không hiển thị

**Root Cause**: Font Awesome không được include trong `auth-layout.blade.php`

**Fix**: Add Font Awesome CDN hoặc đảm bảo `app.css` includes Font Awesome

---

## ✅ VERIFICATION CHECKLIST

- [ ] Page loads successfully
- [ ] All styles are applied (buttons, inputs, layout)
- [ ] Font Awesome icons display correctly
- [ ] Alpine.js works (form interactions)
- [ ] CSRF token is present
- [ ] Form submission works
- [ ] No console errors
- [ ] Responsive design works (mobile/desktop)

---

## 📝 NOTES

1. View sử dụng inline Tailwind classes (không dùng `.btn-primary`, `.form-input` classes)
2. Layout có custom styles nhưng view có thể không dùng chúng
3. Cần verify Alpine.js được load từ Vite build hoặc CDN

---

**Status**: ⏳ **READY FOR TESTING**

