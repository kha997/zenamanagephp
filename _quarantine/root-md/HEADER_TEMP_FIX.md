# Header Temporary Fix

## Vấn đề
Header không hiển thị nút thông báo và user menu dropdown.

## Có thể xảy ra khi
1. React chưa load xong
2. Data không được pass đúng
3. CSS ẩn elements
4. Component bị lỗi khi render

## Kiểm tra ngay

### Bước 1: Mở trang dashboard
```
http://localhost:8000/app/dashboard
```

### Bước 2: Mở Console (F12 → Console tab)

### Bước 3: Chạy lệnh sau để test
```javascript
// Test xem header mount có tồn tại không
const mountEl = document.getElementById('header-mount');
console.log('Mount element:', mountEl);
console.log('Mount innerHTML:', mountEl?.innerHTML);
```

### Bước 4: Test xem React có load không
```javascript
// Check React
console.log('initHeader function:', typeof window.initHeader);

// Check user data
const userData = JSON.parse(document.getElementById('header-mount')?.dataset.user || 'null');
console.log('User data:', userData);
```

## Nếu không có log hoặc error

### Option 1: Fallback về simple-header
Tạm thời quay lại dùng simple-header để đảm bảo app hoạt động:

1. Sửa `resources/views/layouts/app.blade.php`:
```blade
{{-- Temporary: Use simple-header for debugging --}}
<x-shared.simple-header :user="Auth::user()" variant="app" />
```

2. Comment out React header:
```blade
{{-- React HeaderShell --}}
{{-- <x-shared.header :user="Auth::user()" variant="app" /> --}}
```

### Option 2: Fix React mounting issue

Kiểm tra:
1. Vite build đã chạy chưa? → `npm run build`
2. File `public/build/app-Bf4Wo0y4.js` có load không?
3. Check Network tab xem file JS có load không

### Option 3: Debug data flow

1. Trong `header.blade.php`, thêm debug output:
```php
@php
    $user = $user ?? Auth::user();
    $tenant = $user?->tenant;
    
    // Debug: Echo user data
    echo "<!-- User: " . $user->email . " -->";
    echo "<!-- Tenant: " . ($tenant ? $tenant->name : 'none') . " -->";
@endphp
```

2. Check HTML source xem có comment không

## Quick Fix nhanh nhất

Nếu cần header hoạt động ngay:

1. Back to simple-header
2. Fix React trong thời gian riêng
3. Test từng phần

File `resources/views/layouts/app.blade.php`:

```blade
<body class="bg-gray-50" x-data="appLayout()">
    {{-- Use simple header for now --}}
    <x-shared.simple-header :user="Auth::user()" variant="app" />
    
    <main class="pt-20">
        @yield('content')
    </main>
</body>
```

## Sau khi fix xong

1. Chạy lại: `npm run build`
2. Hard refresh: Ctrl+Shift+R
3. Check console có lỗi không
4. Kiểm tra header có render không

