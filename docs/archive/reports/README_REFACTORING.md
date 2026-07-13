# 🔄 NAMING CONVENTION REFACTORING GUIDE

## 📋 **Tổng quan**

Plan này được thiết kế để refactor naming convention từ prefix `zena_` sang chuẩn Laravel, đảm bảo:
- ✅ **Không mất dữ liệu**
- ✅ **Không breaking changes**
- ✅ **Rollback capability**
- ✅ **Comprehensive coverage**

---

## 🎯 **Mục tiêu**

### **Trước khi refactor:**
```php
// ❌ Không chuẩn
zena_users, zena_components, zena_documents
ZenaUser, ZenaComponent, ZenaDocument
App\Models\Zena\User
```

### **Sau khi refactor:**
```php
// ✅ Chuẩn Laravel
users, components, documents
User, Component, Document
App\Models\User
```

---

## 📊 **Phân tích hiện tại**

### **Files cần refactor:**
- **Models:** 5 files
- **Controllers:** 1 file
- **Services:** 3 files
- **Views:** 0 files
- **Tests:** 4 files
- **Migrations:** 15 files
- **Seeders:** 1 file

### **Priority:**
- 🟡 **MEDIUM:** `zena_components` (6 files), `zena_documents` (9 files)
- 🟢 **LOW:** Các tables khác (<5 files)

---

## 🚀 **Cách thực hiện**

### **Option 1: Automated (Recommended)**
```bash
# 1. Phân tích hiện tại
php scripts/analyze_zena_references.php

# 2. Thực hiện refactoring an toàn
php scripts/safe_refactoring_executor.php execute

# 3. Validate kết quả
php scripts/validate_refactoring.php validate

# 4. Nếu có lỗi, rollback
php scripts/safe_refactoring_executor.php rollback
```

### **Option 2: Manual Step-by-step**
```bash
# 1. Backup database
mysqldump -u root -p zenamanage > backup_before_refactor.sql

# 2. Chạy migration rename tables
php artisan migrate

# 3. Refactor từng loại file
php scripts/refactor_naming_convention.php

# 4. Clear cache
php artisan route:clear && php artisan config:clear && php artisan cache:clear

# 5. Test functionality
php artisan test
```

---

## 📁 **Files được tạo**

### **Scripts:**
- `scripts/analyze_zena_references.php` - Phân tích references
- `scripts/safe_refactoring_executor.php` - Thực hiện refactoring an toàn
- `scripts/validate_refactoring.php` - Validate kết quả
- `scripts/refactor_naming_convention.php` - Refactor tự động

### **Documentation:**
- `REFACTOR_NAMING_CONVENTION_PLAN.md` - Plan chi tiết
- `README_REFACTORING.md` - Hướng dẫn sử dụng

### **Migrations:**
- `database/migrations/2025_09_19_174648_rename_zena_tables_to_standard_names.php`

---

## ⚠️ **Lưu ý quan trọng**

### **Trước khi thực hiện:**
1. ✅ **Backup database** đầy đủ
2. ✅ **Backup code** hiện tại
3. ✅ **Test environment** trước
4. ✅ **Thông báo team** về maintenance window

### **Trong quá trình:**
1. ✅ **Monitor logs** liên tục
2. ✅ **Test functionality** sau mỗi step
3. ✅ **Có sẵn rollback plan**
4. ✅ **Không deploy** trong giờ cao điểm

### **Sau khi hoàn thành:**
1. ✅ **Run full test suite**
2. ✅ **Verify all functionality**
3. ✅ **Update documentation**
4. ✅ **Monitor performance**

---

## 🔧 **Troubleshooting**

### **Lỗi thường gặp:**

#### **1. Table không tồn tại**
```bash
# Kiểm tra tables
php artisan tinker --execute="DB::select('SHOW TABLES');"

# Restore từ backup
mysql -u root -p zenamanage < backup_before_refactor.sql
```

#### **2. Model không load được**
```bash
# Clear cache
php artisan route:clear && php artisan config:clear && php artisan cache:clear

# Regenerate autoload
composer dump-autoload
```

#### **3. Foreign key constraints**
```bash
# Disable foreign key checks
SET FOREIGN_KEY_CHECKS=0;

# Run migration
php artisan migrate

# Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS=1;
```

---

## 📈 **Benefits sau refactoring**

### **Code Quality:**
- ✅ **Laravel compliance** - Follow standard conventions
- ✅ **Better readability** - Clear, consistent naming
- ✅ **Easier maintenance** - Standard patterns
- ✅ **Reduced confusion** - No duplicate naming

### **Development:**
- ✅ **Faster development** - Standard patterns
- ✅ **Better IDE support** - Proper autocomplete
- ✅ **Easier onboarding** - New developers familiar
- ✅ **Reduced bugs** - Consistent naming

### **Performance:**
- ✅ **Better caching** - Standard table names
- ✅ **Optimized queries** - Standard relationships
- ✅ **Reduced complexity** - Simpler codebase

---

## 🎯 **Next Steps**

### **Immediate:**
1. **Review plan** với team
2. **Schedule maintenance window**
3. **Prepare rollback strategy**
4. **Test trên staging environment**

### **Long-term:**
1. **Update coding standards**
2. **Train team** về new conventions
3. **Monitor** for any issues
4. **Document** lessons learned

---

## 📞 **Support**

Nếu gặp vấn đề trong quá trình refactoring:

1. **Check logs** trong `storage/logs/`
2. **Review backup** files
3. **Use rollback** scripts
4. **Contact team** để hỗ trợ

---

## ✅ **Success Criteria**

Refactoring được coi là thành công khi:

- [ ] ✅ All tests pass
- [ ] ✅ No broken functionality
- [ ] ✅ Improved code readability
- [ ] ✅ Better Laravel compliance
- [ ] ✅ Reduced technical debt
- [ ] ✅ Faster development velocity

---

**🎉 Happy Refactoring!**
