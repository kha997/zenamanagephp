# Frontend Build Validation Scripts

## 📋 Overview

Các scripts validation này giúp phát hiện và ngăn chặn bugs trong quá trình build frontend, đặc biệt là:

- **Dependency conflicts** (CDN vs npm packages)
- **Blade syntax errors** (inline JavaScript, escaping issues)
- **Alpine.js component issues** (unregistered components, duplicate registrations)
- **Build output validation** (missing files, bundle sizes)
- **Orphaned code prevention** (unused imports, functions, classes, files, routes) ⭐ NEW

## 🚀 Usage

### Validate All (Recommended)

```bash
npm run validate:all
```

Chạy tất cả các validation scripts trước khi build.

### Individual Validations

```bash
# Check dependency conflicts
npm run validate:deps

# Check Blade syntax issues
npm run validate:blade

# Check Alpine.js components
npm run validate:alpine

# Check build output (sau khi build)
npm run validate:build

# Check orphaned code (unused imports, functions, classes) ⭐ NEW
npm run validate:orphaned

# Detect unused files ⭐ NEW
npm run validate:files

# Detect unused routes ⭐ NEW
npm run validate:routes

# Complete validation (all checks) ⭐ NEW
npm run validate:complete
```

### Automatic Validation

Validation scripts tự động chạy trong build process:

- **Pre-build**: `npm run validate:all` (chạy trước `npm run build`)
- **Post-build**: `npm run validate:build` (chạy sau `npm run build`)
- **Pre-commit**: Orphaned code checks (warnings only, non-blocking)

## 📁 Scripts

### 1. `validate-dependencies.js`

**Mục đích**: Phát hiện conflicts giữa CDN và npm packages

**Kiểm tra**:
- Alpine.js được load từ cả CDN và npm ❌
- Chart.js được load từ cả CDN và npm ⚠️
- Axios được load từ cả CDN và npm ⚠️
- Duplicate script tags trong Blade files ❌

**Exit code**: 
- `0` nếu pass
- `1` nếu có CRITICAL errors

### 2. `validate-blade-syntax.js`

**Mục đích**: Phát hiện syntax errors trong Blade templates

**Kiểm tra**:
- `x-data` attributes có line breaks ❌
- `@json()` usage với complex expressions ⚠️
- Alpine components được dùng nhưng chưa register ⚠️
- Unescaped quotes trong inline attributes ⚠️

**Exit code**: 
- `0` nếu pass
- `1` nếu có errors

### 3. `validate-alpine-components.js`

**Mục đích**: Đảm bảo tất cả Alpine components được register đúng

**Kiểm tra**:
- Components được reference trong `x-data` nhưng chưa register ❌
- Duplicate component registrations ⚠️

**Exit code**: 
- `0` nếu pass
- `1` nếu có errors

### 4. `validate-build-output.js`

**Mục đích**: Validate build output sau khi build

**Kiểm tra**:
- Build manifest tồn tại
- Tất cả files trong manifest tồn tại
- Bundle sizes không quá lớn (>1MB) ⚠️
- Required assets (`app.js`, `app.css`) tồn tại ❌

**Exit code**: 
- `0` nếu pass
- `1` nếu có errors

### 5. `validate-orphaned-code.js` ⭐ NEW

**Mục đích**: Ngăn chặn orphaned code (unused imports, functions, classes)

**Kiểm tra**:
- Unused imports trong JS/TS files ⚠️
- Unused functions (không được export) ⚠️
- Unused classes (không được export) ⚠️

**Exit code**: 
- `0` nếu pass (local mode)
- `1` nếu có warnings trong CI mode

**CI Mode**: Set `CI=true` để fail trên warnings

### 6. `detect-unused-files.js` ⭐ NEW

**Mục đích**: Detect files không được reference

**Kiểm tra**:
- Unused Blade components ⚠️
- Unused JS/TS files ⚠️
- Unused CSS files ⚠️

**Exit code**: 
- `0` (warnings only, không fail)

**Note**: Một số files có thể được sử dụng động, cần review manually

### 7. `detect-unused-routes.js` ⭐ NEW

**Mục đích**: Detect routes không được sử dụng

**Kiểm tra**:
- Routes không được reference trong code ⚠️
- API routes được exclude (có thể được dùng externally)

**Exit code**: 
- `0` (warnings only, không fail)

**Note**: API routes có thể được dùng externally, cần review manually

## 🔧 Fixing Issues

### Alpine.js Conflict (CRITICAL)

**Problem**: Alpine.js được load từ cả CDN và npm

**Solution**: Chọn một trong hai:

**Option A: Dùng CDN (Recommended cho development)**
```bash
# Xóa alpinejs từ package.json
npm uninstall alpinejs

# Giữ CDN script trong layouts/app.blade.php
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js"></script>
```

**Option B: Dùng npm package**
```bash
# Giữ alpinejs trong package.json
# Xóa CDN script từ layouts/app.blade.php
# Import trong resources/js/app.js:
import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();
```

### Chart.js Conflict (WARNING)

**Problem**: Chart.js được load từ cả CDN và npm

**Solution**: Chọn một cách, khuyến nghị dùng npm package.

### Blade Syntax Errors

**Problem**: `x-data` có line breaks

**Solution**: Move inline JavaScript vào function:

```blade
{{-- ❌ BAD --}}
<div x-data="{
    show: false,
    toggle() { this.show = !this.show }
}">

{{-- ✅ GOOD --}}
<script>
Alpine.data('myComponent', () => ({
    show: false,
    toggle() { this.show = !this.show }
}));
</script>
<div x-data="myComponent()">
```

### Unregistered Alpine Components

**Problem**: Component được dùng nhưng chưa register

**Solution**: Register component trước khi sử dụng:

```javascript
// resources/js/alpine-data-functions.js hoặc trong Blade
Alpine.data('headerComponent', function() {
    return {
        // component data
    };
});
```

### Orphaned Code (Unused Imports/Functions) ⭐ NEW

**Problem**: Unused imports hoặc functions

**Solution**: 
1. Remove unused imports
2. Export functions nếu được dùng externally
3. Remove functions nếu không được dùng

```javascript
// ❌ BAD
import { unusedFunction } from './utils';
function helper() { /* unused */ }

// ✅ GOOD
import { usedFunction } from './utils';
export function helper() { /* used externally */ }
```

### Unused Files ⭐ NEW

**Problem**: Files không được reference

**Solution**:
1. Review files manually
2. Archive thay vì xóa (nếu có thể cần sau)
3. Remove nếu chắc chắn không cần

### Unused Routes ⭐ NEW

**Problem**: Routes không được sử dụng

**Solution**:
1. Review routes - API routes có thể được dùng externally
2. Remove routes nếu không cần
3. Document routes nếu được dùng externally

## 📊 CI/CD Integration

### Pre-commit Hook

Orphaned code checks tự động chạy trong pre-commit hook:
- Warnings only (non-blocking)
- Fail trong CI/CD nếu có warnings

### GitHub Actions

Workflow tự động chạy trên mỗi PR:
- `.github/workflows/orphaned-code-check.yml`
- Comments results vào PR
- Upload reports as artifacts

## 🐛 Common Issues

### Script không chạy được

**Error**: `Cannot find module 'glob'`

**Solution**: 
```bash
npm install --save-dev glob
```

### Permission denied

**Solution**:
```bash
chmod +x scripts/validate/*.js
```

### False positives

**Problem**: Script báo unused nhưng thực ra đang được dùng

**Solution**:
- Export functions/classes nếu được dùng externally
- Review manually - một số usage có thể dynamic
- Thêm vào ignore list nếu cần

## ✅ Success Criteria

Build pass khi:
- ✅ Không có dependency conflicts
- ✅ Không có Blade syntax errors
- ✅ Tất cả Alpine components được register
- ✅ Build output hợp lệ
- ✅ Không có orphaned code (trong CI mode)
- ✅ Không có unused files (review manually)

## 📝 Notes

- Scripts sử dụng `glob` package để scan files
- Exit code `1` = failed, `0` = passed
- Warnings không block build (local mode), nhưng block trong CI mode
- Errors block build và cần fix ngay
- Một số false positives có thể xảy ra - cần review manually

## 🎯 Orphaned Code Prevention Strategy

### Pre-commit Validation
- Tự động check unused imports
- Tự động check unused functions/classes
- Warnings only (không block commit)

### CI/CD Validation
- Strict mode trong CI (`CI=true`)
- Fail build nếu có orphaned code
- Report results trong PR comments

### Best Practices
1. **Remove unused imports** ngay khi không dùng
2. **Export functions** nếu được dùng externally
3. **Document files** với purpose rõ ràng
4. **Review warnings** trước khi merge
5. **Archive files** thay vì xóa nếu có thể cần sau

### Weekly Cleanup
- Chạy `npm run validate:orphaned` weekly
- Review và cleanup unused code
- Archive unused files thay vì xóa
